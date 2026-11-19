<?php
require_once __DIR__ . '/ApiTestCase.php';

final class SwaggerDocsTest extends ApiTestCase
{
    public function testSwaggerPhpGeneratesTheOpenApiDocumentOfTheApi(): void
    {
        $response = $this->request('GET', '/backend/public/v1/docs/swagger.php');

        $this->assertSame(200, $response['status']);
        $this->assertSame('3.0.0', $response['body']['openapi']);
        $this->assertSame('Production Tracker API', $response['body']['info']['title']);
        // swagger-php leaves "paths" out entirely when no endpoint is documented.
        $this->assertArrayHasKey('/', $response['body']['paths'] ?? []);
    }

    // Try it out sends its requests to this URL, so it must be the server the docs were opened on.
    public function testTheDocumentedServerIsTheBackendTheDocsWereOpenedFrom(): void
    {
        $response = $this->request('GET', '/backend/public/v1/docs/swagger.php');

        $this->assertMatchesRegularExpression('#^http://127\.0\.0\.1:\d+/backend/$#', $response['body']['servers'][0]['url']);
    }

    public function testTheDocsFolderServesTheSwaggerUiPage(): void
    {
        $response = $this->request('GET', '/backend/public/v1/docs/');

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('swagger-ui', $response['raw']);
    }
}
