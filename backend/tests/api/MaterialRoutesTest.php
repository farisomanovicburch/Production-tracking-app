<?php
require_once __DIR__ . '/ApiTestCase.php';

// Happy path + main failure case per route. The rules themselves are tested in MaterialServiceTest.
final class MaterialRoutesTest extends ApiTestCase
{
    private const NOT_FOUND = ['status' => 404, 'error' => 'Material not found.'];

    private function createMaterialThroughTheApi(string $name): array
    {
        return $this->request('POST', '/backend/materials', ['name' => $name, 'supplier' => 'Route supplier', 'stock_qty' => 100]);
    }

    public function testListMaterials(): void
    {
        $response = $this->request('GET', '/backend/materials');

        $this->assertSame(200, $response['status']);
        $this->assertContains('LDPE granulate', array_column($response['body'], 'name'));
        $this->assertDocumented('GET', '/materials', 200);
    }

    public function testGetOneMaterial(): void
    {
        $response = $this->request('GET', '/backend/materials/2');

        $this->assertSame(200, $response['status']);
        $this->assertSame('HDPE granulate', $response['body']['name']);
        $this->assertDocumented('GET', '/materials/{id}', 200);
    }

    public function testGetAnUnknownMaterialIsNotFound(): void
    {
        $response = $this->request('GET', '/backend/materials/999999');

        $this->assertSame(self::NOT_FOUND, $response['body']);
        $this->assertDocumented('GET', '/materials/{id}', 404);
    }

    public function testCreateAMaterialAnswers201WithItsStartingStock(): void
    {
        $response = $this->createMaterialThroughTheApi('Route create material');

        $this->assertSame(201, $response['status']);
        $this->assertSame('100.000', $response['body']['stock_qty']);
        $this->assertDocumented('POST', '/materials', 201);
    }

    public function testCreateAMaterialWithADuplicateNameIsABadRequest(): void
    {
        $response = $this->createMaterialThroughTheApi('LDPE granulate');

        $this->assertSame(['status' => 400, 'error' => 'A material with this name already exists.'], $response['body']);
        $this->assertDocumented('POST', '/materials', 400);
    }

    public function testUpdateAMaterialLeavesItsStockAlone(): void
    {
        $id = $this->createMaterialThroughTheApi('Route update material')['body']['id'];

        $response = $this->request('PUT', "/backend/materials/$id", ['name' => 'Route update material', 'min_stock_qty' => 20, 'stock_qty' => 5]);

        $this->assertSame(200, $response['status']);
        $this->assertSame(['20.000', '100.000'], [$response['body']['min_stock_qty'], $response['body']['stock_qty']]);
        $this->assertDocumented('PUT', '/materials/{id}', 200);
    }

    public function testUpdateAMaterialWithInvalidDataIsABadRequest(): void
    {
        $response = $this->request('PUT', '/backend/materials/2', ['name' => 'LDPE granulate']);

        $this->assertSame(400, $response['status']);
        $this->assertDocumented('PUT', '/materials/{id}', 400);
    }

    public function testUpdateAnUnknownMaterialIsNotFound(): void
    {
        $response = $this->request('PUT', '/backend/materials/999999', ['name' => 'Nothing']);

        $this->assertSame(self::NOT_FOUND, $response['body']);
        $this->assertDocumented('PUT', '/materials/{id}', 404);
    }

    public function testRecordADelivery(): void
    {
        $id = $this->createMaterialThroughTheApi('Route delivery material')['body']['id'];

        $response = $this->request('PUT', "/backend/materials/$id/delivery", ['quantity_kg' => 25.5]);

        $this->assertSame(200, $response['status']);
        $this->assertSame('125.500', $response['body']['stock_qty']);
        $this->assertDocumented('PUT', '/materials/{id}/delivery', 200);
    }

    public function testADeliveryOfZeroIsABadRequest(): void
    {
        $response = $this->request('PUT', '/backend/materials/2/delivery', ['quantity_kg' => 0]);

        $this->assertSame(400, $response['status']);
        $this->assertDocumented('PUT', '/materials/{id}/delivery', 400);
    }

    public function testADeliveryOfAnUnknownMaterialIsNotFound(): void
    {
        $response = $this->request('PUT', '/backend/materials/999999/delivery', ['quantity_kg' => 5]);

        $this->assertSame(self::NOT_FOUND, $response['body']);
        $this->assertDocumented('PUT', '/materials/{id}/delivery', 404);
    }

    public function testDeleteAMaterial(): void
    {
        $id = $this->createMaterialThroughTheApi('Route delete material')['body']['id'];

        $response = $this->request('DELETE', "/backend/materials/$id");

        $this->assertSame(['message' => 'Material deleted.'], $response['body']);
        $this->assertSame(404, $this->request('GET', "/backend/materials/$id")['status']);
        $this->assertDocumented('DELETE', '/materials/{id}', 200);
    }

    public function testDeleteAMaterialInUseIsABadRequest(): void
    {
        $response = $this->request('DELETE', '/backend/materials/1');

        $this->assertSame(400, $response['status']);
        $this->assertStringContainsString('Deactivate it instead.', $response['body']['error']);
        $this->assertDocumented('DELETE', '/materials/{id}', 400);
    }

    public function testDeleteAnUnknownMaterialIsNotFound(): void
    {
        $response = $this->request('DELETE', '/backend/materials/999999');

        $this->assertSame(self::NOT_FOUND, $response['body']);
        $this->assertDocumented('DELETE', '/materials/{id}', 404);
    }
}
