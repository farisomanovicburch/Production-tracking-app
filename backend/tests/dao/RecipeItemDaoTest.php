<?php
require_once __DIR__ . '/../DatabaseTestCase.php';
require_once __DIR__ . '/../../rest/dao/RecipeItemDao.php';

final class RecipeItemDaoTest extends DatabaseTestCase
{
    public function testGetByRecipeReturnsOnlyThatRecipesItemsWithMaterialNamesLargestShareFirst(): void
    {
        $recipeId = $this->insertRow('recipes', ['name' => 'Test recipe']);
        $otherRecipeId = $this->insertRow('recipes', ['name' => 'Other recipe']);
        $smallId = $this->insertRow('materials', ['name' => 'Test additive']);
        $largeId = $this->insertRow('materials', ['name' => 'Test granulate']);
        $this->insertRow('recipe_items', ['recipe_id' => $recipeId, 'material_id' => $smallId, 'percentage' => 30]);
        $this->insertRow('recipe_items', ['recipe_id' => $recipeId, 'material_id' => $largeId, 'percentage' => 70]);
        $this->insertRow('recipe_items', ['recipe_id' => $otherRecipeId, 'material_id' => $largeId, 'percentage' => 100]);

        $items = (new RecipeItemDao())->getByRecipe($recipeId);

        $this->assertSame(
            [['Test granulate', '70.00'], ['Test additive', '30.00']],
            array_map(fn ($item) => [$item['material_name'], $item['percentage']], $items)
        );
    }
}
