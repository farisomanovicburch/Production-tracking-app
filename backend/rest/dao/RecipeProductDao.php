<?php
require_once __DIR__ . '/BaseDao.php';

class RecipeProductDao extends BaseDao
{
    public function __construct()
    {
        parent::__construct('recipe_products', ['recipe_id', 'product_id', 'is_default']);
    }

    public function getLink($recipeId, $productId)
    {
        return $this->queryUnique(
            'SELECT * FROM recipe_products WHERE recipe_id = :recipe_id AND product_id = :product_id',
            ['recipe_id' => $recipeId, 'product_id' => $productId]
        );
    }

    // The database cannot limit a product to one default recipe, so the service asks for the current one first.
    public function getDefaultForProduct($productId)
    {
        return $this->queryUnique(
            'SELECT * FROM recipe_products WHERE product_id = :product_id AND is_default = TRUE ORDER BY id',
            ['product_id' => $productId]
        );
    }
}
