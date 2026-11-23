<?php
require_once __DIR__ . '/ApiTestCase.php';

// Happy path + main failure case per route. The rules themselves are tested in ProductServiceTest.
final class ProductRoutesTest extends ApiTestCase
{
    private const NOT_FOUND = ['status' => 404, 'error' => 'Product not found.'];

    private function createProductThroughTheApi(string $code): array
    {
        return $this->request('POST', '/backend/products', ['code' => $code, 'name' => 'Route test film', 'width_mm' => 1000, 'thickness_um' => 50]);
    }

    public function testListProducts(): void
    {
        $response = $this->request('GET', '/backend/products');

        $this->assertSame(200, $response['status']);
        $this->assertContains('P-100', array_column($response['body'], 'code'));
        $this->assertDocumented('GET', '/products', 200);
    }

    public function testGetOneProduct(): void
    {
        $response = $this->request('GET', '/backend/products/1');

        $this->assertSame(200, $response['status']);
        $this->assertSame('P-100', $response['body']['code']);
        $this->assertDocumented('GET', '/products/{id}', 200);
    }

    public function testGetAnUnknownProductIsNotFound(): void
    {
        $response = $this->request('GET', '/backend/products/999999');

        $this->assertSame(self::NOT_FOUND, $response['body']);
        $this->assertDocumented('GET', '/products/{id}', 404);
    }

    public function testCreateAProductAnswers201(): void
    {
        $response = $this->createProductThroughTheApi('R-P-CREATE');

        $this->assertSame(201, $response['status']);
        $this->assertSame(1000, $response['body']['width_mm']);
        $this->assertDocumented('POST', '/products', 201);
    }

    public function testCreateAProductWithADuplicateCodeIsABadRequest(): void
    {
        $response = $this->createProductThroughTheApi('P-100');

        $this->assertSame(['status' => 400, 'error' => 'A product with this code already exists.'], $response['body']);
        $this->assertDocumented('POST', '/products', 400);
    }

    public function testUpdateAProduct(): void
    {
        $id = $this->createProductThroughTheApi('R-P-UPDATE')['body']['id'];

        $response = $this->request('PUT', "/backend/products/$id", ['code' => 'R-P-UPDATE', 'name' => 'Route renamed film']);

        $this->assertSame(200, $response['status']);
        $this->assertSame('Route renamed film', $response['body']['name']);
        $this->assertDocumented('PUT', '/products/{id}', 200);
    }

    public function testUpdateAProductWithInvalidDataIsABadRequest(): void
    {
        $response = $this->request('PUT', '/backend/products/1', ['code' => 'P-100', 'name' => 'LDPE film, clear', 'unit' => 'm']);

        $this->assertSame(400, $response['status']);
        $this->assertDocumented('PUT', '/products/{id}', 400);
    }

    public function testUpdateAnUnknownProductIsNotFound(): void
    {
        $response = $this->request('PUT', '/backend/products/999999', ['code' => 'R-P-NONE', 'name' => 'Nothing']);

        $this->assertSame(self::NOT_FOUND, $response['body']);
        $this->assertDocumented('PUT', '/products/{id}', 404);
    }

    public function testDeleteAProduct(): void
    {
        $id = $this->createProductThroughTheApi('R-P-DELETE')['body']['id'];

        $response = $this->request('DELETE', "/backend/products/$id");

        $this->assertSame(['message' => 'Product deleted.'], $response['body']);
        $this->assertSame(404, $this->request('GET', "/backend/products/$id")['status']);
        $this->assertDocumented('DELETE', '/products/{id}', 200);
    }

    public function testDeleteAProductWithRunsIsABadRequest(): void
    {
        $response = $this->request('DELETE', '/backend/products/1');

        $this->assertSame(400, $response['status']);
        $this->assertStringContainsString('Deactivate it instead.', $response['body']['error']);
        $this->assertDocumented('DELETE', '/products/{id}', 400);
    }

    public function testDeleteAnUnknownProductIsNotFound(): void
    {
        $response = $this->request('DELETE', '/backend/products/999999');

        $this->assertSame(self::NOT_FOUND, $response['body']);
        $this->assertDocumented('DELETE', '/products/{id}', 404);
    }
}
