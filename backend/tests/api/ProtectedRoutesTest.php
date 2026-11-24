<?php
require_once __DIR__ . '/ApiTestCase.php';

// Which routes need a token. The list of routes is read out of rest/routes/*.php, not typed here: a route added
// tomorrow is checked tomorrow, and a routes file that someone requires outside the protected group in index.php fails
// this test instead of staying open to everybody.
final class ProtectedRoutesTest extends ApiTestCase
{
    private const TIME = '\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]';

    private string $logFolder;

    protected function setUp(): void
    {
        $this->logFolder = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'protected-routes-test-' . bin2hex(random_bytes(6));
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

    /** @return string[] e.g. "GET /machines/1/parameters": every route of the API except the public auth routes */
    private function protectedRoutes(): array
    {
        $routes = [];
        foreach (glob(dirname(__DIR__, 2) . '/rest/routes/*Routes.php') as $file) {
            // Login and register are public on purpose; GET /auth/me checks the token itself.
            if (basename($file) === 'AuthRoutes.php') {
                continue;
            }
            preg_match_all("/Flight::route\('([A-Z]+) ([^']+)'/", file_get_contents($file), $found, PREG_SET_ORDER);
            foreach ($found as [, $method, $pattern]) {
                // "@id:[0-9]+" becomes the biggest id the database can hold. The token is checked first, so the id does not
                // matter when the app is protected; when it is not (this test failing), a DELETE or PUT on that id finds no
                // row and changes nothing, where id 1 would have deleted real data.
                $routes[] = $method . ' ' . preg_replace('/@\w+(:\[[^\]]+\]\+?)?/', '2147483647', $pattern);
            }
        }
        return $routes;
    }

    public function testEveryRouteOfTheApiAnswers401WithoutAToken(): void
    {
        $routes = $this->protectedRoutes();
        $this->assertGreaterThan(60, count($routes), 'The route files were not read: this test would prove nothing.');

        $notProtected = [];
        foreach ($routes as $route) {
            [$method, $path] = explode(' ', $route);
            $status = $this->requestWithoutToken($method, '/backend' . $path, $method === 'GET' || $method === 'DELETE' ? null : [])['status'];
            if ($status !== 401) {
                $notProtected[] = "$route answered $status";
            }
        }

        $this->assertSame([], $notProtected);
    }

    // Anonymous or not, the app does not say what exists: protected routes check the token before anything else.
    public function testAProtectedRouteAnswersWithTheSameMessageWhetherOrNotTheRecordExists(): void
    {
        $existing = $this->requestWithoutToken('GET', '/backend/users/1');
        $missing = $this->requestWithoutToken('GET', '/backend/users/999999');

        $this->assertSame(['status' => 401, 'error' => 'Missing authentication token.'], $existing['body']);
        $this->assertSame($existing['body'], $missing['body']);
    }

    public function testAValidTokenOpensAProtectedRoute(): void
    {
        $response = $this->request('GET', '/backend/machines');

        $this->assertSame(200, $response['status']);
    }

    public function testTheLoginRoutesStayPublic(): void
    {
        $login = $this->requestWithoutToken('POST', '/backend/auth/login', ['email' => 'nobody@example.com', 'password' => 'secret123']);
        $register = $this->requestWithoutToken('POST', '/backend/auth/register', ['full_name' => 'X']);

        // 401 and 400 are the routes' own answers (wrong login, incomplete form), not the middleware's.
        $this->assertSame('Invalid email or password.', $login['body']['error']);
        $this->assertSame(400, $register['status']);
    }

    /* ---------- what the logs say ---------- */

    public function testARefusedRequestIsWrittenToErrorLogWithTheReason(): void
    {
        $server = self::startServer(['LOG_DIRECTORY' => $this->logFolder]);

        $this->requestWithoutToken('GET', '/backend/machines?page=2', null, $server);

        $errorLogFile = $this->logFolder . '/error.log';
        $errorLog = file_exists($errorLogFile) ? file($errorLogFile, FILE_IGNORE_NEW_LINES) : [];
        $this->assertCount(1, $errorLog);
        $this->assertMatchesRegularExpression('/^' . self::TIME . ' ERROR GET \/machines 401 user=- role=- Missing authentication token\.$/', $errorLog[0]);
    }

    // The proof that routes behind the middleware can see who is calling, and that the role is the database's.
    public function testARequestWithATokenIsLoggedWithTheUserAndRole(): void
    {
        $server = self::startServer(['LOG_DIRECTORY' => $this->logFolder]);
        $token = $this->request('POST', '/backend/auth/login', ['email' => 'operator@example.com', 'password' => 'operator456'], $server)['body']['token'];

        $this->request('GET', '/backend/machines', null, $server, ["Authorization: Bearer $token"]);

        $this->assertMatchesRegularExpression('/^' . self::TIME . ' INFO GET \/machines 200 user=\d+ role=operator \d+ms\r?$/m', file_get_contents($this->logFolder . '/info.log'));
    }
}
