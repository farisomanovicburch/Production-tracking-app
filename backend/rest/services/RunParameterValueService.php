<?php
require_once __DIR__ . '/BaseService.php';
require_once __DIR__ . '/../dao/RunParameterValueDao.php';
require_once __DIR__ . '/../dao/ProductionRunDao.php';
require_once __DIR__ . '/../dao/MachineParameterDao.php';

/*
 * The machine settings recorded for a run (zone temperature, screw speed, ...). A value only makes sense for a
 * parameter of the run's own machine, and new values are only taken for parameters that are still in use.
 * ProductionRunService uses addToRun() for the values sent with a new run, so the rules live in one place.
 */
class RunParameterValueService extends BaseService
{
    protected $entityName = 'Run parameter value';

    // production_run_id comes from the URL; machine_parameter_id is fixed after adding (see update()).
    private const ACCEPTED_FIELDS = ['machine_parameter_id', 'value'];

    // The largest number DECIMAL(12,3) can hold, in both directions (a temperature could be below zero).
    private const MAX_VALUE = 999999999.999;

    private $runDao;
    private $machineParameterDao;

    // The run is looked up through its DAO, not ProductionRunService: that service creates this one, and two
    // services that create each other in their constructors would never finish.
    public function __construct($dao = null, $runDao = null, $machineParameterDao = null)
    {
        parent::__construct($dao ?? new RunParameterValueDao());
        $this->runDao = $runDao ?? new ProductionRunDao();
        $this->machineParameterDao = $machineParameterDao ?? new MachineParameterDao();
    }

    public function getByRun($runId)
    {
        $this->getRun($runId);
        return $this->dao->getByRun($runId);
    }

    public function addToRun($runId, $value)
    {
        $run = $this->getRun($runId);
        $value = array_intersect_key($value, array_flip(self::ACCEPTED_FIELDS));
        $this->validate($value);

        $parameter = $this->machineParameterDao->getById($value['machine_parameter_id']);
        if (!$parameter) {
            throw new ValidationException('The selected parameter does not exist.');
        }
        if ($parameter['machine_id'] != $run['machine_id']) {
            throw new ValidationException("The selected parameter does not belong to this run's machine.");
        }
        if (!$parameter['is_active']) {
            throw new ValidationException('The selected parameter is deactivated.');
        }
        $existing = array_column($this->dao->getByRun($runId), 'machine_parameter_id');
        if (in_array((int) $value['machine_parameter_id'], $existing, true)) {
            throw new ValidationException('This run already has a value for this parameter.');
        }

        $value['production_run_id'] = $runId;
        $created = $this->dao->add($value);
        return $this->dao->getById($created['id']);
    }

    // The inherited add() would skip the run check, the allow-list and the parameter rules.
    public function add($value)
    {
        throw new LogicException('Use addToRun(): the run comes from the URL.');
    }

    // Only the number can be corrected. A value for a different parameter is a different value: delete, add again.
    // No active check here: correcting a typo in an old run must work after its parameter was deactivated.
    public function update($value, $id)
    {
        return parent::update(array_intersect_key($value, ['value' => true]), $id);
    }

    protected function validate($value, $id = null)
    {
        $fields = $id === null ? ['machine_parameter_id', 'value'] : ['value'];
        Validator::required($value, $fields);
        if ($id === null) {
            Validator::integer($value['machine_parameter_id'], 'machine_parameter_id', 1);
        }
        Validator::decimal($value['value'], 'value', -self::MAX_VALUE, self::MAX_VALUE);
    }

    private function getRun($runId)
    {
        $run = $this->runDao->getById($runId);
        if (!$run) {
            throw new NotFoundException('Production run not found.');
        }
        return $run;
    }
}
