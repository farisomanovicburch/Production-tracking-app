<?php
require_once __DIR__ . '/BaseDao.php';

class RecipeItemDao extends BaseDao
{
    public function __construct()
    {
        parent::__construct('recipe_items', ['recipe_id', 'material_id', 'percentage']);
    }

    public function getByRecipe($recipeId)
    {
        return $this->query(
            'SELECT ri.*, m.name AS material_name
             FROM recipe_items ri
             JOIN materials m ON m.id = ri.material_id
             WHERE ri.recipe_id = :recipe_id
             ORDER BY ri.percentage DESC, m.name',
            ['recipe_id' => $recipeId]
        );
    }
}
