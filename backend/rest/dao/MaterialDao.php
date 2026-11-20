<?php
require_once __DIR__ . '/BaseDao.php';

class MaterialDao extends BaseDao
{
    public function __construct()
    {
        parent::__construct('materials', ['name', 'unit', 'supplier', 'stock_qty', 'min_stock_qty', 'is_active']);
    }

    // Lets the service answer a duplicate name with a 400 before the UNIQUE index answers with a 500.
    public function getByName($name)
    {
        return $this->queryUnique('SELECT * FROM materials WHERE name = :name', ['name' => $name]);
    }

    // The new stock is computed inside MySQL in one statement, so two runs saved at the same moment cannot both
    // read 10 kg and overwrite each other's result. CAST keeps the sum exact DECIMAL instead of a floating-point
    // number. Going below zero breaks chk_materials_stock_not_negative and throws a PDOException.
    public function changeStock($id, $amountKg)
    {
        return $this->execute(
            'UPDATE materials SET stock_qty = stock_qty + CAST(:amount AS DECIMAL(12,3)) WHERE id = :id',
            ['amount' => $amountKg, 'id' => $id]
        );
    }
}
