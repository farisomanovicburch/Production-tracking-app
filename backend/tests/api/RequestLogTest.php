<?php
require_once __DIR__ . '/ApiTestCase.php';

// What RequestLogMiddleware writes to info.log. The server runs in another process, so it is pointed at a log folder
// of this test's own (LOG_DIRECTORY) and the test reads the file afterwards.
final class RequestLogTest extends ApiTestCase
{
    private const FIXTURE_APP = 'backend/tests/fixtures/middleware-app.php';
    private const TIME = '\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]';

    private string $logFolder;

    protected function setUp(): void
    {
        $this->logFolder = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'request-log-test-' . bin2hex(random_bytes(6));
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

    private function infoLogLines(): array
    {
        $file = $this->logFolder . DIRECTORY_SEPARATOR . 'info.log';
        return file_exists($file) ? file($file, FILE_IGNORE_NEW_LINES) : [];
    }

    public function testARequestIsLoggedWithMethodPathStatusAndDuration(): void
    {
        $server = $this->serverLoggingToTestFolder();

        $this->request('GET', '/backend/', null, $server);

        $this->assertMatchesRegularExpression('/^' . self::TIME . ' INFO GET \/ 200 user=- role=- \d+ms$/', $this->infoLogLines()[0] ?? '');
    }

    public function testEveryRequestGetsExactlyOneLineInTheOrderItArrived(): void
    {
        $server = $this->serverLoggingToTestFolder();

        $this->request('GET', '/backend/', null, $server);
        $this->request('GET', '/backend/users/999999', null, $server);
        $this->request('GET', '/backend/', null, $server);

        $lines = $this->infoLogLines();
        $this->assertCount(3, $lines);
        $this->assertStringContainsString(' GET / 200 ', $lines[0]);
        $this->assertStringContainsString(' GET /users/999999 404 ', $lines[1]);
        $this->assertStringContainsString(' GET / 200 ', $lines[2]);
    }

    // The status is only known when the answer is ready, and a refusal or a crash is an answer too.
    public function testTheLoggedStatusIsTheOneTheClientReceived(): void
    {
        $server = $this->serverLoggingToTestFolder(self::FIXTURE_APP);

        $statuses = [];
        foreach (['/invalid', '/unauthorized', '/crash', '/no-such-url'] as $path) {
            $statuses[] = $this->request('GET', $path, null, $server)['status'];
        }

        $this->assertSame([400, 401, 500, 404], $statuses);
        $logged = array_map(fn ($line) => preg_match('/ INFO GET (\S+) (\d{3}) /', $line, $found) ? [$found[1], (int) $found[2]] : null, $this->infoLogLines());
        $this->assertSame([['/invalid', 400], ['/unauthorized', 401], ['/crash', 500], ['/no-such-url', 404]], $logged);
    }

    public function testTheLogShowsWhoMadeTheRequest(): void
    {
        $server = $this->serverLoggingToTestFolder(self::FIXTURE_APP);

        $this->request('GET', '/forbidden-as-operator', null, $server);

        $this->assertMatchesRegularExpression('/^' . self::TIME . ' INFO GET \/forbidden-as-operator 403 user=2 role=operator \d+ms$/', $this->infoLogLines()[0] ?? '');
    }

    // A query string can carry values that do not belong in a log (a search text, a token).
    public function testTheQueryStringIsNotLogged(): void
    {
        $server = $this->serverLoggingToTestFolder();

        $this->request('GET', '/backend/users/999999?token=abc123&page=2', null, $server);

        $this->assertMatchesRegularExpression('/^' . self::TIME . ' INFO GET \/users\/999999 404 user=\d+ role=admin \d+ms$/', $this->infoLogLines()[0] ?? '');
    }

    public function testARequestThatFailsOnTheServerIsLoggedAs500(): void
    {
        $server = $this->serverLoggingToTestFolder('server.php', ['DB_NAME' => 'database_that_does_not_exist']);

        $this->request('GET', '/backend/users', null, $server);

        $this->assertMatchesRegularExpression('/^' . self::TIME . ' INFO GET \/users 500 user=- role=- \d+ms$/', $this->infoLogLines()[0] ?? '');
    }
}
