<?php
require_once __DIR__ . '/../DatabaseTestCase.php';
require_once __DIR__ . '/../../rest/dao/RunParameterValueDao.php';

final class RunParameterValueDaoTest extends DatabaseTestCase
{
    public function testGetByRunReturnsOnlyThatRunsValuesWithParameterNamesInDisplayOrder(): void
    {
        $machineId = $this->createMachine('extrusion');
        $speedId = $this->insertRow('machine_parameters', ['machine_id' => $machineId, 'name' => 'Screw speed', 'unit' => 'rpm', 'display_order' => 2]);
        $temperatureId = $this->insertRow('machine_parameters', ['machine_id' => $machineId, 'name' => 'Zone 1 temperature', 'unit' => '°C', 'display_order' => 1]);
        $runId = $this->createRun($machineId);
        $otherRunId = $this->createRun($machineId);
        $this->insertRow('run_parameter_values', ['production_run_id' => $runId, 'machine_parameter_id' => $speedId, 'value' => 60]);
        $this->insertRow('run_parameter_values', ['production_run_id' => $runId, 'machine_parameter_id' => $temperatureId, 'value' => 180]);
        $this->insertRow('run_parameter_values', ['production_run_id' => $otherRunId, 'machine_parameter_id' => $temperatureId, 'value' => 175]);

        $values = (new RunParameterValueDao())->getByRun($runId);

        $this->assertSame(
            [['Zone 1 temperature', '°C', '180.000'], ['Screw speed', 'rpm', '60.000']],
            array_map(fn ($value) => [$value['parameter_name'], $value['parameter_unit'], $value['value']], $values)
        );
    }
}
