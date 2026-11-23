<?php
require_once __DIR__ . '/../DatabaseTestCase.php';
require_once __DIR__ . '/../../rest/dao/RecipeDao.php';

final class RecipeDaoTest extends DatabaseTestCase
{
    // Same collation as the UNIQUE index: "clear film" and "Clear film" are one name.
    public function testGetByNameFindsTheRecipeInAnyCase(): void
    {
        $recipeId = $this->createRecipe('Test recipe');

        $this->assertSame($recipeId, (new RecipeDao())->getByName('TEST RECIPE')['id']);
    }

    public function testGetByNameReturnsNullForAnUnknownName(): void
    {
        $this->assertNull((new RecipeDao())->getByName('No such recipe'));
    }
}
