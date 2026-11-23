<?php
require_once __DIR__ . '/../DatabaseTestCase.php';
require_once __DIR__ . '/../../rest/services/RecipeService.php';

final class RecipeServiceTest extends DatabaseTestCase
{
    private const NAME = 'Test recipe';

    private RecipeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RecipeService();
    }

    /* ---------- add ---------- */

    public function testANewRecipeIsActiveUnlessToldOtherwise(): void
    {
        $this->assertSame(1, $this->service->add(['name' => self::NAME])['is_active']);
    }

    public function testAddStoresTheNameTrimmedAndEmptyNotesAsNull(): void
    {
        $recipe = $this->service->add(['name' => '  Test recipe ', 'notes' => ' ']);

        $this->assertSame([self::NAME, null], [$recipe['name'], $recipe['notes']]);
    }

    public function testAddRequiresAName(): void
    {
        $this->assertRejected(fn () => $this->service->add(['notes' => 'No name']), "Field 'name' is required.");
    }

    public function testAddRejectsTheNameOfAnotherRecipeInAnyCase(): void
    {
        $this->assertRejected(fn () => $this->service->add(['name' => 'clear film']), 'A recipe with this name already exists.');
    }

    /* ---------- update ---------- */

    public function testUpdateLetsARecipeKeepItsOwnName(): void
    {
        $id = $this->service->add(['name' => self::NAME])['id'];

        $this->assertSame('Mix well.', $this->service->update(['name' => self::NAME, 'notes' => 'Mix well.'], $id)['notes']);
    }

    public function testUpdateRejectsTheNameOfAnotherRecipe(): void
    {
        $id = $this->service->add(['name' => self::NAME])['id'];

        $this->assertRejected(fn () => $this->service->update(['name' => 'Clear film'], $id), 'A recipe with this name already exists.');
    }

    public function testUpdateCanDeactivateARecipe(): void
    {
        $id = $this->service->add(['name' => self::NAME])['id'];

        $this->assertSame(0, $this->service->update(['name' => self::NAME, 'is_active' => false], $id)['is_active']);
    }

    /* ---------- delete ---------- */

    // Items and product links are part of the recipe, not history: they go with it (ON DELETE CASCADE).
    public function testDeleteOfAnUnusedRecipeAlsoRemovesItsItemsAndProductLinks(): void
    {
        $recipeId = $this->createRecipe();
        $this->insertRow('recipe_items', ['recipe_id' => $recipeId, 'material_id' => $this->createMaterial(), 'percentage' => 100]);
        $this->insertRow('recipe_products', ['recipe_id' => $recipeId, 'product_id' => $this->createProduct()]);

        $this->service->delete($recipeId);

        $this->assertSame([0, 0, 0], [
            $this->countRows('recipes', 'id', $recipeId),
            $this->countRows('recipe_items', 'recipe_id', $recipeId),
            $this->countRows('recipe_products', 'recipe_id', $recipeId),
        ]);
    }

    public function testDeleteOfARecipeWithRunsAsksToDeactivateInstead(): void
    {
        $recipeId = $this->createRecipe();
        $this->createRun($this->createMachine('extrusion'), ['recipe_id' => $recipeId]);

        $this->assertRejected(
            fn () => $this->service->delete($recipeId),
            'This recipe has production runs and cannot be deleted. Deactivate it instead.'
        );
    }
}
