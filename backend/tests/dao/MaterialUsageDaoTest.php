<?php
require_once __DIR__ . '/../DatabaseTestCase.php';
require_once __DIR__ . '/../../rest/dao/MaterialUsageDao.php';

final class MaterialUsageDaoTest extends DatabaseTestCase
{
    public function testGetByRunReturnsOnlyThatRunsUsagesWithMaterialNamesLargestFirst(): void
    {
        $machineId = $this->createMachine('extrusion');
        $runId = $this->createRun($machineId);
        $otherRunId = $this->createRun($machineId);
        $additiveId = $this->createMaterial('Test additive');
        $granulateId = $this->createMaterial('Test granulate');
        $this->insertRow('material_usages', ['production_run_id' => $runId, 'material_id' => $additiveId, 'quantity_kg' => 9]);
        $this->insertRow('material_usages', ['production_run_id' => $runId, 'material_id' => $granulateId, 'quantity_kg' => 900]);
        $this->insertRow('material_usages', ['production_run_id' => $otherRunId, 'material_id' => $granulateId, 'quantity_kg' => 100]);

        $usages = (new MaterialUsageDao())->getByRun($runId);

        $this->assertSame(
            [['Test granulate', '900.000'], ['Test additive', '9.000']],
            array_map(fn ($usage) => [$usage['material_name'], $usage['quantity_kg']], $usages)
        );
    }
}
