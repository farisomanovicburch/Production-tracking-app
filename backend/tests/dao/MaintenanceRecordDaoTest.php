<?php
require_once __DIR__ . '/../DatabaseTestCase.php';
require_once __DIR__ . '/../../rest/dao/MaintenanceRecordDao.php';

final class MaintenanceRecordDaoTest extends DatabaseTestCase
{
    public function testGetByIdReturnsTheRecordWithItsMachineAndUserNames(): void
    {
        $recordId = $this->insertRow('maintenance_records', [
            'machine_id' => $this->createMachine('extrusion', 'Test extruder'),
            'user_id' => $this->createUser('Test Mechanic'),
            'maintenance_type' => 'preventive',
            'started_at' => '2026-10-20 08:00:00',
            'ended_at' => '2026-10-20 09:00:00',
            'description' => 'Test oil change',
        ]);

        $record = (new MaintenanceRecordDao())->getById($recordId);

        $this->assertSame(['Test extruder', 'Test Mechanic'], [$record['machine_name'], $record['user_name']]);
    }
}
