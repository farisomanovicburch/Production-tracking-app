<?php
require_once __DIR__ . '/BaseDao.php';

class ConversionRunDao extends BaseDao
{
    // Scrap is calculated here, never stored, so it cannot disagree with input and output.
    // products is joined twice under two aliases: once for the film that went in, once for the bags.
    private const SELECT = '
        SELECT cr.*,
               (cr.input_kg - cr.output_kg) AS scrap_kg,
               m.name      AS machine_name,
               u.full_name AS user_name,
               ip.name     AS input_product_name,
               op.name     AS output_product_name
        FROM conversion_runs cr
        JOIN machines m  ON m.id = cr.machine_id
        JOIN users u     ON u.id = cr.user_id
        JOIN products ip ON ip.id = cr.input_product_id
        JOIN products op ON op.id = cr.output_product_id';

    public function __construct()
    {
        parent::__construct('conversion_runs', [
            'machine_id', 'user_id', 'input_product_id', 'output_product_id',
            'started_at', 'ended_at', 'input_kg', 'output_kg', 'bag_count', 'notes',
        ]);
    }

    public function getAll()
    {
        return $this->query(self::SELECT . ' ORDER BY cr.started_at DESC, cr.id DESC');
    }

    public function getById($id)
    {
        return $this->queryUnique(self::SELECT . ' WHERE cr.id = :id', ['id' => $id]);
    }
}
