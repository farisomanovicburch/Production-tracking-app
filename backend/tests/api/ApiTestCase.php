<?php
use PHPUnit\Framework\TestCase;

// Route tests send real HTTP requests to PHP's built-in server running server.php, exactly as a browser or
// Postman would. The server is a separate process, so its database writes are NOT rolled back like the DAO
// tests: route tests must not depend on each other's data (the bootstrap rebuilds the database every run).
abstract class ApiTestCase extends TestCase
{
    // One server for the whole test run: starting php -S per test would add a second to every test.
    private static $server = null;
    private static $baseUrl = null;

    public static function setUpBeforeClass(): void
    {
        if (self::$server === null) {
            self::startServer();
        }
    }

    /** @return array{status: int, body: array|null, raw: string} */
    protected function request(string $method, string $path, ?array $body = null): array
    {
        $curl = curl_init(self::$baseUrl . $path);
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

    private static function startServer(): void
    {
        // Port 0 lets the operating system pick a free port, so a server already running on 8000 never clashes.
        $probe = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($probe, false), ':'), 1);
        fclose($probe);

        $projectRoot = realpath(__DIR__ . '/../../..');
        // The server's own errors go to a file: if they went to a pipe nobody reads, the server would block.
        $log = sys_get_temp_dir() . '/production-tracker-test-server.log';
        self::$server = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:$port", 'server.php'],
            [1 => ['file', $log, 'w'], 2 => ['file', $log, 'a']],
            $pipes,
            $projectRoot
        );
        self::$baseUrl = "http://127.0.0.1:$port";

        $deadline = microtime(true) + 5;
        while (!@fsockopen('127.0.0.1', $port) && microtime(true) < $deadline) {
            usleep(50000);
        }
        register_shutdown_function(fn () => proc_terminate(self::$server));
    }
}
