<?php
require_once __DIR__ . '/../DatabaseTestCase.php';
require_once __DIR__ . '/../../rest/services/RecipeItemService.php';

final class RecipeItemServiceTest extends DatabaseTestCase
{
    private RecipeItemService $service;
    private int $recipeId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RecipeItemService();
        $this->recipeId = $this->createRecipe();
    }

    private function addItem($percentage, ?int $materialId = null, ?int $recipeId = null): array
    {
        return $this->service->addToRecipe(
            $recipeId ?? $this->recipeId,
            ['material_id' => $materialId ?? $this->createMaterial(), 'percentage' => $percentage]
        );
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

    public function testGetByRecipeListsTheItemsWithMaterialNames(): void
    {
        $this->addItem('100', $this->createMaterial('Test LDPE'));

        $this->assertSame(['Test LDPE'], array_column($this->service->getByRecipe($this->recipeId), 'material_name'));
    }

    public function testGetByRecipeOfAnUnknownRecipeIsNotFound(): void
    {
        $this->assertNotFound(fn () => $this->service->getByRecipe(999999), 'Recipe not found.');
    }

    /* ---------- add ---------- */

    public function testAddToRecipeSavesTheItemOnThatRecipe(): void
    {
        $item = $this->addItem('99.5');

        $this->assertSame([$this->recipeId, '99.50'], [$item['recipe_id'], $item['percentage']]);
    }

    public function testAddToRecipeIgnoresARecipeIdInTheBody(): void
    {
        $item = $this->service->addToRecipe(
            $this->recipeId,
            ['material_id' => $this->createMaterial(), 'percentage' => 10, 'recipe_id' => $this->createRecipe()]
        );

        $this->assertSame($this->recipeId, $item['recipe_id']);
    }

    public function testAddToAnUnknownRecipeIsNotFound(): void
    {
        $this->assertNotFound(fn () => $this->addItem(10, null, 999999), 'Recipe not found.');
    }

    public function testAddRequiresAPercentage(): void
    {
        $this->assertRejected(
            fn () => $this->service->addToRecipe($this->recipeId, ['material_id' => $this->createMaterial()]),
            "Field 'percentage' is required."
        );
    }

    // chk_recipe_items_percentage would refuse these too, but as a PDOException: a 500 for the user.
    public function testAddRejectsAZeroPercentage(): void
    {
        $this->assertRejected(fn () => $this->addItem('0'), "Field 'percentage' must be at least 0.01.");
    }

    public function testAddRejectsMoreThanAHundredPercentForOneMaterial(): void
    {
        $this->assertRejected(fn () => $this->addItem('100.01'), "Field 'percentage' must be at most 100.");
    }

    public function testAddRejectsAMaterialIdThatIsNotAWholeNumber(): void
    {
        $this->assertRejected(
            fn () => $this->service->addToRecipe($this->recipeId, ['material_id' => 'LDPE', 'percentage' => 10]),
            "Field 'material_id' must be a whole number."
        );
    }

    public function testAddRejectsAMaterialThatDoesNotExist(): void
    {
        $this->assertRejected(fn () => $this->addItem(10, 999999), 'The selected material does not exist.');
    }

    public function testAddRejectsAMaterialThatIsAlreadyInTheRecipe(): void
    {
        $materialId = $this->createMaterial();
        $this->addItem(10, $materialId);

        $this->assertRejected(fn () => $this->addItem(5, $materialId), 'This material is already in the recipe.');
    }

    /* ---------- the 100 % rule ---------- */

    public function testItemsMayAddUpToExactlyAHundredPercent(): void
    {
        $this->addItem('60');

        $this->assertSame('40.00', $this->addItem('40')['percentage']);
    }

    public function testItemsMayNotAddUpToMoreThanAHundredPercent(): void
    {
        $this->addItem('60');

        $this->assertRejected(
            fn () => $this->addItem('40.01'),
            'The materials of this recipe would add up to 100.01 %. They may not add up to more than 100 %.'
        );
    }

    // In floating point 90.2 + 0.9 + 8.9 is 100.00000000000001, so a float comparison would refuse a correct recipe.
    public function testSharesThatAddUpToExactlyAHundredAreAcceptedDespiteFloatingPoint(): void
    {
        $this->addItem('90.2');
        $this->addItem('0.9');

        $this->assertSame('8.90', $this->addItem('8.9')['percentage']);
    }

    // Each recipe has its own 100 %.
    public function testItemsOfOtherRecipesDoNotCount(): void
    {
        $this->addItem('100', null, $this->createRecipe());

        $this->assertSame('100.00', $this->addItem('100')['percentage']);
    }

    /* ---------- update ---------- */

    // The item's old percentage is replaced, not added: 60 + 40, then 40 -> 40, is still 100.
    public function testUpdateCountsTheNewPercentageInsteadOfTheOldOne(): void
    {
        $this->addItem('60');
        $item = $this->addItem('40');

        $updated = $this->service->update(['material_id' => $item['material_id'], 'percentage' => '40'], $item['id']);

        $this->assertSame('40.00', $updated['percentage']);
    }

    public function testUpdateMayNotPushTheRecipeOverAHundredPercent(): void
    {
        $this->addItem('60');
        $item = $this->addItem('40');

        $this->assertRejected(
            fn () => $this->service->update(['material_id' => $item['material_id'], 'percentage' => '41'], $item['id']),
            'The materials of this recipe would add up to 101.00 %. They may not add up to more than 100 %.'
        );
    }

    public function testUpdateMayChangeTheMaterialButNotToOneAlreadyInTheRecipe(): void
    {
        $takenMaterialId = $this->createMaterial();
        $this->addItem('10', $takenMaterialId);
        $item = $this->addItem('20');

        $this->assertRejected(
            fn () => $this->service->update(['material_id' => $takenMaterialId, 'percentage' => '20'], $item['id']),
            'This material is already in the recipe.'
        );
        $newMaterialId = $this->createMaterial();
        $this->assertSame($newMaterialId, $this->service->update(['material_id' => $newMaterialId, 'percentage' => '20'], $item['id'])['material_id']);
    }

    public function testUpdateCannotMoveAnItemToAnotherRecipe(): void
    {
        $item = $this->addItem('20');

        $updated = $this->service->update(
            ['material_id' => $item['material_id'], 'percentage' => '20', 'recipe_id' => $this->createRecipe()],
            $item['id']
        );

        $this->assertSame($this->recipeId, $updated['recipe_id']);
    }

    public function testUpdateOfAnUnknownItemIsNotFound(): void
    {
        $this->assertNotFound(
            fn () => $this->service->update(['material_id' => $this->createMaterial(), 'percentage' => '20'], 999999),
            'Recipe item not found.'
        );
    }

    /* ---------- delete and the inherited add() ---------- */

    public function testDeleteRemovesTheItemButNotTheMaterial(): void
    {
        $item = $this->addItem('20');

        $this->service->delete($item['id']);

        $this->assertSame([0, 1], [$this->countRows('recipe_items', 'id', $item['id']), $this->countRows('materials', 'id', $item['material_id'])]);
    }

    public function testAddWithoutTheRecipeFromTheUrlIsAProgrammingError(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Use addToRecipe(): the recipe comes from the URL.');

        $this->service->add(['recipe_id' => $this->recipeId, 'material_id' => $this->createMaterial(), 'percentage' => 10]);
    }
}
