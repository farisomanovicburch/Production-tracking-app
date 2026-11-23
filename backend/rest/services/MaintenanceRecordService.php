<?php
require_once __DIR__ . '/BaseService.php';
require_once __DIR__ . '/../dao/MaintenanceRecordDao.php';
require_once __DIR__ . '/../dao/MachineDao.php';
require_once __DIR__ . '/../dao/UserDao.php';

/*
 * A history log of work on the machines, always written after the work is done. Unlike a run, nothing hangs off
 * a record (no parameters, no products, no stock), so every field stays editable, machine and user included.
 */
class MaintenanceRecordService extends BaseService
{
    protected $entityName = 'Maintenance record';

    private const TYPES = ['preventive', 'corrective'];
    private const TIME_FIELDS = ['started_at', 'ended_at'];

    private $machineDao;
    private $userDao;

    public function __construct($dao = null, $machineDao = null, $userDao = null)
    {
        parent::__construct($dao ?? new MaintenanceRecordDao());
        $this->machineDao = $machineDao ?? new MachineDao();
        $this->userDao = $userDao ?? new UserDao();
    }

    public function add($record)
    {
        $record = $this->prepare($record);
        $this->validate($record);
        $this->validateLinks($record, null);
        $created = $this->dao->add($record);
        return $this->dao->getById($created['id']);
    }

    // A field left out keeps its saved value, so the rules are checked on the saved record with the changes applied.
    public function update($record, $id)
    {
        $existing = $this->getById($id);
        $record = $this->prepare($record);
        $merged = array_merge($existing, $record);
        $this->validate($merged, $id);
        $this->validateLinks($merged, $existing);
        $this->dao->update($record, $id);
        return $this->dao->getById($id);
    }

    protected function validate($record, $id = null)
    {
        Validator::required($record, ['machine_id', 'user_id', 'maintenance_type', 'started_at', 'ended_at', 'description']);
        Validator::integer($record['machine_id'], 'machine_id', 1);
        Validator::integer($record['user_id'], 'user_id', 1);
        Validator::oneOf($record['maintenance_type'], 'maintenance_type', self::TYPES);
        foreach (self::TIME_FIELDS as $field) {
            Validator::datetime($record[$field], $field);
        }
        Validator::length($record['description'], 'description', 1, 1000);
        if (isset($record['notes'])) {
            Validator::length($record['notes'], 'notes', 1, 1000);
        }

        // 'YYYY-MM-DD HH:MM:SS' text sorts in time order, so comparing the text compares the moments.
        if (strcmp($record['ended_at'], $record['started_at']) <= 0) {
            throw new ValidationException('The maintenance must end after it starts.');
        }
    }

    /*
     * The machine only has to exist: it is often out of service (deactivated) exactly because it is being repaired.
     * The user must be active only when they are newly put on the record; a record logged by someone who has left
     * since stays editable. $existing is null for a new record.
     */
    private function validateLinks($record, $existing)
    {
        if (!$this->machineDao->getById($record['machine_id'])) {
            throw new ValidationException('The selected machine does not exist.');
        }

        $user = $this->userDao->getById($record['user_id']);
        if (!$user) {
            throw new ValidationException('The selected user does not exist.');
        }
        $userIsNewOnRecord = $existing === null || (int) $existing['user_id'] !== (int) $record['user_id'];
        if ($userIsNewOnRecord && !$user['is_active']) {
            throw new ValidationException('The selected user is deactivated and cannot log new maintenance records.');
        }
    }

    private function prepare($record)
    {
        $record = $this->trimStrings($record, ['description', 'notes']);
        $record = $this->emptyToNull($record, ['notes']);
        return $this->normalizeDatetimes($record, self::TIME_FIELDS);
    }
}
