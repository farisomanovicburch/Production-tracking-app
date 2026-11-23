<?php
require_once __DIR__ . '/BaseDao.php';

class MachineDao extends BaseDao
{
    public function __construct()
    {
        parent::__construct('machines', ['name', 'code', 'machine_type', 'is_active']);
    }

    // The service checks the code here before saving, so a duplicate gets a readable 400 instead of the UNIQUE
    // index's 500. The comparison is MySQL's own, so it ignores case exactly like the index does.
    public function getByCode($code)
    {
        return $this->queryUnique('SELECT * FROM machines WHERE code = :code', ['code' => $code]);
    }
}
