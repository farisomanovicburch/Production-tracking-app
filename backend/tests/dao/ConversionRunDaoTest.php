<?php
require_once __DIR__ . '/../DatabaseTestCase.php';
require_once __DIR__ . '/../../rest/dao/ConversionRunDao.php';

final class ConversionRunDaoTest extends DatabaseTestCase
{
    public function testGetByIdReturnsTheNamesAndScrapCalculatedFromInputMinusOutput(): void
    {
        $conversionId = $this->insertRow('conversion_runs', [
            'machine_id' => $this->createMachine('conversion', 'Test bag machine'),
            'user_id' => $this->createUser('Test Operator'),
            'input_product_id' => $this->createProduct('Test film'),
            'output_product_id' => $this->createProduct('Test bag', 'pcs'),
            'started_at' => '2026-10-20 08:00:00',
            'ended_at' => '2026-10-20 14:00:00',
            'input_kg' => 100,
            'output_kg' => 92.5,
            'bag_count' => 800,
        ]);

        $conversion = (new ConversionRunDao())->getById($conversionId);

        $this->assertSame(
            ['Test bag machine', 'Test Operator', 'Test film', 'Test bag', '7.500'],
            [
                $conversion['machine_name'], $conversion['user_name'],
                $conversion['input_product_name'], $conversion['output_product_name'], $conversion['scrap_kg'],
            ]
        );
    }
}
