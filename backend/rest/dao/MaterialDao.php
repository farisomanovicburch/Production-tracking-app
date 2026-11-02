<?php
require_once __DIR__ . '/BaseDao.php';

class MaterialDao extends BaseDao
{
    public function __construct()
    {
        parent::__construct('materials', ['name', 'unit', 'supplier', 'stock_qty', 'min_stock_qty', 'is_active']);
    }
}
