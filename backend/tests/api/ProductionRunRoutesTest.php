<?php
require_once __DIR__ . '/ApiTestCase.php';

// Happy path + main failure case per route. The rules themselves are tested in ProductionRunServiceTest.
final class ProductionRunRoutesTest extends ApiTestCase
{
    private const NOT_FOUND = ['status' => 404, 'error' => 'Production run not found.'];

    private function stockOf(int $materialId): string
    {
        return $this->request('GET', "/backend/materials/$materialId")['body']['stock_qty'];
    }

    public function testListRunsWithoutTheirChildren(): void
    {
        $response = $this->request('GET', '/backend/production-runs');

        $this->assertSame(200, $response['status']);
        $this->assertContains('Extruder 1', array_column($response['body'], 'machine_name'));
        $this->assertArrayNotHasKey('material_usages', $response['body'][0]);
        $this->assertDocumented('GET', '/production-runs', 200);
    }

    // Sample run 2 used LDPE granulate and slip additive.
    public function testGetOneRunWithItsValuesAndUsages(): void
    {
        $response = $this->request('GET', '/backend/production-runs/2');

        $this->assertSame(200, $response['status']);
        $this->assertSame(['LDPE granulate', 'Slip additive'], array_column($response['body']['material_usages'], 'material_name'));
        $this->assertCount(3, $response['body']['parameter_values']);
        $this->assertDocumented('GET', '/production-runs/{id}', 200);
    }

    public function testGetAnUnknownRunIsNotFound(): void
    {
        $response = $this->request('GET', '/backend/production-runs/999999');

        $this->assertSame(self::NOT_FOUND, $response['body']);
        $this->assertDocumented('GET', '/production-runs/{id}', 404);
    }

    public function testCreateARunAnswers201AndTakesTheStock(): void
    {
        $setup = $this->createRunSetup('PR-ADD');

        $response = $this->request('POST', '/backend/production-runs', $this->runBody($setup));

        $this->assertSame(201, $response['status']);
        $this->assertSame(['2026-11-02 06:00:00', '180.000'], [$response['body']['started_at'], $response['body']['parameter_values'][0]['value']]);
        $this->assertSame('990.000', $this->stockOf($setup['material_id']));
        $this->assertDocumented('POST', '/production-runs', 201);
    }

    public function testCreateARunWithNotEnoughStockIsABadRequestAndSavesNothing(): void
    {
        $setup = $this->createRunSetup('PR-STOCK');

        $response = $this->request('POST', '/backend/production-runs', $this->runBody($setup, [
            'material_usages' => [['material_id' => $setup['material_id'], 'quantity_kg' => 1000.5]],
        ]));

        $this->assertSame(400, $response['status']);
        $this->assertSame("Not enough Route granulate PR-STOCK in stock: 1000.000 kg left, 1000.500 kg needed.", $response['body']['error']);
        $this->assertSame('1000.000', $this->stockOf($setup['material_id']));
        $this->assertDocumented('POST', '/production-runs', 400);
    }

    public function testUpdateARun(): void
    {
        $id = $this->request('POST', '/backend/production-runs', $this->runBody($this->createRunSetup('PR-PUT')))['body']['id'];

        $response = $this->request('PUT', "/backend/production-runs/$id", ['scrap_kg' => 12.5, 'notes' => 'Corrected scrap']);

        $this->assertSame(200, $response['status']);
        $this->assertSame(['12.500', 'Corrected scrap'], [$response['body']['scrap_kg'], $response['body']['notes']]);
        $this->assertDocumented('PUT', '/production-runs/{id}', 200);
    }

    public function testUpdateARunWithTheEndBeforeTheStartIsABadRequest(): void
    {
        $id = $this->request('POST', '/backend/production-runs', $this->runBody($this->createRunSetup('PR-BAD')))['body']['id'];

        $response = $this->request('PUT', "/backend/production-runs/$id", ['ended_at' => '2026-11-02T05:00']);

        $this->assertSame(['status' => 400, 'error' => 'The run must end after it starts.'], $response['body']);
        $this->assertDocumented('PUT', '/production-runs/{id}', 400);
    }

    public function testUpdateAnUnknownRunIsNotFound(): void
    {
        $response = $this->request('PUT', '/backend/production-runs/999999', ['notes' => 'x']);

        $this->assertSame(self::NOT_FOUND, $response['body']);
        $this->assertDocumented('PUT', '/production-runs/{id}', 404);
    }

    public function testDeleteARunGivesTheStockBack(): void
    {
        $setup = $this->createRunSetup('PR-DEL');
        $id = $this->request('POST', '/backend/production-runs', $this->runBody($setup))['body']['id'];

        $response = $this->request('DELETE', "/backend/production-runs/$id");

        $this->assertSame(['message' => 'Production run deleted.'], $response['body']);
        $this->assertSame(404, $this->request('GET', "/backend/production-runs/$id")['status']);
        $this->assertSame('1000.000', $this->stockOf($setup['material_id']));
        $this->assertDocumented('DELETE', '/production-runs/{id}', 200);
    }

    public function testDeleteAnUnknownRunIsNotFound(): void
    {
        $response = $this->request('DELETE', '/backend/production-runs/999999');

        $this->assertSame(self::NOT_FOUND, $response['body']);
        $this->assertDocumented('DELETE', '/production-runs/{id}', 404);
    }
}
