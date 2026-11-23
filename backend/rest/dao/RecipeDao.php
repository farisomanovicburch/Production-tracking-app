<?php
require_once __DIR__ . '/BaseDao.php';

class RecipeDao extends BaseDao
{
    public function __construct()
    {
        parent::__construct('recipes', ['name', 'notes', 'is_active']);
    }

    // Lets the service answer a duplicate name with a 400 before the UNIQUE index answers with a 500.
    public function getByName($name)
    {
        return $this->queryUnique('SELECT * FROM recipes WHERE name = :name', ['name' => $name]);
    }
}
