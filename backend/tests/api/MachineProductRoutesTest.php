<?php
require_once __DIR__ . '/ApiTestCase.php';

// Happy path + main failure case per route. The rules themselves are tested in MachineProductServiceTest.
final class MachineProductRoutesTest extends ApiTestCase
{
    private function createMachine(string $code): int
    {
        return $this->request('POST', '/backend/machines', ['name' => 'Route test machine', 'code' => $code, 'machine_type' => 'conversion'])['body']['id'];
    }

    public function testListTheProductsOfAMachine(): void
    {
        $response = $this->request('GET', '/backend/machines/3/products');

        $this->assertSame(200, $response['status']);
        $this->assertCount(4, $response['body']);
        $this->assertArrayHasKey('product_name', $response['body'][0]);
        $this->assertDocumented('GET', '/machines/{id}/products', 200);
    }

    public function testListTheProductsOfAnUnknownMachineIsNotFound(): void
    {
        $response = $this->request('GET', '/backend/machines/999999/products');

        $this->assertSame(['status' => 404, 'error' => 'Machine not found.'], $response['body']);
        $this->assertDocumented('GET', '/machines/{id}/products', 404);
    }

    public function testLinkAProductAnswers201(): void
    {
        $response = $this->request('POST', '/backend/machines/' . $this->createMachine('R-L-ADD') . '/products', ['product_id' => 1, 'role' => 'input']);

        $this->assertSame(201, $response['status']);
        $this->assertSame('input', $response['body']['role']);
        $this->assertDocumented('POST', '/machines/{id}/products', 201);
    }

    public function testLinkAnInputProductToAnExtruderIsABadRequest(): void
    {
        $response = $this->request('POST', '/backend/machines/1/products', ['product_id' => 3, 'role' => 'input']);

        $this->assertSame(400, $response['status']);
        $this->assertDocumented('POST', '/machines/{id}/products', 400);
    }

    public function testLinkAProductToAnUnknownMachineIsNotFound(): void
    {
        $response = $this->request('POST', '/backend/machines/999999/products', ['product_id' => 1, 'role' => 'output']);

        $this->assertSame(['status' => 404, 'error' => 'Machine not found.'], $response['body']);
        $this->assertDocumented('POST', '/machines/{id}/products', 404);
    }

    public function testRemoveAProductLink(): void
    {
        $machineId = $this->createMachine('R-L-DEL');
        $linkId = $this->request('POST', "/backend/machines/$machineId/products", ['product_id' => 1, 'role' => 'input'])['body']['id'];

        $response = $this->request('DELETE', "/backend/machine-products/$linkId");

        $this->assertSame(['message' => 'Product link removed.'], $response['body']);
        $this->assertSame([], $this->request('GET', "/backend/machines/$machineId/products")['body']);
        $this->assertDocumented('DELETE', '/machine-products/{id}', 200);
    }

    public function testRemoveAnUnknownProductLinkIsNotFound(): void
    {
        $response = $this->request('DELETE', '/backend/machine-products/999999');

        $this->assertSame(['status' => 404, 'error' => 'Machine product link not found.'], $response['body']);
        $this->assertDocumented('DELETE', '/machine-products/{id}', 404);
    }
}
