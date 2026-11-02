<?php
require_once __DIR__ . '/BaseDao.php';

class MachineDao extends BaseDao
{
    public function __construct()
    {
        parent::__construct('machines', ['name', 'code', 'machine_type', 'is_active']);
    }
}
