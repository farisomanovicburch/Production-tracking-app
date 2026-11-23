<?php
require_once __DIR__ . '/ApiTestCase.php';

// What ErrorMiddleware writes to error.log. The server runs in another process, so it is pointed at a log folder
// of this test's own (LOG_DIRECTORY) and the test reads the file afterwards.
final class ErrorLogTest extends ApiTestCase
{
    private const FIXTURE_APP = 'backend/tests/fixtures/middleware-app.php';
    private const TIME = '\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]';

    private string $logFolder;

    protected function setUp(): void
    {
        $this->logFolder = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'error-log-test-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->logFolder . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->logFolder)) {
            rmdir($this->logFolder);
        }
    }

    private function serverLoggingToTestFolder(string $router = 'server.php', array $environment = []): string
    {
        return self::startServer(['LOG_DIRECTORY' => $this->logFolder] + $environment, $router);
    }

    private function errorLogLines(): array
    {
        $file = $this->logFolder . DIRECTORY_SEPARATOR . 'error.log';
        return file_exists($file) ? file($file, FILE_IGNORE_NEW_LINES) : [];
    }

    /* ---------- the middleware on its own (fixture app) ---------- */

    public function testA401IsLoggedAndTheClientStillGetsItsMessage(): void
    {
        $server = $this->serverLoggingToTestFolder(self::FIXTURE_APP);

        $response = $this->request('GET', '/unauthorized?page=2', null, $server);

        $this->assertSame(401, $response['status']);
        $this->assertSame(['status' => 401, 'error' => 'Login required.'], $response['body']);
        // The path only: a query string can carry values that do not belong in a log.
        $this->assertMatchesRegularExpression('/^' . self::TIME . ' ERROR GET \/unauthorized 401 user=- role=- Login required\.$/', $this->errorLogLines()[0] ?? '');
        $this->assertCount(1, $this->errorLogLines());
    }

    public function testA403IsLoggedWithTheUserAndRoleOfTheCaller(): void
    {
        $server = $this->serverLoggingToTestFolder(self::FIXTURE_APP);

        $response = $this->request('GET', '/forbidden-as-operator', null, $server);

        $this->assertSame(403, $response['status']);
        $this->assertSame(['status' => 403, 'error' => 'Only an admin may do this.'], $response['body']);
        $this->assertMatchesRegularExpression('/^' . self::TIME . ' ERROR GET \/forbidden-as-operator 403 user=2 role=operator Only an admin may do this\.$/', $this->errorLogLines()[0] ?? '');
    }

    public function testAnUnexpectedErrorIsLoggedWithItsClassFileAndLineButTheClientGetsNoDetails(): void
    {
        $server = $this->serverLoggingToTestFolder(self::FIXTURE_APP);

        $response = $this->request('GET', '/crash', null, $server);

        $this->assertSame(500, $response['status']);
        $this->assertSame(['status' => 500, 'error' => 'Something went wrong on the server. Please try again later.'], $response['body']);
        $this->assertStringNotContainsString('boom', $response['raw']);
        $this->assertMatchesRegularExpression('/^' . self::TIME . ' ERROR GET \/crash 500 user=- role=- RuntimeException: boom in .+middleware-app\.php:\d+$/', $this->errorLogLines()[0] ?? '');
    }

    public function testAMistakeOfTheClientIsNotLogged(): void
    {
        $server = $this->serverLoggingToTestFolder(self::FIXTURE_APP);

        $invalid = $this->request('GET', '/invalid', null, $server);
        $missing = $this->request('GET', '/missing', null, $server);

        // Still answered with their own status and message, only not written down.
        $this->assertSame([400, 404], [$invalid['status'], $missing['status']]);
        $this->assertSame([], $this->errorLogLines());
    }

    public function testAnUnknownUrlIsLoggedAndStillAnswers404(): void
    {
        $server = $this->serverLoggingToTestFolder(self::FIXTURE_APP);

        $response = $this->request('GET', '/no-such-url?token=abc123', null, $server);

        $this->assertSame(404, $response['status']);
        $this->assertSame(['status' => 404, 'error' => 'Endpoint GET /no-such-url does not exist.'], $response['body']);
        $this->assertMatchesRegularExpression('/^' . self::TIME . ' ERROR GET \/no-such-url 404 user=- role=- Endpoint GET \/no-such-url does not exist\.$/', $this->errorLogLines()[0] ?? '');
    }

    // Without this, every test run would add fake 500s and 404s to the error.log a developer reads when something
    // really breaks.
    public function testTheTestServersDoNotWriteIntoTheRealLogFolder(): void
    {
        $realLog = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'error.log';
        $sizeBefore = file_exists($realLog) ? filesize($realLog) : -1;

        $this->request('GET', '/backend/no-such-url');

        clearstatcache();
        $this->assertSame($sizeBefore, file_exists($realLog) ? filesize($realLog) : -1);
    }

    /* ---------- the real app: index.php registers the middleware ---------- */

    public function testTheRealAppLogsAnUnknownUrl(): void
    {
        $server = $this->serverLoggingToTestFolder();

        $this->request('DELETE', '/backend/machines/abc?force=1', null, $server);

        $this->assertMatchesRegularExpression('/^' . self::TIME . ' ERROR DELETE \/machines\/abc 404 user=- role=- Endpoint DELETE \/machines\/abc does not exist\.$/', $this->errorLogLines()[0] ?? '');
    }

    public function testTheRealAppLogsAServerError(): void
    {
        $server = $this->serverLoggingToTestFolder('server.php', ['DB_NAME' => 'database_that_does_not_exist']);

        $response = $this->request('GET', '/backend/users', null, $server);

        $this->assertSame(500, $response['status']);
        $this->assertMatchesRegularExpression('/^' . self::TIME . ' ERROR GET \/users 500 user=- role=- PDOException: .+ in .+\.php:\d+$/', $this->errorLogLines()[0] ?? '');
    }
}
