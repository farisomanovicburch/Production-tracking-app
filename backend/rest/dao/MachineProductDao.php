<?php
require_once __DIR__ . '/BaseDao.php';

class MachineProductDao extends BaseDao
{
    public function __construct()
    {
        parent::__construct('machine_products', ['machine_id', 'product_id', 'role']);
    }

    // Product names come with the links (JOIN), so the setup page needs one request, not one per product.
    public function getByMachine($machineId)
    {
        return $this->query(
            'SELECT mp.*, p.code AS product_code, p.name AS product_name
             FROM machine_products mp
             JOIN products p ON p.id = mp.product_id
             WHERE mp.machine_id = :machine_id
             ORDER BY mp.role, p.name',
            ['machine_id' => $machineId]
        );
    }
}
