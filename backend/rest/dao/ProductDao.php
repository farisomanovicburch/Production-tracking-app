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

    // Lets the service answer a duplicate code with a 400 before the UNIQUE index answers with a 500.
    public function getByCode($code)
    {
        return $this->queryUnique('SELECT * FROM products WHERE code = :code', ['code' => $code]);
    }
}
