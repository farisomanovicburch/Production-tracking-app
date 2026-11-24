<?php
require_once __DIR__ . '/ApiTestCase.php';

final class FrontControllerTest extends ApiTestCase
{
    public function testTheApiRootAnswersThatTheBackendIsRunning(): void
    {
        $response = $this->request('GET', '/backend/');

        $this->assertSame(200, $response['status']);
        $this->assertSame('The backend is running.', $response['body']['message']);
    }

    // The root is the "is it up?" check of a person or a deployment tool: it has no data and needs no login.
    public function testTheApiRootNeedsNoToken(): void
    {
        $response = $this->requestWithoutToken('GET', '/backend/');

        $this->assertSame(200, $response['status']);
    }

    // Apache drops the Authorization header unless the rewrite rules hand it to PHP, and then every request answers
    // 401 "Missing authentication token" even with a correct token. php -S does not need the rule, so no HTTP test
    // can see it missing: the file itself is checked.
    public function testHtaccessPassesTheAuthorizationHeaderToPhp(): void
    {
        $htaccess = file_get_contents(dirname(__DIR__, 2) . '/.htaccess');

        $this->assertStringContainsString('RewriteCond %{HTTP:Authorization} .', $htaccess);
        $this->assertStringContainsString('RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]', $htaccess);
        $this->assertLessThan(strpos($htaccess, 'RewriteRule ^(.*)$ index.php'), strpos($htaccess, 'E=HTTP_AUTHORIZATION'), 'The header rule must come before the rule that sends the request to index.php.');
    }

    // The frontend shows every error the same way, so even a URL that matches no route answers JSON.
    public function testAnUnknownEndpointAnswers404AsJsonNamingTheEndpoint(): void
    {
        $response = $this->request('DELETE', '/backend/machines/abc?force=1');

        $this->assertSame(404, $response['status']);
        $this->assertSame(
            ['status' => 404, 'error' => 'Endpoint DELETE /machines/abc does not exist.'],
            $response['body']
        );
    }
}
