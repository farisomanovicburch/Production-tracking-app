<?php
require_once __DIR__ . '/../DatabaseTestCase.php';
require_once __DIR__ . '/../../rest/services/ProductionRunService.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class ProductionRunServiceTest extends DatabaseTestCase
{
    private ProductionRunService $service;
    private int $machineId;
    private int $productId;
    private int $recipeId;
    private int $userId;
    private int $parameterId;
    private int $materialId;

    // A complete, valid setup built from fixtures: an extruder with one active parameter that outputs the product,
    // a recipe linked to the product that is 100 % one material with 100 kg in stock, and an active user.
    // Each test breaks exactly one piece of it.
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProductionRunService();
        $this->machineId = $this->createMachine('extrusion', 'Test extruder');
        $this->productId = $this->createProduct('Test film');
        $this->recipeId = $this->createRecipe('Test run recipe');
        $this->userId = $this->createUser('Test operator');
        $this->parameterId = $this->insertRow('machine_parameters', ['machine_id' => $this->machineId, 'name' => 'Test zone', 'display_order' => 1]);
        $this->materialId = $this->createStockedMaterial('Test granulate', 100);

        $this->insertRow('machine_products', ['machine_id' => $this->machineId, 'product_id' => $this->productId, 'role' => 'output']);
        $this->insertRow('recipe_products', ['recipe_id' => $this->recipeId, 'product_id' => $this->productId]);
        $this->insertRow('recipe_items', ['recipe_id' => $this->recipeId, 'material_id' => $this->materialId, 'percentage' => 100]);
    }

    private function createStockedMaterial(string $name, float $stockKg): int
    {
        return $this->insertRow('materials', ['name' => $name, 'stock_qty' => $stockKg]);
    }

    private function stockOf(int $materialId): string
    {
        $statement = $this->db->prepare('SELECT stock_qty FROM materials WHERE id = :id');
        $statement->execute(['id' => $materialId]);
        return $statement->fetchColumn();
    }

    private function validRun(array $overrides = []): array
    {
        return array_merge([
            'machine_id' => $this->machineId,
            'product_id' => $this->productId,
            'recipe_id' => $this->recipeId,
            'user_id' => $this->userId,
            'warmup_started_at' => '2026-10-20 05:30:00',
            'started_at' => '2026-10-20 06:00:00',
            'stable_at' => '2026-10-20 06:45:00',
            'ended_at' => '2026-10-20 14:00:00',
            'quantity_produced' => '870',
            'scrap_kg' => '42',
            'parameter_values' => [['machine_parameter_id' => $this->parameterId, 'value' => '180']],
            'material_usages' => [['material_id' => $this->materialId, 'quantity_kg' => '30']],
        ], $overrides);
    }

    private function deactivate(string $table, int $id): void
    {
        $this->db->prepare("UPDATE $table SET is_active = FALSE WHERE id = :id")->execute(['id' => $id]);
    }

    /* ---------- a valid run ---------- */

    public function testAddSavesTheRunAndReturnsItWithTheNames(): void
    {
        $run = $this->service->add($this->validRun());

        $this->assertSame(
            ['Test extruder', 'Test film', 'Test run recipe', 'Test operator', '870.000'],
            [$run['machine_name'], $run['product_name'], $run['recipe_name'], $run['user_name'], $run['quantity_produced']]
        );
    }

    public function testAddAcceptsTheTimesADatetimeLocalFieldSends(): void
    {
        $run = $this->service->add($this->validRun(['started_at' => '2026-10-20T06:00', 'ended_at' => '2026-10-20T14:00']));

        $this->assertSame(['2026-10-20 06:00:00', '2026-10-20 14:00:00'], [$run['started_at'], $run['ended_at']]);
    }

    public function testAddSavesEmptyOptionalFieldsAsNullAndEmptyScrapAsZero(): void
    {
        $run = $this->service->add($this->validRun([
            'warmup_started_at' => '', 'stable_at' => '', 'net_weight_per_unit_kg' => '', 'notes' => '', 'scrap_kg' => '',
        ]));

        $this->assertSame(
            [null, null, null, null, '0.000'],
            [$run['warmup_started_at'], $run['stable_at'], $run['net_weight_per_unit_kg'], $run['notes'], $run['scrap_kg']]
        );
    }

    /* ---------- fields ---------- */

    public static function requiredFields(): array
    {
        return [
            'machine' => ['machine_id'], 'product' => ['product_id'], 'recipe' => ['recipe_id'], 'user' => ['user_id'],
            'start' => ['started_at'], 'end' => ['ended_at'], 'quantity' => ['quantity_produced'],
        ];
    }

    #[DataProvider('requiredFields')]
    public function testAddRequiresTheField(string $field): void
    {
        $this->assertRejected(fn () => $this->service->add($this->validRun([$field => ''])), "Field '$field' is required.");
    }

    public function testAddRejectsATimeThatIsNotADate(): void
    {
        $this->assertRejected(
            fn () => $this->service->add($this->validRun(['ended_at' => '2026-02-30 14:00:00'])),
            "Field 'ended_at' must be a date and time (YYYY-MM-DD HH:MM:SS)."
        );
    }

    public function testAddRejectsANegativeQuantity(): void
    {
        $this->assertRejected(
            fn () => $this->service->add($this->validRun(['quantity_produced' => '-1'])),
            "Field 'quantity_produced' must be at least 0."
        );
    }

    public function testAddRejectsAGrossWeightBelowTheNetWeight(): void
    {
        $this->assertRejected(
            fn () => $this->service->add($this->validRun(['net_weight_per_unit_kg' => '25', 'gross_weight_per_unit_kg' => '24.5'])),
            'The gross weight per unit cannot be less than the net weight.'
        );
    }

    /* ---------- machine ---------- */

    public function testAddRejectsAnUnknownMachine(): void
    {
        $this->assertRejected(fn () => $this->service->add($this->validRun(['machine_id' => 999999])), 'The selected machine does not exist.');
    }

    public function testAddRejectsADeactivatedMachine(): void
    {
        $this->deactivate('machines', $this->machineId);

        $this->assertRejected(
            fn () => $this->service->add($this->validRun()),
            'The selected machine is deactivated and cannot be used for new runs.'
        );
    }

    public function testAddRejectsAConversionMachine(): void
    {
        $bagMachineId = $this->createMachine('conversion');
        $this->insertRow('machine_products', ['machine_id' => $bagMachineId, 'product_id' => $this->productId, 'role' => 'output']);

        $this->assertRejected(
            fn () => $this->service->add($this->validRun(['machine_id' => $bagMachineId])),
            'The selected machine is not an extrusion machine.'
        );
    }

    /* ---------- product ---------- */

    public function testAddRejectsAnUnknownProduct(): void
    {
        $this->assertRejected(fn () => $this->service->add($this->validRun(['product_id' => 999999])), 'The selected product does not exist.');
    }

    public function testAddRejectsADeactivatedProduct(): void
    {
        $this->deactivate('products', $this->productId);

        $this->assertRejected(
            fn () => $this->service->add($this->validRun()),
            'The selected product is deactivated and cannot be used for new runs.'
        );
    }

    // Linked to the machine, but as an input: the role is part of the rule, not only the link.
    public function testAddRejectsAProductThatIsNotAnOutputOfTheMachine(): void
    {
        $inputProductId = $this->createProduct();
        $this->insertRow('machine_products', ['machine_id' => $this->machineId, 'product_id' => $inputProductId, 'role' => 'input']);
        $this->insertRow('recipe_products', ['recipe_id' => $this->recipeId, 'product_id' => $inputProductId]);

        $this->assertRejected(
            fn () => $this->service->add($this->validRun(['product_id' => $inputProductId])),
            'The selected machine does not make the selected product.'
        );
    }

    /* ---------- recipe ---------- */

    public function testAddRejectsAnUnknownRecipe(): void
    {
        $this->assertRejected(fn () => $this->service->add($this->validRun(['recipe_id' => 999999])), 'The selected recipe does not exist.');
    }

    public function testAddRejectsADeactivatedRecipe(): void
    {
        $this->deactivate('recipes', $this->recipeId);

        $this->assertRejected(
            fn () => $this->service->add($this->validRun()),
            'The selected recipe is deactivated and cannot be used for new runs.'
        );
    }

    public function testAddRejectsARecipeThatIsNotLinkedToTheProduct(): void
    {
        $otherRecipeId = $this->createRecipe();
        $this->insertRow('recipe_items', ['recipe_id' => $otherRecipeId, 'material_id' => $this->createMaterial(), 'percentage' => 100]);

        $this->assertRejected(
            fn () => $this->service->add($this->validRun(['recipe_id' => $otherRecipeId])),
            'The selected recipe is not linked to the selected product.'
        );
    }

    public function testAddRejectsARecipeThatDoesNotAddUpToAHundredPercent(): void
    {
        $unfinishedRecipeId = $this->createRecipe();
        $this->insertRow('recipe_products', ['recipe_id' => $unfinishedRecipeId, 'product_id' => $this->productId]);
        $this->insertRow('recipe_items', ['recipe_id' => $unfinishedRecipeId, 'material_id' => $this->createMaterial(), 'percentage' => 80]);

        $this->assertRejected(
            fn () => $this->service->add($this->validRun(['recipe_id' => $unfinishedRecipeId])),
            'The materials of the selected recipe add up to 80.00 %, not 100 %.'
        );
    }

    /* ---------- user ---------- */

    public function testAddRejectsAnUnknownUser(): void
    {
        $this->assertRejected(fn () => $this->service->add($this->validRun(['user_id' => 999999])), 'The selected user does not exist.');
    }

    public function testAddRejectsADeactivatedUser(): void
    {
        $this->deactivate('users', $this->userId);

        $this->assertRejected(
            fn () => $this->service->add($this->validRun()),
            'The selected user is deactivated and cannot log new runs.'
        );
    }

    /* ---------- time order: warm-up < start < stable < end ---------- */

    public function testAddRejectsARunThatDoesNotEndAfterItStarts(): void
    {
        $this->assertRejected(
            fn () => $this->service->add($this->validRun(['stable_at' => '', 'ended_at' => '2026-10-20 06:00:00'])),
            'The run must end after it starts.'
        );
    }

    public function testAddRejectsAWarmUpThatDoesNotStartBeforeTheRun(): void
    {
        $this->assertRejected(
            fn () => $this->service->add($this->validRun(['warmup_started_at' => '2026-10-20 06:00:00'])),
            'The warm-up must start before the run starts.'
        );
    }

    public function testAddRejectsAStableTimeAtTheStart(): void
    {
        $this->assertRejected(
            fn () => $this->service->add($this->validRun(['stable_at' => '2026-10-20 06:00:00'])),
            'The run must become stable after it starts and before it ends.'
        );
    }

    public function testAddRejectsAStableTimeAtTheEnd(): void
    {
        $this->assertRejected(
            fn () => $this->service->add($this->validRun(['stable_at' => '2026-10-20 14:00:00'])),
            'The run must become stable after it starts and before it ends.'
        );
    }

    /* ---------- parameter values and material usages, saved with the run ---------- */

    public function testAddSavesTheValuesAndUsagesWithTheRun(): void
    {
        $run = $this->service->add($this->validRun());

        $this->assertSame(
            [['Test zone', '180.000'], ['Test granulate', '30.000']],
            [
                [$run['parameter_values'][0]['parameter_name'], $run['parameter_values'][0]['value']],
                [$run['material_usages'][0]['material_name'], $run['material_usages'][0]['quantity_kg']],
            ]
        );
    }

    public function testAddTakesTheUsedMaterialFromStock(): void
    {
        $this->service->add($this->validRun());

        $this->assertSame('70.000', $this->stockOf($this->materialId));
    }

    public function testAddRequiresAValueForEveryActiveParameter(): void
    {
        $this->insertRow('machine_parameters', ['machine_id' => $this->machineId, 'name' => 'Test speed', 'display_order' => 2]);

        $this->assertRejected(
            fn () => $this->service->add($this->validRun()),
            'Missing values for these parameters: Test speed.'
        );
    }

    public function testAddDoesNotAskForADeactivatedParameter(): void
    {
        $this->insertRow('machine_parameters', ['machine_id' => $this->machineId, 'name' => 'Old zone', 'is_active' => 0]);

        $run = $this->service->add($this->validRun());

        $this->assertCount(1, $run['parameter_values']);
    }

    public function testAddRejectsAValueForADeactivatedParameter(): void
    {
        $oldParameterId = $this->insertRow('machine_parameters', ['machine_id' => $this->machineId, 'name' => 'Old zone', 'is_active' => 0]);

        $this->assertRejected(
            fn () => $this->service->add($this->validRun(['parameter_values' => [
                ['machine_parameter_id' => $this->parameterId, 'value' => '180'],
                ['machine_parameter_id' => $oldParameterId, 'value' => '1'],
            ]])),
            'The selected parameter is deactivated.'
        );
    }

    public function testAddRejectsAValueForAParameterOfAnotherMachine(): void
    {
        $foreignParameterId = $this->insertRow('machine_parameters', ['machine_id' => $this->createMachine('extrusion'), 'name' => 'Foreign zone']);

        $this->assertRejected(
            fn () => $this->service->add($this->validRun(['parameter_values' => [
                ['machine_parameter_id' => $this->parameterId, 'value' => '180'],
                ['machine_parameter_id' => $foreignParameterId, 'value' => '1'],
            ]])),
            "The selected parameter does not belong to this run's machine."
        );
    }

    public function testAddRejectsParameterValuesThatAreNotAList(): void
    {
        $this->assertRejected(
            fn () => $this->service->add($this->validRun(['parameter_values' => 'Zone 1: 180'])),
            "Field 'parameter_values' must be a list of objects."
        );
    }

    public function testAddRequiresAtLeastOneMaterialUsage(): void
    {
        $this->assertRejected(fn () => $this->service->add($this->validRun(['material_usages' => []])), 'A run must use at least one material.');
    }

    public function testAddRejectsAMaterialThatIsNotInTheRecipe(): void
    {
        $this->assertRejected(
            fn () => $this->service->add($this->validRun(['material_usages' => [
                ['material_id' => $this->createStockedMaterial('Not in recipe', 50), 'quantity_kg' => '1'],
            ]])),
            "The selected material is not in this run's recipe."
        );
    }

    public function testAddRejectsADeactivatedMaterial(): void
    {
        $this->deactivate('materials', $this->materialId);

        $this->assertRejected(
            fn () => $this->service->add($this->validRun()),
            'The selected material is deactivated and cannot be used for new runs.'
        );
    }

    // The thorough case: the run row and its value are written before the stock check fails, and all of it must go.
    public function testAddWithNotEnoughStockSavesNothing(): void
    {
        $this->assertRejected(
            fn () => $this->service->add($this->validRun(['material_usages' => [['material_id' => $this->materialId, 'quantity_kg' => '150']]])),
            'Not enough Test granulate in stock: 100.000 kg left, 150.000 kg needed.'
        );

        $this->assertSame([0, '100.000'], [$this->countRows('production_runs', 'machine_id', $this->machineId), $this->stockOf($this->materialId)]);
    }

    public function testAddRejectsTheSameMaterialTwiceAndSavesNothing(): void
    {
        $this->assertRejected(
            fn () => $this->service->add($this->validRun(['material_usages' => [
                ['material_id' => $this->materialId, 'quantity_kg' => '10'],
                ['material_id' => $this->materialId, 'quantity_kg' => '5'],
            ]])),
            'This material is already used in this run.'
        );

        $this->assertSame([0, '100.000'], [$this->countRows('production_runs', 'machine_id', $this->machineId), $this->stockOf($this->materialId)]);
    }

    /* ---------- read ---------- */

    public function testGetByIdIncludesTheValuesAndUsages(): void
    {
        $id = $this->service->add($this->validRun())['id'];

        $run = $this->service->getById($id);

        $this->assertSame([1, 1], [count($run['parameter_values']), count($run['material_usages'])]);
    }

    // The list page shows one line per run: nesting every run's children would make it grow for nothing.
    public function testGetAllStaysFlat(): void
    {
        $this->service->add($this->validRun());

        $this->assertArrayNotHasKey('material_usages', $this->service->getAll()[0]);
    }

    public function testGetByIdOfAnUnknownRunIsNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Production run not found.');

        $this->service->getById(999999);
    }

    /* ---------- update ---------- */

    public function testUpdateChangesTheTimesAndAmounts(): void
    {
        $id = $this->service->add($this->validRun())['id'];

        $run = $this->service->update(['ended_at' => '2026-10-20T15:00', 'quantity_produced' => '900', 'notes' => 'Longer run'], $id);

        $this->assertSame(['2026-10-20 15:00:00', '900.000', 'Longer run'], [$run['ended_at'], $run['quantity_produced'], $run['notes']]);
    }

    public function testUpdateKeepsTheFieldsThatAreLeftOut(): void
    {
        $id = $this->service->add($this->validRun())['id'];

        $run = $this->service->update(['notes' => 'Only a note'], $id);

        $this->assertSame(['2026-10-20 05:30:00', '870.000', '42.000'], [$run['warmup_started_at'], $run['quantity_produced'], $run['scrap_kg']]);
    }

    // Identity fields are locked: everything else (parameters, product links, recipe) depends on them.
    public function testUpdateIgnoresTheMachineProductRecipeAndUser(): void
    {
        $id = $this->service->add($this->validRun())['id'];
        $otherIds = ['machine_id' => $this->createMachine('extrusion'), 'product_id' => $this->createProduct(),
            'recipe_id' => $this->createRecipe(), 'user_id' => $this->createUser()];

        $run = $this->service->update($otherIds, $id);

        $this->assertSame(
            [$this->machineId, $this->productId, $this->recipeId, $this->userId],
            [$run['machine_id'], $run['product_id'], $run['recipe_id'], $run['user_id']]
        );
    }

    public function testUpdateChecksTheTimeOrderAgainstTheSavedTimes(): void
    {
        $id = $this->service->add($this->validRun())['id'];

        $this->assertRejected(fn () => $this->service->update(['started_at' => '2026-10-20 15:00:00'], $id), 'The run must end after it starts.');
    }

    public function testUpdateRequiresAQuantityWhenOneIsSent(): void
    {
        $id = $this->service->add($this->validRun())['id'];

        $this->assertRejected(fn () => $this->service->update(['quantity_produced' => ''], $id), "Field 'quantity_produced' is required.");
    }

    // A run is history: deactivating its machine later must not lock it against corrections.
    public function testUpdateIsAllowedAfterTheMachineIsDeactivated(): void
    {
        $id = $this->service->add($this->validRun())['id'];
        $this->deactivate('machines', $this->machineId);

        $this->assertSame('Corrected', $this->service->update(['notes' => 'Corrected'], $id)['notes']);
    }

    public function testUpdateOfAnUnknownRunIsNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Production run not found.');

        $this->service->update(['notes' => 'x'], 999999);
    }

    /* ---------- delete ---------- */

    public function testDeleteGivesTheStockBackAndRemovesTheValuesAndUsages(): void
    {
        $id = $this->service->add($this->validRun())['id'];

        $this->service->delete($id);

        $this->assertSame(
            ['100.000', 0, 0],
            [$this->stockOf($this->materialId), $this->countRows('run_parameter_values', 'production_run_id', $id),
                $this->countRows('material_usages', 'production_run_id', $id)]
        );
    }

    public function testDeleteOfAnUnknownRunIsNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Production run not found.');

        $this->service->delete(999999);
    }
}
