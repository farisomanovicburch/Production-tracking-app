<?php
require_once __DIR__ . '/ApiTestCase.php';

// Happy path + main failure case per route. The rules themselves are tested in MachineServiceTest.
final class MachineRoutesTest extends ApiTestCase
{
    private function createMachineThroughTheApi(string $code): array
    {
        return $this->request('POST', '/backend/machines', ['name' => 'Route test machine', 'code' => $code, 'machine_type' => 'extrusion']);
    }

    public function testListMachines(): void
    {
        $response = $this->request('GET', '/backend/machines');

        $this->assertSame(200, $response['status']);
        $this->assertContains('EX-01', array_column($response['body'], 'code'));
        $this->assertDocumented('GET', '/machines', 200);
    }

    public function testGetOneMachine(): void
    {
        $response = $this->request('GET', '/backend/machines/1');

        $this->assertSame(200, $response['status']);
        $this->assertSame('EX-01', $response['body']['code']);
        $this->assertDocumented('GET', '/machines/{id}', 200);
    }

    public function testGetAnUnknownMachineIsNotFound(): void
    {
        $response = $this->request('GET', '/backend/machines/999999');

        $this->assertSame(['status' => 404, 'error' => 'Machine not found.'], $response['body']);
        $this->assertDocumented('GET', '/machines/{id}', 404);
    }

    public function testCreateAMachineAnswers201WithTheNewMachine(): void
    {
        $response = $this->createMachineThroughTheApi('R-CREATE');

        $this->assertSame(201, $response['status']);
        $this->assertIsInt($response['body']['id']);
        $this->assertSame('R-CREATE', $response['body']['code']);
        $this->assertDocumented('POST', '/machines', 201);
    }

    public function testCreateAMachineWithADuplicateCodeIsABadRequest(): void
    {
        $response = $this->createMachineThroughTheApi('EX-01');

        $this->assertSame(['status' => 400, 'error' => 'A machine with this code already exists.'], $response['body']);
        $this->assertDocumented('POST', '/machines', 400);
    }

    public function testUpdateAMachine(): void
    {
        $id = $this->createMachineThroughTheApi('R-UPDATE')['body']['id'];

        $response = $this->request('PUT', "/backend/machines/$id", ['name' => 'Route renamed', 'code' => 'R-UPDATE']);

        $this->assertSame(200, $response['status']);
        $this->assertSame('Route renamed', $response['body']['name']);
        $this->assertDocumented('PUT', '/machines/{id}', 200);
    }

    public function testUpdateAMachineWithInvalidDataIsABadRequest(): void
    {
        $response = $this->request('PUT', '/backend/machines/1', ['name' => 'Extruder 1', 'code' => 'EX-01', 'machine_type' => 'conversion']);

        $this->assertSame(400, $response['status']);
        $this->assertDocumented('PUT', '/machines/{id}', 400);
    }

    public function testUpdateAnUnknownMachineIsNotFound(): void
    {
        $response = $this->request('PUT', '/backend/machines/999999', ['name' => 'Nothing', 'code' => 'R-NONE']);

        $this->assertSame(['status' => 404, 'error' => 'Machine not found.'], $response['body']);
        $this->assertDocumented('PUT', '/machines/{id}', 404);
    }

    public function testDeleteAMachine(): void
    {
        $id = $this->createMachineThroughTheApi('R-DELETE')['body']['id'];

        $response = $this->request('DELETE', "/backend/machines/$id");

        $this->assertSame(['message' => 'Machine deleted.'], $response['body']);
        $this->assertSame(404, $this->request('GET', "/backend/machines/$id")['status']);
        $this->assertDocumented('DELETE', '/machines/{id}', 200);
    }

    public function testDeleteAMachineWithHistoryIsABadRequest(): void
    {
        $response = $this->request('DELETE', '/backend/machines/1');

        $this->assertSame(400, $response['status']);
        $this->assertStringContainsString('Deactivate it instead.', $response['body']['error']);
        $this->assertDocumented('DELETE', '/machines/{id}', 400);
    }

    public function testDeleteAnUnknownMachineIsNotFound(): void
    {
        $response = $this->request('DELETE', '/backend/machines/999999');

        $this->assertSame(['status' => 404, 'error' => 'Machine not found.'], $response['body']);
        $this->assertDocumented('DELETE', '/machines/{id}', 404);
    }
}
