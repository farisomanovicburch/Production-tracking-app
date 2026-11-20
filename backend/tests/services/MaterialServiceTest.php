<?php
require_once __DIR__ . '/../DatabaseTestCase.php';
require_once __DIR__ . '/../../rest/services/MaterialService.php';

final class MaterialServiceTest extends DatabaseTestCase
{
    private const NAME = 'Test LDPE';

    private MaterialService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MaterialService();
    }

    private function addMaterial(array $overrides = []): array
    {
        return $this->service->add(array_merge(['name' => self::NAME], $overrides));
    }

    private function stockOf(int $id): string
    {
        return $this->service->getById($id)['stock_qty'];
    }

    /* ---------- add ---------- */

    public function testANewMaterialStartsWithTheGivenStock(): void
    {
        $this->assertSame('120.500', $this->addMaterial(['stock_qty' => '120.5'])['stock_qty']);
    }

    public function testANewMaterialWithoutStockStartsAtZeroInKg(): void
    {
        $material = $this->addMaterial();

        $this->assertSame(['0.000', 'kg', 1], [$material['stock_qty'], $material['unit'], $material['is_active']]);
    }

    // Both columns are NOT NULL with a default: an empty form field means "use the default", not NULL.
    public function testAnEmptyStockAndUnitMeanZeroAndKg(): void
    {
        $material = $this->addMaterial(['stock_qty' => '', 'unit' => ' ']);

        $this->assertSame(['0.000', 'kg'], [$material['stock_qty'], $material['unit']]);
    }

    public function testAddRejectsANegativeStock(): void
    {
        $this->assertRejected(fn () => $this->addMaterial(['stock_qty' => '-1']), "Field 'stock_qty' must be at least 0.");
    }

    public function testAddRequiresAName(): void
    {
        $this->assertRejected(fn () => $this->addMaterial(['name' => '  ']), "Field 'name' is required.");
    }

    public function testAddRejectsTheNameOfAnotherMaterialInAnyCase(): void
    {
        $this->assertRejected(fn () => $this->addMaterial(['name' => 'ldpe granulate']), 'A material with this name already exists.');
    }

    // NULL means "no low-stock warning for this material"; "" from an empty field would be refused by the column.
    public function testAddStoresAnEmptyMinimumAndSupplierAsNull(): void
    {
        $material = $this->addMaterial(['min_stock_qty' => '', 'supplier' => '']);

        $this->assertSame([null, null], [$material['min_stock_qty'], $material['supplier']]);
    }

    public function testAddRejectsANegativeMinimum(): void
    {
        $this->assertRejected(fn () => $this->addMaterial(['min_stock_qty' => '-5']), "Field 'min_stock_qty' must be at least 0.");
    }

    /* ---------- update ---------- */

    // Stock changes only through deliveries and runs, so every kilogram has a reason. An edit that could type in
    // a new number would hide a loss or a mistake.
    public function testUpdateIgnoresAStockSentByTheClient(): void
    {
        $id = $this->addMaterial(['stock_qty' => '10'])['id'];

        $this->service->update(['name' => self::NAME, 'stock_qty' => '9999'], $id);

        $this->assertSame('10.000', $this->stockOf($id));
    }

    public function testUpdateLetsAMaterialKeepItsOwnName(): void
    {
        $id = $this->addMaterial()['id'];

        $this->assertSame('Supplier X', $this->service->update(['name' => self::NAME, 'supplier' => 'Supplier X'], $id)['supplier']);
    }

    public function testUpdateRejectsTheNameOfAnotherMaterial(): void
    {
        $id = $this->addMaterial()['id'];

        $this->assertRejected(fn () => $this->service->update(['name' => 'LDPE granulate'], $id), 'A material with this name already exists.');
    }

    public function testUpdateCanDeactivateAMaterial(): void
    {
        $id = $this->addMaterial()['id'];

        $this->assertSame(0, $this->service->update(['name' => self::NAME, 'is_active' => false], $id)['is_active']);
    }

    /* ---------- delivery ---------- */

    public function testADeliveryAddsToTheStockAndReturnsTheMaterial(): void
    {
        $id = $this->addMaterial(['stock_qty' => '10'])['id'];

        $material = $this->service->addDelivery($id, ['quantity_kg' => '2.5']);

        $this->assertSame('12.500', $material['stock_qty']);
        $this->assertSame('12.500', $this->stockOf($id));
    }

    // A negative delivery would be a hidden stock correction, the thing the delivery route exists to prevent.
    public function testADeliveryMustBeMoreThanZero(): void
    {
        $id = $this->addMaterial(['stock_qty' => '10'])['id'];

        $this->assertRejected(fn () => $this->service->addDelivery($id, ['quantity_kg' => '0']), "Field 'quantity_kg' must be at least 0.001.");
        $this->assertSame('10.000', $this->stockOf($id));
    }

    public function testADeliveryRequiresAQuantity(): void
    {
        $id = $this->addMaterial()['id'];

        $this->assertRejected(fn () => $this->service->addDelivery($id, []), "Field 'quantity_kg' is required.");
    }

    public function testADeliveryOfAnUnknownMaterialIsNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Material not found.');

        $this->service->addDelivery(999999, ['quantity_kg' => '5']);
    }

    /* ---------- delete ---------- */

    public function testDeleteOfAMaterialInARecipeAsksToDeactivateInstead(): void
    {
        $materialId = $this->createMaterial();
        $this->insertRow('recipe_items', ['recipe_id' => $this->createRecipe(), 'material_id' => $materialId, 'percentage' => 100]);

        $this->assertRejected(
            fn () => $this->service->delete($materialId),
            'This material is used in recipes or production runs and cannot be deleted. Deactivate it instead.'
        );
    }
}
