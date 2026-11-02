<?php
require_once __DIR__ . '/BaseDao.php';

class ProductionRunDao extends BaseDao
{
    // Run columns plus the names the list and details pages show, in one query instead of one request per id.
    private const SELECT = '
        SELECT pr.*,
               m.name      AS machine_name,
               p.name      AS product_name,
               p.unit      AS product_unit,
               r.name      AS recipe_name,
               u.full_name AS user_name
        FROM production_runs pr
        JOIN machines m ON m.id = pr.machine_id
        JOIN products p ON p.id = pr.product_id
        JOIN recipes r  ON r.id = pr.recipe_id
        JOIN users u    ON u.id = pr.user_id';

    public function __construct()
    {
        parent::__construct('production_runs', [
            'machine_id', 'product_id', 'recipe_id', 'user_id',
            'warmup_started_at', 'started_at', 'stable_at', 'ended_at',
            'quantity_produced', 'net_weight_per_unit_kg', 'gross_weight_per_unit_kg', 'scrap_kg', 'notes',
        ]);
    }

    public function getAll()
    {
        return $this->query(self::SELECT . ' ORDER BY pr.started_at DESC, pr.id DESC');
    }

    public function getById($id)
    {
        return $this->queryUnique(self::SELECT . ' WHERE pr.id = :id', ['id' => $id]);
    }
}
