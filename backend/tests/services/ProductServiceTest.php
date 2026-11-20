<?php
require_once __DIR__ . '/../DatabaseTestCase.php';
require_once __DIR__ . '/../../rest/services/ProductService.php';

final class ProductServiceTest extends DatabaseTestCase
{
    private const CODE = 'T-P1';

    private ProductService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProductService();
    }

    private function newProduct(array $overrides = []): array
    {
        return array_merge(['code' => self::CODE, 'name' => 'Test film'], $overrides);
    }

    /* ---------- add ---------- */

    public function testANewProductIsActiveAndCountedInKgUnlessToldOtherwise(): void
    {
        $product = $this->service->add($this->newProduct());

        $this->assertSame([1, 'kg'], [$product['is_active'], $product['unit']]);
    }

    public function testAddStoresTheCodeAndNameTrimmed(): void
    {
        $product = $this->service->add($this->newProduct(['code' => ' T-P1 ', 'name' => ' Test film  ']));

        $this->assertSame([self::CODE, 'Test film'], [$product['code'], $product['name']]);
    }

    public function testAddRequiresAName(): void
    {
        $this->assertRejected(fn () => $this->service->add($this->newProduct(['name' => null])), "Field 'name' is required.");
    }

    public function testAddRejectsTheCodeOfAnotherProductInAnyCase(): void
    {
        $this->assertRejected(fn () => $this->service->add($this->newProduct(['code' => 'p-100'])), 'A product with this code already exists.');
    }

    public function testAddRejectsAnUnknownUnit(): void
    {
        $this->assertRejected(fn () => $this->service->add($this->newProduct(['unit' => 'm'])), "Field 'unit' must be one of: kg, pcs.");
    }

    // Empty form fields arrive as "", which an INT or DECIMAL column refuses: an unknown size is NULL.
    public function testAddStoresEmptyDimensionsAndDescriptionAsNull(): void
    {
        $product = $this->service->add($this->newProduct(['width_mm' => '', 'thickness_um' => '', 'length_m' => '', 'description' => ' ']));

        $this->assertSame([null, null, null, null], [$product['width_mm'], $product['thickness_um'], $product['length_m'], $product['description']]);
    }

    public function testAddStoresTheDimensions(): void
    {
        $product = $this->service->add($this->newProduct(['width_mm' => '300', 'thickness_um' => 40, 'length_m' => '0.5']));

        $this->assertSame([300, 40, '0.50'], [$product['width_mm'], $product['thickness_um'], $product['length_m']]);
    }

    // chk_products_dimensions_positive would refuse it too, but with a PDOException that the user sees as a 500.
    public function testAddRejectsAZeroWidth(): void
    {
        $this->assertRejected(fn () => $this->service->add($this->newProduct(['width_mm' => 0])), "Field 'width_mm' must be at least 1.");
    }

    public function testAddRejectsAWidthThatIsNotAWholeNumber(): void
    {
        $this->assertRejected(fn () => $this->service->add($this->newProduct(['width_mm' => '300.5'])), "Field 'width_mm' must be a whole number.");
    }

    public function testAddRejectsAZeroLength(): void
    {
        $this->assertRejected(fn () => $this->service->add($this->newProduct(['length_m' => '0'])), "Field 'length_m' must be at least 0.01.");
    }

    /* ---------- update ---------- */

    public function testUpdateLetsAProductKeepItsOwnCode(): void
    {
        $id = $this->service->add($this->newProduct())['id'];

        $this->assertSame('Renamed film', $this->service->update($this->newProduct(['name' => 'Renamed film']), $id)['name']);
    }

    public function testUpdateRejectsTheCodeOfAnotherProduct(): void
    {
        $id = $this->service->add($this->newProduct())['id'];

        $this->assertRejected(fn () => $this->service->update($this->newProduct(['code' => 'P-100']), $id), 'A product with this code already exists.');
    }

    public function testUpdateCanDeactivateAProductWithFalseFromJson(): void
    {
        $id = $this->service->add($this->newProduct())['id'];

        $this->assertSame(0, $this->service->update($this->newProduct(['is_active' => false]), $id)['is_active']);
    }

    // Clearing a field in the form must clear it in the database, not keep the old value.
    public function testUpdateCanClearADimension(): void
    {
        $id = $this->service->add($this->newProduct(['width_mm' => 300]))['id'];

        $this->assertNull($this->service->update($this->newProduct(['width_mm' => '']), $id)['width_mm']);
    }

    /* ---------- delete ---------- */

    public function testDeleteOfAProductWithRunsAsksToDeactivateInstead(): void
    {
        $productId = $this->createProduct();
        $this->createRun($this->createMachine('extrusion'), ['product_id' => $productId]);

        $this->assertRejected(
            fn () => $this->service->delete($productId),
            'This product has production runs or conversions and cannot be deleted. Deactivate it instead.'
        );
    }
}
