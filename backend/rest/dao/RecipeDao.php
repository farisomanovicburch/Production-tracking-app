<?php
require_once __DIR__ . '/BaseDao.php';

class RecipeDao extends BaseDao
{
    public function __construct()
    {
        parent::__construct('recipes', ['name', 'notes', 'is_active']);
    }
}
