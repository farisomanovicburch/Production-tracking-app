<?php
require_once __DIR__ . '/../DatabaseTestCase.php';
require_once __DIR__ . '/../../rest/dao/ConversionRunParameterValueDao.php';

final class ConversionRunParameterValueDaoTest extends DatabaseTestCase
{
    public function testGetByConversionRunReturnsOnlyThatConversionsValuesWithParameterNames(): void
    {
        $machineId = $this->createMachine('conversion');
        $weldTimeId = $this->insertRow('machine_parameters', ['machine_id' => $machineId, 'name' => 'Weld time', 'unit' => 's', 'display_order' => 1]);
        $conversionId = $this->createConversion($machineId);
        $otherConversionId = $this->createConversion($machineId);
        $this->insertRow('conversion_run_parameter_values', ['conversion_run_id' => $conversionId, 'machine_parameter_id' => $weldTimeId, 'value' => 0.6]);
        $this->insertRow('conversion_run_parameter_values', ['conversion_run_id' => $otherConversionId, 'machine_parameter_id' => $weldTimeId, 'value' => 0.5]);

        $values = (new ConversionRunParameterValueDao())->getByConversionRun($conversionId);

        $this->assertSame(
            [['Weld time', 's', '0.600']],
            array_map(fn ($value) => [$value['parameter_name'], $value['parameter_unit'], $value['value']], $values)
        );
    }

    private function createConversion(int $machineId): int
    {
        return $this->insertRow('conversion_runs', [
            'machine_id' => $machineId,
            'user_id' => $this->createUser(),
            'input_product_id' => $this->createProduct(),
            'output_product_id' => $this->createProduct(),
            'started_at' => '2026-10-20 08:00:00',
            'ended_at' => '2026-10-20 14:00:00',
            'input_kg' => 100,
            'output_kg' => 90,
            'bag_count' => 800,
        ]);
    }
}
