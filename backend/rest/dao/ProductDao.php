<?php
require_once __DIR__ . '/BaseDao.php';

class ProductDao extends BaseDao
{
    public function __construct()
    {
        parent::__construct('products', [
            'code', 'name', 'width_mm', 'thickness_um', 'length_m', 'unit', 'description', 'is_active',
        ]);
    }
}
