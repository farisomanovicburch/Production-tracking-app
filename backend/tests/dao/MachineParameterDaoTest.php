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

    public function testGetActiveByMachineLeavesOutDeactivatedParameters(): void
    {
        $machineId = $this->createMachine('extrusion');
        $this->insertRow('machine_parameters', ['machine_id' => $machineId, 'name' => 'Active', 'display_order' => 1]);
        $this->insertRow('machine_parameters', ['machine_id' => $machineId, 'name' => 'Deactivated', 'display_order' => 2, 'is_active' => 0]);

        $parameters = (new MachineParameterDao())->getActiveByMachine($machineId);

        $this->assertSame(['Active'], array_column($parameters, 'name'));
    }

    public function testGetByNameFindsTheParameterOfThatMachineInAnyCase(): void
    {
        $machineId = $this->createMachine('extrusion');
        $parameterId = $this->insertRow('machine_parameters', ['machine_id' => $machineId, 'name' => 'Screw speed']);

        $this->assertSame($parameterId, (new MachineParameterDao())->getByName($machineId, 'SCREW SPEED')['id']);
    }

    // Two machines may each have a "Screw speed": the name is unique per machine, not in the whole table.
    public function testGetByNameIgnoresParametersOfOtherMachines(): void
    {
        $this->insertRow('machine_parameters', ['machine_id' => $this->createMachine('extrusion'), 'name' => 'Screw speed']);

        $this->assertNull((new MachineParameterDao())->getByName($this->createMachine('extrusion'), 'Screw speed'));
    }

    public function testAParameterIsActiveByDefaultAndCanBeDeactivated(): void
    {
        $dao = new MachineParameterDao();
        $parameterId = $dao->add(['machine_id' => $this->createMachine('extrusion'), 'name' => 'Screw speed'])['id'];

        $this->assertSame(1, $dao->getById($parameterId)['is_active']);

        $dao->update(['is_active' => 0], $parameterId);

        $this->assertSame(0, $dao->getById($parameterId)['is_active']);
    }
}
