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

    // A run's product must be an output of its machine, a conversion's film an input: the role is part of the check.
    public function getLink($machineId, $productId, $role)
    {
        return $this->queryUnique(
            'SELECT * FROM machine_products WHERE machine_id = :machine_id AND product_id = :product_id AND role = :role',
            ['machine_id' => $machineId, 'product_id' => $productId, 'role' => $role]
        );
    }
}
