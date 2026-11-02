<?php
require_once __DIR__ . '/../DatabaseTestCase.php';
require_once __DIR__ . '/../../rest/dao/ProductionRunDao.php';

final class ProductionRunDaoTest extends DatabaseTestCase
{
    public function testGetByIdReturnsTheRunWithItsMachineProductRecipeAndOperatorNames(): void
    {
        $runId = $this->createRun($this->createMachine('extrusion', 'Test extruder'), [
            'product_id' => $this->createProduct('Test film'),
            'recipe_id' => $this->createRecipe('Test recipe'),
            'user_id' => $this->createUser('Test Operator'),
        ]);

        $run = (new ProductionRunDao())->getById($runId);

        $this->assertSame(
            ['Test extruder', 'Test film', 'Test recipe', 'Test Operator'],
            [$run['machine_name'], $run['product_name'], $run['recipe_name'], $run['user_name']]
        );
    }

    public function testGetAllListsTheNewestRunFirst(): void
    {
        $startTimes = array_column((new ProductionRunDao())->getAll(), 'started_at');
        $newestFirst = $startTimes;
        rsort($newestFirst);

        $this->assertGreaterThan(1, count($startTimes), 'needs at least two runs to check an order');
        $this->assertSame($newestFirst, $startTimes);
    }
}
