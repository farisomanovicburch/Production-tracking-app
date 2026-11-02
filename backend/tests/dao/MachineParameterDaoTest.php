<?php
require_once __DIR__ . '/../DatabaseTestCase.php';
require_once __DIR__ . '/../../rest/dao/MachineParameterDao.php';

final class MachineParameterDaoTest extends DatabaseTestCase
{
    public function testGetByMachineReturnsOnlyThatMachinesParametersInDisplayOrder(): void
    {
        $machineId = $this->insertRow('machines', ['name' => 'Test machine', 'code' => 'T-01', 'machine_type' => 'extrusion']);
        $otherMachineId = $this->insertRow('machines', ['name' => 'Other machine', 'code' => 'T-02', 'machine_type' => 'extrusion']);
        $this->insertRow('machine_parameters', ['machine_id' => $machineId, 'name' => 'Second', 'display_order' => 2]);
        $this->insertRow('machine_parameters', ['machine_id' => $machineId, 'name' => 'First', 'display_order' => 1]);
        $this->insertRow('machine_parameters', ['machine_id' => $otherMachineId, 'name' => 'Other', 'display_order' => 1]);

        $parameters = (new MachineParameterDao())->getByMachine($machineId);

        $this->assertSame(['First', 'Second'], array_column($parameters, 'name'));
    }
}
