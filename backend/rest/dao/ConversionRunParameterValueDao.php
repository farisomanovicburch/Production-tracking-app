<?php
require_once __DIR__ . '/BaseDao.php';

class ConversionRunParameterValueDao extends BaseDao
{
    public function __construct()
    {
        parent::__construct('conversion_run_parameter_values', ['conversion_run_id', 'machine_parameter_id', 'value']);
    }

    public function getByConversionRun($conversionRunId)
    {
        return $this->query(
            'SELECT crpv.*, mp.name AS parameter_name, mp.unit AS parameter_unit
             FROM conversion_run_parameter_values crpv
             JOIN machine_parameters mp ON mp.id = crpv.machine_parameter_id
             WHERE crpv.conversion_run_id = :conversion_run_id
             ORDER BY mp.display_order, mp.id',
            ['conversion_run_id' => $conversionRunId]
        );
    }
}
