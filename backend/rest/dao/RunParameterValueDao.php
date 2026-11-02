<?php
require_once __DIR__ . '/BaseDao.php';

class RunParameterValueDao extends BaseDao
{
    public function __construct()
    {
        parent::__construct('run_parameter_values', ['production_run_id', 'machine_parameter_id', 'value']);
    }

    public function getByRun($runId)
    {
        return $this->query(
            'SELECT rpv.*, mp.name AS parameter_name, mp.unit AS parameter_unit
             FROM run_parameter_values rpv
             JOIN machine_parameters mp ON mp.id = rpv.machine_parameter_id
             WHERE rpv.production_run_id = :run_id
             ORDER BY mp.display_order, mp.id',
            ['run_id' => $runId]
        );
    }
}
