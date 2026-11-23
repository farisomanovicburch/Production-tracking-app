<?php
require_once __DIR__ . '/ApiTestCase.php';

// Happy path + main failure case per route. The rules themselves are tested in MaintenanceRecordServiceTest.
final class MaintenanceRecordRoutesTest extends ApiTestCase
{
    private const NOT_FOUND = ['status' => 404, 'error' => 'Maintenance record not found.'];

    // Sample machine 1 and user 2: maintenance moves no stock and has no children, so sharing them changes nothing.
    private function recordBody(array $overrides = []): array
    {
        return array_merge([
            'machine_id' => 1, 'user_id' => 2, 'maintenance_type' => 'preventive',
            'started_at' => '2026-11-04T07:00', 'ended_at' => '2026-11-04T09:30', 'description' => 'Route test oil change',
        ], $overrides);
    }

    private function createRecord(): int
    {
        return $this->request('POST', '/backend/maintenance-records', $this->recordBody())['body']['id'];
    }

    public function testListMaintenanceRecords(): void
    {
        $response = $this->request('GET', '/backend/maintenance-records');

        $this->assertSame(200, $response['status']);
        $this->assertContains('Gearbox oil change', array_column($response['body'], 'description'));
        $this->assertDocumented('GET', '/maintenance-records', 200);
    }

    public function testGetOneRecord(): void
    {
        $response = $this->request('GET', '/backend/maintenance-records/2');

        $this->assertSame(200, $response['status']);
        $this->assertSame(['Extruder 2', 'corrective'], [$response['body']['machine_name'], $response['body']['maintenance_type']]);
        $this->assertDocumented('GET', '/maintenance-records/{id}', 200);
    }

    public function testGetAnUnknownRecordIsNotFound(): void
    {
        $response = $this->request('GET', '/backend/maintenance-records/999999');

        $this->assertSame(self::NOT_FOUND, $response['body']);
        $this->assertDocumented('GET', '/maintenance-records/{id}', 404);
    }

    public function testCreateARecordAnswers201(): void
    {
        $response = $this->request('POST', '/backend/maintenance-records', $this->recordBody());

        $this->assertSame(201, $response['status']);
        $this->assertSame(['Extruder 1', '2026-11-04 07:00:00'], [$response['body']['machine_name'], $response['body']['started_at']]);
        $this->assertDocumented('POST', '/maintenance-records', 201);
    }

    public function testCreateARecordWithAnUnknownTypeIsABadRequest(): void
    {
        $response = $this->request('POST', '/backend/maintenance-records', $this->recordBody(['maintenance_type' => 'inspection']));

        $this->assertSame(['status' => 400, 'error' => "Field 'maintenance_type' must be one of: preventive, corrective."], $response['body']);
        $this->assertDocumented('POST', '/maintenance-records', 400);
    }

    public function testUpdateARecord(): void
    {
        $id = $this->createRecord();

        $response = $this->request('PUT', "/backend/maintenance-records/$id", ['maintenance_type' => 'corrective', 'notes' => 'Found a leaking seal']);

        $this->assertSame(200, $response['status']);
        $this->assertSame(['corrective', 'Found a leaking seal'], [$response['body']['maintenance_type'], $response['body']['notes']]);
        $this->assertDocumented('PUT', '/maintenance-records/{id}', 200);
    }

    public function testUpdateARecordWithTheEndBeforeTheStartIsABadRequest(): void
    {
        $id = $this->createRecord();

        $response = $this->request('PUT', "/backend/maintenance-records/$id", ['ended_at' => '2026-11-04T06:00']);

        $this->assertSame(['status' => 400, 'error' => 'The maintenance must end after it starts.'], $response['body']);
        $this->assertDocumented('PUT', '/maintenance-records/{id}', 400);
    }

    public function testUpdateAnUnknownRecordIsNotFound(): void
    {
        $response = $this->request('PUT', '/backend/maintenance-records/999999', ['notes' => 'x']);

        $this->assertSame(self::NOT_FOUND, $response['body']);
        $this->assertDocumented('PUT', '/maintenance-records/{id}', 404);
    }

    public function testDeleteARecord(): void
    {
        $id = $this->createRecord();

        $response = $this->request('DELETE', "/backend/maintenance-records/$id");

        $this->assertSame(['message' => 'Maintenance record deleted.'], $response['body']);
        $this->assertSame(404, $this->request('GET', "/backend/maintenance-records/$id")['status']);
        $this->assertDocumented('DELETE', '/maintenance-records/{id}', 200);
    }

    public function testDeleteAnUnknownRecordIsNotFound(): void
    {
        $response = $this->request('DELETE', '/backend/maintenance-records/999999');

        $this->assertSame(self::NOT_FOUND, $response['body']);
        $this->assertDocumented('DELETE', '/maintenance-records/{id}', 404);
    }
}
