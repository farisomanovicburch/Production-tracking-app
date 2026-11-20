<?php
require_once __DIR__ . '/ApiTestCase.php';

// Happy path + main failure case per route. The rules themselves are tested in MachineParameterServiceTest.
final class MachineParameterRoutesTest extends ApiTestCase
{
    private const NOT_FOUND = ['status' => 404, 'error' => 'Machine parameter not found.'];

    // A machine of its own, so the parameters a test adds never collide with another test's or the sample data.
    private function createMachine(string $code): int
    {
        return $this->request('POST', '/backend/machines', ['name' => 'Route test machine', 'code' => $code, 'machine_type' => 'extrusion'])['body']['id'];
    }

    private function createParameter(string $machineCode): array
    {
        return $this->request('POST', '/backend/machines/' . $this->createMachine($machineCode) . '/parameters', ['name' => 'Screw speed', 'unit' => 'rpm']);
    }

    public function testListTheParametersOfAMachine(): void
    {
        $response = $this->request('GET', '/backend/machines/1/parameters');

        $this->assertSame(200, $response['status']);
        $this->assertSame(['Zone 1 temperature', 'Screw speed', 'Haul-off speed'], array_column($response['body'], 'name'));
        $this->assertDocumented('GET', '/machines/{id}/parameters', 200);
    }

    public function testListTheParametersOfAnUnknownMachineIsNotFound(): void
    {
        $response = $this->request('GET', '/backend/machines/999999/parameters');

        $this->assertSame(['status' => 404, 'error' => 'Machine not found.'], $response['body']);
        $this->assertDocumented('GET', '/machines/{id}/parameters', 404);
    }

    public function testAddAParameterAnswers201(): void
    {
        $response = $this->createParameter('R-P-ADD');

        $this->assertSame(201, $response['status']);
        $this->assertSame('Screw speed', $response['body']['name']);
        $this->assertDocumented('POST', '/machines/{id}/parameters', 201);
    }

    public function testAddAParameterWithADuplicateNameIsABadRequest(): void
    {
        $response = $this->request('POST', '/backend/machines/1/parameters', ['name' => 'Screw speed']);

        $this->assertSame(['status' => 400, 'error' => 'This machine already has a parameter with this name.'], $response['body']);
        $this->assertDocumented('POST', '/machines/{id}/parameters', 400);
    }

    public function testAddAParameterToAnUnknownMachineIsNotFound(): void
    {
        $response = $this->request('POST', '/backend/machines/999999/parameters', ['name' => 'Screw speed']);

        $this->assertSame(['status' => 404, 'error' => 'Machine not found.'], $response['body']);
        $this->assertDocumented('POST', '/machines/{id}/parameters', 404);
    }

    public function testGetOneParameter(): void
    {
        $response = $this->request('GET', '/backend/machine-parameters/2');

        $this->assertSame(200, $response['status']);
        $this->assertSame('Screw speed', $response['body']['name']);
        $this->assertDocumented('GET', '/machine-parameters/{id}', 200);
    }

    public function testGetAnUnknownParameterIsNotFound(): void
    {
        $response = $this->request('GET', '/backend/machine-parameters/999999');

        $this->assertSame(self::NOT_FOUND, $response['body']);
        $this->assertDocumented('GET', '/machine-parameters/{id}', 404);
    }

    public function testUpdateAParameter(): void
    {
        $id = $this->createParameter('R-P-UPD')['body']['id'];

        $response = $this->request('PUT', "/backend/machine-parameters/$id", ['name' => 'Screw speed', 'is_active' => false]);

        $this->assertSame(200, $response['status']);
        $this->assertSame(0, $response['body']['is_active']);
        $this->assertDocumented('PUT', '/machine-parameters/{id}', 200);
    }

    public function testUpdateAParameterWithInvalidDataIsABadRequest(): void
    {
        $response = $this->request('PUT', '/backend/machine-parameters/2', ['name' => 'Zone 1 temperature']);

        $this->assertSame(400, $response['status']);
        $this->assertDocumented('PUT', '/machine-parameters/{id}', 400);
    }

    public function testUpdateAnUnknownParameterIsNotFound(): void
    {
        $response = $this->request('PUT', '/backend/machine-parameters/999999', ['name' => 'Screw speed']);

        $this->assertSame(self::NOT_FOUND, $response['body']);
        $this->assertDocumented('PUT', '/machine-parameters/{id}', 404);
    }

    public function testDeleteAParameter(): void
    {
        $id = $this->createParameter('R-P-DEL')['body']['id'];

        $response = $this->request('DELETE', "/backend/machine-parameters/$id");

        $this->assertSame(['message' => 'Machine parameter deleted.'], $response['body']);
        $this->assertSame(404, $this->request('GET', "/backend/machine-parameters/$id")['status']);
        $this->assertDocumented('DELETE', '/machine-parameters/{id}', 200);
    }

    public function testDeleteAParameterWithSavedValuesIsABadRequest(): void
    {
        $response = $this->request('DELETE', '/backend/machine-parameters/1');

        $this->assertSame(400, $response['status']);
        $this->assertStringContainsString('Deactivate it instead.', $response['body']['error']);
        $this->assertDocumented('DELETE', '/machine-parameters/{id}', 400);
    }

    public function testDeleteAnUnknownParameterIsNotFound(): void
    {
        $response = $this->request('DELETE', '/backend/machine-parameters/999999');

        $this->assertSame(self::NOT_FOUND, $response['body']);
        $this->assertDocumented('DELETE', '/machine-parameters/{id}', 404);
    }
}
