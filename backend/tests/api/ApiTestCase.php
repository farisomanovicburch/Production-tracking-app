<?php
use PHPUnit\Framework\TestCase;

// Route tests send real HTTP requests to PHP's built-in server running server.php, exactly as a browser or
// Postman would. The server is a separate process, so its database writes are NOT rolled back like the DAO
// tests: route tests must not depend on each other's data (the bootstrap rebuilds the database every run).
abstract class ApiTestCase extends TestCase
{
    // One server for the whole test run: starting php -S per test would add a second to every test.
    private static $baseUrl = null;
    private static $servers = [];
    private static $openApi = null;

    public static function setUpBeforeClass(): void
    {
        if (self::$baseUrl === null) {
            self::$baseUrl = self::startServer();
            register_shutdown_function(function () {
                foreach (self::$servers as $server) {
                    proc_terminate($server);
                }
            });
        }
    }

    /** @return array{status: int, body: array|null, raw: string} */
    protected function request(string $method, string $path, ?array $body = null, ?string $baseUrl = null): array
    {
        $curl = curl_init(($baseUrl ?? self::$baseUrl) . $path);
        $headers = ['Accept: application/json'];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body));
        }
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 10,
        ]);
        $raw = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        return ['status' => $status, 'body' => json_decode((string) $raw, true), 'raw' => (string) $raw];
    }

    // The documentation must not lie: every status a route really answers with has to be listed in Swagger.
    protected function assertDocumented(string $method, string $pathTemplate, int $status): void
    {
        self::$openApi ??= $this->request('GET', '/backend/public/v1/docs/swagger.php')['body'];
        $responses = self::$openApi['paths'][$pathTemplate][strtolower($method)]['responses'] ?? [];

        $this->assertArrayHasKey((string) $status, $responses, "$method $pathTemplate does not document $status.");
    }

    // A second server with different settings, e.g. a database that does not exist. Settings come from
    // environment variables because Config reads those before its defaults.
    protected static function startServer(array $environment = []): string
    {
        // Port 0 lets the operating system pick a free port, so a server already running on 8000 never clashes.
        $probe = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($probe, false), ':'), 1);
        fclose($probe);

        // The server's own errors go to a file: if they went to a pipe nobody reads, the server would block.
        $log = sys_get_temp_dir() . "/production-tracker-test-server-$port.log";
        self::$servers[] = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:$port", 'server.php'],
            [1 => ['file', $log, 'w'], 2 => ['file', $log, 'a']],
            $pipes,
            realpath(__DIR__ . '/../../..'),
            $environment === [] ? null : array_merge(getenv(), $environment)
        );

        $deadline = microtime(true) + 5;
        while (!@fsockopen('127.0.0.1', $port) && microtime(true) < $deadline) {
            usleep(50000);
        }
        return "http://127.0.0.1:$port";
    }
}
