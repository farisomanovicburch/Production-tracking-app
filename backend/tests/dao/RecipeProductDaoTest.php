<?php
require_once __DIR__ . '/../DatabaseTestCase.php';
require_once __DIR__ . '/../../rest/dao/RecipeProductDao.php';

final class RecipeProductDaoTest extends DatabaseTestCase
{
    private RecipeProductDao $dao;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dao = new RecipeProductDao();
    }

    public function testGetLinkFindsTheRecipeLinkedToTheProduct(): void
    {
        $recipeId = $this->createRecipe();
        $productId = $this->createProduct();
        $linkId = $this->insertRow('recipe_products', ['recipe_id' => $recipeId, 'product_id' => $productId]);

        $this->assertSame($linkId, $this->dao->getLink($recipeId, $productId)['id']);
    }

    public function testGetLinkReturnsNullWhenTheRecipeIsLinkedOnlyToAnotherProduct(): void
    {
        $recipeId = $this->createRecipe();
        $productId = $this->createProduct();
        $this->insertRow('recipe_products', ['recipe_id' => $recipeId, 'product_id' => $this->createProduct()]);

        $this->assertNull($this->dao->getLink($recipeId, $productId));
    }

    public function testGetDefaultForProductReturnsTheDefaultLink(): void
    {
        $productId = $this->createProduct();
        $this->insertRow('recipe_products', ['recipe_id' => $this->createRecipe(), 'product_id' => $productId, 'is_default' => 0]);
        $defaultLinkId = $this->insertRow('recipe_products', ['recipe_id' => $this->createRecipe(), 'product_id' => $productId, 'is_default' => 1]);

        $this->assertSame($defaultLinkId, $this->dao->getDefaultForProduct($productId)['id']);
    }

    public function testGetDefaultForProductReturnsNullWhenNoLinkIsTheDefault(): void
    {
        $productId = $this->createProduct();
        $this->insertRow('recipe_products', ['recipe_id' => $this->createRecipe(), 'product_id' => $productId, 'is_default' => 0]);

        $this->assertNull($this->dao->getDefaultForProduct($productId));
    }
}
