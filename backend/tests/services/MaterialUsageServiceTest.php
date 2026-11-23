<?php
require_once __DIR__ . '/../DatabaseTestCase.php';
require_once __DIR__ . '/../../rest/services/MaterialUsageService.php';

// Usages added to or corrected on a run that already exists, and the stock movements they cause. The usages sent
// with a new run are tested in ProductionRunServiceTest.
final class MaterialUsageServiceTest extends DatabaseTestCase
{
    private MaterialUsageService $service;
    private int $runId;
    private int $recipeId;
    private int $materialId;

    // A run whose recipe is 100 % one material with 100 kg in stock.
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MaterialUsageService();
        $this->runId = $this->createRun($this->createMachine('extrusion'));

        $statement = $this->db->prepare('SELECT recipe_id FROM production_runs WHERE id = :id');
        $statement->execute(['id' => $this->runId]);
        $this->recipeId = (int) $statement->fetchColumn();

        $this->materialId = $this->createStockedMaterial('Test granulate', 100);
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

    private function addUsage($quantityKg = '30', ?int $materialId = null): array
    {
        return $this->service->addToRun($this->runId, ['material_id' => $materialId ?? $this->materialId, 'quantity_kg' => $quantityKg]);
    }

    private function assertNotFound(callable $action, string $message): void
    {
        try {
            $action();
            $this->fail("Accepted, expected: $message");
        } catch (NotFoundException $exception) {
            $this->assertSame($message, $exception->getMessage());
        }
    }

    /* ---------- list ---------- */

    public function testGetByRunListsTheUsagesWithMaterialNames(): void
    {
        $this->addUsage();

        $this->assertSame(['Test granulate'], array_column($this->service->getByRun($this->runId), 'material_name'));
    }

    public function testGetByRunOfAnUnknownRunIsNotFound(): void
    {
        $this->assertNotFound(fn () => $this->service->getByRun(999999), 'Production run not found.');
    }

    /* ---------- add ---------- */

    public function testAddToRunSavesTheUsageAndTakesItFromStock(): void
    {
        $usage = $this->addUsage('30');

        $this->assertSame([$this->runId, '30.000', '70.000'], [$usage['production_run_id'], $usage['quantity_kg'], $this->stockOf($this->materialId)]);
    }

    public function testAddToAnUnknownRunIsNotFound(): void
    {
        $this->assertNotFound(
            fn () => $this->service->addToRun(999999, ['material_id' => $this->materialId, 'quantity_kg' => 1]),
            'Production run not found.'
        );
    }

    public function testAddToRunRequiresMoreThanZero(): void
    {
        $this->assertRejected(fn () => $this->addUsage('0'), "Field 'quantity_kg' must be at least 0.001.");
    }

    public function testAddToRunRejectsAnUnknownMaterial(): void
    {
        $this->assertRejected(fn () => $this->addUsage('1', 999999), 'The selected material does not exist.');
    }

    public function testAddToRunRejectsAMaterialThatIsNotInTheRecipe(): void
    {
        $this->assertRejected(
            fn () => $this->addUsage('1', $this->createStockedMaterial('Not in recipe', 50)),
            "The selected material is not in this run's recipe."
        );
    }

    public function testAddToRunRejectsAMaterialThatIsAlreadyUsed(): void
    {
        $this->addUsage('10');

        $this->assertRejected(fn () => $this->addUsage('5'), 'This material is already used in this run.');
    }

    public function testAddToRunWithNotEnoughStockSavesNothing(): void
    {
        $this->assertRejected(fn () => $this->addUsage('100.001'), 'Not enough Test granulate in stock: 100.000 kg left, 100.001 kg needed.');

        $this->assertSame([0, '100.000'], [$this->countRows('material_usages', 'production_run_id', $this->runId), $this->stockOf($this->materialId)]);
    }

    // Adding to an existing run corrects history, so a material deactivated since then is still accepted.
    public function testAddToRunAcceptsADeactivatedMaterial(): void
    {
        $this->db->prepare('UPDATE materials SET is_active = FALSE WHERE id = :id')->execute(['id' => $this->materialId]);

        $this->assertSame('30.000', $this->addUsage('30')['quantity_kg']);
    }

    public function testTheInheritedAddIsAMistakeInTheCode(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Use addToRun(): the run comes from the URL.');

        $this->service->add(['production_run_id' => $this->runId, 'material_id' => $this->materialId, 'quantity_kg' => 1]);
    }

    /* ---------- update: the stock moves by the difference ---------- */

    public function testUpdateToMoreTakesOnlyTheDifferenceFromStock(): void
    {
        $id = $this->addUsage('30')['id'];

        $usage = $this->service->update(['quantity_kg' => '50'], $id);

        $this->assertSame(['50.000', '50.000'], [$usage['quantity_kg'], $this->stockOf($this->materialId)]);
    }

    public function testUpdateToLessGivesTheDifferenceBack(): void
    {
        $id = $this->addUsage('30')['id'];

        $this->service->update(['quantity_kg' => '10'], $id);

        $this->assertSame('90.000', $this->stockOf($this->materialId));
    }

    // 70 kg are left after the first 30; going to 120 needs 90 more.
    public function testUpdateBeyondTheStockChangesNothing(): void
    {
        $id = $this->addUsage('30')['id'];

        $this->assertRejected(
            fn () => $this->service->update(['quantity_kg' => '120'], $id),
            'Not enough Test granulate in stock: 70.000 kg left, 90.000 kg needed.'
        );

        $this->assertSame(['30.000', '70.000'], [$this->service->getById($id)['quantity_kg'], $this->stockOf($this->materialId)]);
    }

    // Moving a usage to another material would have to move two stocks: delete it and add another instead.
    public function testUpdateIgnoresAnotherMaterial(): void
    {
        $id = $this->addUsage('30')['id'];
        $otherMaterialId = $this->createStockedMaterial('Other granulate', 50);

        $usage = $this->service->update(['material_id' => $otherMaterialId, 'quantity_kg' => '30'], $id);

        $this->assertSame([$this->materialId, '50.000'], [$usage['material_id'], $this->stockOf($otherMaterialId)]);
    }

    public function testUpdateRequiresMoreThanZero(): void
    {
        $id = $this->addUsage('30')['id'];

        $this->assertRejected(fn () => $this->service->update(['quantity_kg' => '-5'], $id), "Field 'quantity_kg' must be at least 0.001.");
    }

    public function testUpdateOfAnUnknownUsageIsNotFound(): void
    {
        $this->assertNotFound(fn () => $this->service->update(['quantity_kg' => '1'], 999999), 'Material usage not found.');
    }

    /* ---------- delete ---------- */

    public function testDeleteGivesTheStockBack(): void
    {
        $id = $this->addUsage('30')['id'];

        $this->service->delete($id);

        $this->assertSame([0, '100.000'], [$this->countRows('material_usages', 'id', $id), $this->stockOf($this->materialId)]);
    }

    public function testDeleteOfAnUnknownUsageIsNotFound(): void
    {
        $this->assertNotFound(fn () => $this->service->delete(999999), 'Material usage not found.');
    }
}
