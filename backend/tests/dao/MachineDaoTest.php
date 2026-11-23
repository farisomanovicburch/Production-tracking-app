<?php
require_once __DIR__ . '/../DatabaseTestCase.php';
require_once __DIR__ . '/../../rest/dao/MachineDao.php';

final class MachineDaoTest extends DatabaseTestCase
{
    // The column's collation ignores case, so the UNIQUE index treats t-code and T-CODE as one code: the lookup must too.
    public function testGetByCodeFindsTheMachineInAnyCase(): void
    {
        $machineId = $this->insertRow('machines', ['name' => 'Test machine', 'code' => 'T-CODE', 'machine_type' => 'extrusion']);

        $this->assertSame($machineId, (new MachineDao())->getByCode('t-code')['id']);
    }

    public function testGetByCodeReturnsNullForAnUnknownCode(): void
    {
        $this->assertNull((new MachineDao())->getByCode('NO-SUCH-CODE'));
    }
}
