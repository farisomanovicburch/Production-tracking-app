<?php
require_once __DIR__ . '/BaseDao.php';

class MaterialUsageDao extends BaseDao
{
    public function __construct()
    {
        parent::__construct('material_usages', ['production_run_id', 'material_id', 'quantity_kg']);
    }

    public function getByRun($runId)
    {
        return $this->query(
            'SELECT mu.*, m.name AS material_name
             FROM material_usages mu
             JOIN materials m ON m.id = mu.material_id
             WHERE mu.production_run_id = :run_id
             ORDER BY mu.quantity_kg DESC, m.name',
            ['run_id' => $runId]
        );
    }
}
