<?php
require_once __DIR__ . '/BaseDao.php';

class RecipeProductDao extends BaseDao
{
    public function __construct()
    {
        parent::__construct('recipe_products', ['recipe_id', 'product_id', 'is_default']);
    }
}
