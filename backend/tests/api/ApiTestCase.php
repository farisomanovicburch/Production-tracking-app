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

    /*
     * Every route except login, register and the API root needs a token, so a request carries the sample admin's by
     * default: the route tests are about what the routes do, not about who calls them. A test that sends a token of its
     * own (an "Authorization: ..." entry in $extraHeaders) keeps it; one that needs no token at all uses
     * requestWithoutToken().
     * @return array{status: int, body: array|null, raw: string}
     */
    protected function request(string $method, string $path, ?array $body = null, ?string $baseUrl = null, array $extraHeaders = []): array
    {
        $sendsItsOwnToken = array_filter($extraHeaders, fn ($header) => stripos($header, 'Authorization:') === 0);
        if (!$sendsItsOwnToken) {
            $extraHeaders[] = 'Authorization: Bearer ' . $this->adminToken();
        }
        return $this->send($method, $path, $body, $baseUrl, $extraHeaders);
    }

    /** @return array{status: int, body: array|null, raw: string} */
    protected function requestWithoutToken(string $method, string $path, ?array $body = null, ?string $baseUrl = null): array
    {
        return $this->send($method, $path, $body, $baseUrl, []);
    }

    // One login for the whole test run. The token is valid for 24 hours and signed with the same secret on every
    // server a test starts, so the other servers (broken database, fake weather) accept it too.
    private static $adminToken = null;

    private function adminToken(): string
    {
        self::$adminToken ??= $this->send('POST', '/backend/auth/login', ['email' => 'admin@example.com', 'password' => 'admin123'], null, [])['body']['token'];
        return self::$adminToken;
    }

    private function send(string $method, string $path, ?array $body, ?string $baseUrl, array $extraHeaders): array
    {
        $curl = curl_init(($baseUrl ?? self::$baseUrl) . $path);
        $headers = array_merge(['Accept: application/json'], $extraHeaders);
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

    /*
     * Everything a production run needs, created through the API under a code of its own: route tests are not
     * rolled back, so a run test that used the sample data would change the stock other tests read.
     * The recipe is 90 % material_id + 10 % second_material_id, both with 1000 kg in stock.
     */
    protected function createRunSetup(string $code): array
    {
        $machineId = $this->request('POST', '/backend/machines', ['name' => "Route extruder $code", 'code' => "RX-$code", 'machine_type' => 'extrusion'])['body']['id'];
        $parameterId = $this->request('POST', "/backend/machines/$machineId/parameters", ['name' => 'Route zone'])['body']['id'];
        $productId = $this->request('POST', '/backend/products', ['code' => "RP-$code", 'name' => "Route film $code"])['body']['id'];
        $this->request('POST', "/backend/machines/$machineId/products", ['product_id' => $productId, 'role' => 'output']);

        $materialId = $this->request('POST', '/backend/materials', ['name' => "Route granulate $code", 'stock_qty' => 1000])['body']['id'];
        $secondMaterialId = $this->request('POST', '/backend/materials', ['name' => "Route additive $code", 'stock_qty' => 1000])['body']['id'];
        $recipeId = $this->request('POST', '/backend/recipes', ['name' => "Route run recipe $code"])['body']['id'];
        $this->request('POST', "/backend/recipes/$recipeId/items", ['material_id' => $materialId, 'percentage' => 90]);
        $this->request('POST', "/backend/recipes/$recipeId/items", ['material_id' => $secondMaterialId, 'percentage' => 10]);
        $this->request('POST', "/backend/recipes/$recipeId/products", ['product_id' => $productId]);

        $userId = $this->request('POST', '/backend/users', [
            'full_name' => "Route operator $code", 'email' => strtolower("route.$code@example.com"), 'password' => 'secret123',
        ])['body']['id'];

        return [
            'machine_id' => $machineId, 'parameter_id' => $parameterId, 'product_id' => $productId, 'recipe_id' => $recipeId,
            'material_id' => $materialId, 'second_material_id' => $secondMaterialId, 'user_id' => $userId,
        ];
    }

    // A stand-in for Open-Meteo (tests/fixtures/fake-open-meteo.php), started once and shared by the weather tests.
    private static $fakeOpenMeteoUrl = null;

    protected static function fakeOpenMeteoUrl(): string
    {
        self::$fakeOpenMeteoUrl ??= self::startServer([], 'backend/tests/fixtures/fake-open-meteo.php');
        return self::$fakeOpenMeteoUrl;
    }

    // A valid new run for a setup from createRunSetup(): 10 kg of the first material, nothing of the second.
    protected function runBody(array $setup, array $overrides = []): array
    {
        return array_merge([
            'machine_id' => $setup['machine_id'],
            'product_id' => $setup['product_id'],
            'recipe_id' => $setup['recipe_id'],
            'user_id' => $setup['user_id'],
            'started_at' => '2026-11-02T06:00',
            'ended_at' => '2026-11-02T14:00',
            'quantity_produced' => 500,
            'parameter_values' => [['machine_parameter_id' => $setup['parameter_id'], 'value' => 180]],
            'material_usages' => [['material_id' => $setup['material_id'], 'quantity_kg' => 10]],
        ], $overrides);
    }

    // Everything a conversion needs, under a code of its own: a bag machine with one parameter that takes a film
    // (input) and makes a bag (output), and a user.
    protected function createConversionSetup(string $code): array
    {
        $machineId = $this->request('POST', '/backend/machines', ['name' => "Route bag machine $code", 'code' => "RB-$code", 'machine_type' => 'conversion'])['body']['id'];
        $parameterId = $this->request('POST', "/backend/machines/$machineId/parameters", ['name' => 'Route weld time', 'unit' => 's'])['body']['id'];
        $inputProductId = $this->request('POST', '/backend/products', ['code' => "RI-$code", 'name' => "Route film $code"])['body']['id'];
        $outputProductId = $this->request('POST', '/backend/products', ['code' => "RO-$code", 'name' => "Route bag $code", 'unit' => 'pcs'])['body']['id'];
        $this->request('POST', "/backend/machines/$machineId/products", ['product_id' => $inputProductId, 'role' => 'input']);
        $this->request('POST', "/backend/machines/$machineId/products", ['product_id' => $outputProductId, 'role' => 'output']);
        $userId = $this->request('POST', '/backend/users', [
            'full_name' => "Route operator $code", 'email' => strtolower("route.conversion.$code@example.com"), 'password' => 'secret123',
        ])['body']['id'];

        return [
            'machine_id' => $machineId, 'parameter_id' => $parameterId, 'input_product_id' => $inputProductId,
            'output_product_id' => $outputProductId, 'user_id' => $userId,
        ];
    }

    protected function conversionBody(array $setup, array $overrides = []): array
    {
        return array_merge([
            'machine_id' => $setup['machine_id'],
            'user_id' => $setup['user_id'],
            'input_product_id' => $setup['input_product_id'],
            'output_product_id' => $setup['output_product_id'],
            'started_at' => '2026-11-03T07:00',
            'ended_at' => '2026-11-03T13:00',
            'input_kg' => 528,
            'output_kg' => 500,
            'bag_count' => 4200,
            'parameter_values' => [['machine_parameter_id' => $setup['parameter_id'], 'value' => 0.6]],
        ], $overrides);
    }

    // A second server with different settings, e.g. a database that does not exist. Settings come from
    // environment variables because Config reads those before its defaults. $router is the script that answers
    // every request (relative to the repository root): the app's server.php, or a fake external API.
    protected static function startServer(array $environment = [], string $router = 'server.php'): string
    {
        // Port 0 lets the operating system pick a free port, so a server already running on 8000 never clashes.
        $probe = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($probe, false), ':'), 1);
        fclose($probe);

        // The server's own errors go to a file: if they went to a pipe nobody reads, the server would block.
        $log = sys_get_temp_dir() . "/production-tracker-test-server-$port.log";
        self::$servers[] = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:$port", $router],
            [1 => ['file', $log, 'w'], 2 => ['file', $log, 'a']],
            $pipes,
            realpath(__DIR__ . '/../../..'),
            // The app logs 404s and 500s, and these servers get plenty on purpose: they log into the temp folder, not
            // into backend/logs. A test that wants to read the log passes its own LOG_DIRECTORY.
            array_merge(getenv(), ['LOG_DIRECTORY' => sys_get_temp_dir() . '/production-tracker-test-logs'], $environment)
        );

        $deadline = microtime(true) + 5;
        while (!@fsockopen('127.0.0.1', $port) && microtime(true) < $deadline) {
            usleep(50000);
        }
        return "http://127.0.0.1:$port";
    }
}
