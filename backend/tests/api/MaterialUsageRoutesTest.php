<?php
require_once __DIR__ . '/ApiTestCase.php';

// Happy path + main failure case per route. The rules and stock movements are tested in MaterialUsageServiceTest.
final class MaterialUsageRoutesTest extends ApiTestCase
{
    private const NOT_FOUND = ['status' => 404, 'error' => 'Material usage not found.'];

    // A run of its own that used 10 kg of the first material; the second recipe material is still unused.
    private function createRun(string $code): array
    {
        $setup = $this->createRunSetup($code);
        $run = $this->request('POST', '/backend/production-runs', $this->runBody($setup))['body'];
        return $setup + ['run_id' => $run['id'], 'usage_id' => $run['material_usages'][0]['id']];
    }

    private function stockOf(int $materialId): string
    {
        return $this->request('GET', "/backend/materials/$materialId")['body']['stock_qty'];
    }

    public function testListTheUsagesOfARun(): void
    {
        $response = $this->request('GET', '/backend/production-runs/2/material-usages');

        $this->assertSame(200, $response['status']);
        $this->assertSame(['LDPE granulate', 'Slip additive'], array_column($response['body'], 'material_name'));
        $this->assertDocumented('GET', '/production-runs/{id}/material-usages', 200);
    }

    public function testListTheUsagesOfAnUnknownRunIsNotFound(): void
    {
        $response = $this->request('GET', '/backend/production-runs/999999/material-usages');

        $this->assertSame(['status' => 404, 'error' => 'Production run not found.'], $response['body']);
        $this->assertDocumented('GET', '/production-runs/{id}/material-usages', 404);
    }

    public function testAddAUsageAnswers201AndTakesTheStock(): void
    {
        $run = $this->createRun('MU-ADD');

        $response = $this->request('POST', "/backend/production-runs/{$run['run_id']}/material-usages", ['material_id' => $run['second_material_id'], 'quantity_kg' => 1.25]);

        $this->assertSame(201, $response['status']);
        $this->assertSame('1.250', $response['body']['quantity_kg']);
        $this->assertSame('998.750', $this->stockOf($run['second_material_id']));
        $this->assertDocumented('POST', '/production-runs/{id}/material-usages', 201);
    }

    // Black masterbatch (4) is not in the route test recipe.
    public function testAddAMaterialThatIsNotInTheRecipeIsABadRequest(): void
    {
        $run = $this->createRun('MU-BAD');

        $response = $this->request('POST', "/backend/production-runs/{$run['run_id']}/material-usages", ['material_id' => 4, 'quantity_kg' => 1]);

        $this->assertSame(['status' => 400, 'error' => "The selected material is not in this run's recipe."], $response['body']);
        $this->assertDocumented('POST', '/production-runs/{id}/material-usages', 400);
    }

    public function testAddAUsageToAnUnknownRunIsNotFound(): void
    {
        $response = $this->request('POST', '/backend/production-runs/999999/material-usages', ['material_id' => 1, 'quantity_kg' => 1]);

        $this->assertSame(['status' => 404, 'error' => 'Production run not found.'], $response['body']);
        $this->assertDocumented('POST', '/production-runs/{id}/material-usages', 404);
    }

    public function testUpdateAUsageMovesTheStockByTheDifference(): void
    {
        $run = $this->createRun('MU-PUT');

        $response = $this->request('PUT', "/backend/material-usages/{$run['usage_id']}", ['quantity_kg' => 25]);

        $this->assertSame(200, $response['status']);
        $this->assertSame(['25.000', '975.000'], [$response['body']['quantity_kg'], $this->stockOf($run['material_id'])]);
        $this->assertDocumented('PUT', '/material-usages/{id}', 200);
    }

    public function testUpdateAUsageBeyondTheStockIsABadRequest(): void
    {
        $run = $this->createRun('MU-STOCK');

        $response = $this->request('PUT', "/backend/material-usages/{$run['usage_id']}", ['quantity_kg' => 2000]);

        $this->assertSame(400, $response['status']);
        $this->assertSame("Not enough Route granulate MU-STOCK in stock: 990.000 kg left, 1990.000 kg needed.", $response['body']['error']);
        $this->assertDocumented('PUT', '/material-usages/{id}', 400);
    }

    public function testUpdateAnUnknownUsageIsNotFound(): void
    {
        $response = $this->request('PUT', '/backend/material-usages/999999', ['quantity_kg' => 1]);

        $this->assertSame(self::NOT_FOUND, $response['body']);
        $this->assertDocumented('PUT', '/material-usages/{id}', 404);
    }

    public function testDeleteAUsageGivesTheStockBack(): void
    {
        $run = $this->createRun('MU-DEL');

        $response = $this->request('DELETE', "/backend/material-usages/{$run['usage_id']}");

        $this->assertSame(['message' => 'Material usage deleted.'], $response['body']);
        $this->assertSame('1000.000', $this->stockOf($run['material_id']));
        $this->assertDocumented('DELETE', '/material-usages/{id}', 200);
    }

    public function testDeleteAnUnknownUsageIsNotFound(): void
    {
        $response = $this->request('DELETE', '/backend/material-usages/999999');

        $this->assertSame(self::NOT_FOUND, $response['body']);
        $this->assertDocumented('DELETE', '/material-usages/{id}', 404);
    }
}
