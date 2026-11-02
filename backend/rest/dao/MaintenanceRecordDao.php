<?php
require_once __DIR__ . '/BaseDao.php';

class MaintenanceRecordDao extends BaseDao
{
    private const SELECT = '
        SELECT mr.*, m.name AS machine_name, u.full_name AS user_name
        FROM maintenance_records mr
        JOIN machines m ON m.id = mr.machine_id
        JOIN users u    ON u.id = mr.user_id';

    public function __construct()
    {
        parent::__construct('maintenance_records', [
            'machine_id', 'user_id', 'maintenance_type', 'started_at', 'ended_at', 'description', 'notes',
        ]);
    }

    public function getAll()
    {
        return $this->query(self::SELECT . ' ORDER BY mr.started_at DESC, mr.id DESC');
    }

    public function getById($id)
    {
        return $this->queryUnique(self::SELECT . ' WHERE mr.id = :id', ['id' => $id]);
    }
}
