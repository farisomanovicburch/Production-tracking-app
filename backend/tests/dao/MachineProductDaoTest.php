<?php
require_once __DIR__ . '/../DatabaseTestCase.php';
require_once __DIR__ . '/../../rest/dao/MachineProductDao.php';

final class MachineProductDaoTest extends DatabaseTestCase
{
    public function testGetByMachineReturnsOnlyThatMachinesProductsWithTheirNamesAndRoles(): void
    {
        $machineId = $this->insertRow('machines', ['name' => 'Test machine', 'code' => 'T-01', 'machine_type' => 'conversion']);
        $otherMachineId = $this->insertRow('machines', ['name' => 'Other machine', 'code' => 'T-02', 'machine_type' => 'extrusion']);
        $filmId = $this->insertRow('products', ['code' => 'T-FILM', 'name' => 'Test film']);
        $bagId = $this->insertRow('products', ['code' => 'T-BAG', 'name' => 'Test bag', 'unit' => 'pcs']);
        $this->insertRow('machine_products', ['machine_id' => $machineId, 'product_id' => $bagId, 'role' => 'output']);
        $this->insertRow('machine_products', ['machine_id' => $machineId, 'product_id' => $filmId, 'role' => 'input']);
        $this->insertRow('machine_products', ['machine_id' => $otherMachineId, 'product_id' => $filmId, 'role' => 'output']);

        $links = (new MachineProductDao())->getByMachine($machineId);

        $this->assertSame(
            [['Test film', 'input'], ['Test bag', 'output']],
            array_map(fn ($link) => [$link['product_name'], $link['role']], $links)
        );
    }
}
