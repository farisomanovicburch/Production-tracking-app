<?php
require_once __DIR__ . '/BaseDao.php';

class MachineParameterDao extends BaseDao
{
    public function __construct()
    {
        parent::__construct('machine_parameters', ['machine_id', 'name', 'unit', 'display_order']);
    }

    // The machine setup page and the run form list a machine's parameters in the order the admin chose.
    public function getByMachine($machineId)
    {
        return $this->query(
            'SELECT * FROM machine_parameters WHERE machine_id = :machine_id ORDER BY display_order, id',
            ['machine_id' => $machineId]
        );
    }
}
