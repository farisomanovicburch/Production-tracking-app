<?php
require_once __DIR__ . '/BaseService.php';
require_once __DIR__ . '/MachineService.php';
require_once __DIR__ . '/../dao/MachineParameterDao.php';

class MachineParameterService extends BaseService
{
    protected $entityName = 'Machine parameter';
    protected $inUseMessage = 'Runs or conversions have saved values for this parameter, so it cannot be deleted. Deactivate it instead.';

    // machine_id is not on the list: it comes from the URL when adding, and a parameter never moves to another
    // machine, because the values already saved for it belong to that machine's runs.
    private const ACCEPTED_FIELDS = ['name', 'unit', 'display_order', 'is_active'];

    private $machineService;

    public function __construct($dao = null, $machineService = null)
    {
        parent::__construct($dao ?? new MachineParameterDao());
        $this->machineService = $machineService ?? new MachineService();
    }

    // MachineService::getById() throws "Machine not found.", so a wrong id in the URL is a 404, not an empty list.
    public function getByMachine($machineId)
    {
        $this->machineService->getById($machineId);
        return $this->dao->getByMachine($machineId);
    }

    public function addToMachine($machineId, $parameter)
    {
        $this->machineService->getById($machineId);
        $parameter = $this->prepare($parameter);
        $parameter['machine_id'] = $machineId;
        // The column's default is 0, which would put every new parameter at the top of the run form.
        if (!isset($parameter['display_order'])) {
            $parameter['display_order'] = $this->nextDisplayOrder($machineId);
        }
        return parent::add($parameter);
    }

    // The inherited add() would skip the machine check, the allow-list and the display order. A mistake in the
    // code, not in the user's input: LogicException (500, logged), not a 400.
    public function add($parameter)
    {
        throw new LogicException('Use addToMachine(): the machine comes from the URL.');
    }

    public function update($parameter, $id)
    {
        return parent::update($this->prepare($parameter), $id);
    }

    /*
     * A new run or conversion must record every parameter the machine still uses; deactivated ones are not asked
     * for. Lives here because both run services need it and the parameters are this service's entity.
     */
    public function assertValuesForEveryActiveParameter($machineId, $values)
    {
        $sentParameterIds = array_map('intval', array_column($values, 'machine_parameter_id'));
        $missing = [];
        foreach ($this->dao->getActiveByMachine($machineId) as $parameter) {
            if (!in_array((int) $parameter['id'], $sentParameterIds, true)) {
                $missing[] = $parameter['name'];
            }
        }
        if ($missing !== []) {
            throw new ValidationException('Missing values for these parameters: ' . implode(', ', $missing) . '.');
        }
    }

    protected function validate($parameter, $id = null)
    {
        Validator::required($parameter, ['name']);
        Validator::length($parameter['name'], 'name', 1, 100);
        if (isset($parameter['unit'])) {
            Validator::length($parameter['unit'], 'unit', 1, 20);
        }
        if (array_key_exists('display_order', $parameter)) {
            Validator::integer($parameter['display_order'], 'display_order', 0);
        }
        if (array_key_exists('is_active', $parameter)) {
            Validator::boolean($parameter['is_active'], 'is_active');
        }

        // On update the body has no machine_id (not accepted), so the machine is the one the parameter already has.
        $machineId = $id === null ? $parameter['machine_id'] : $this->dao->getById($id)['machine_id'];
        $existing = $this->dao->getByName($machineId, $parameter['name']);
        if ($existing && $existing['id'] != $id) {
            throw new ValidationException('This machine already has a parameter with this name.');
        }
    }

    private function prepare($parameter)
    {
        $parameter = array_intersect_key($parameter, array_flip(self::ACCEPTED_FIELDS));
        $parameter = $this->trimStrings($parameter, ['name', 'unit']);
        $parameter = $this->emptyToNull($parameter, ['unit']);
        return $this->normalizeBooleans($parameter, ['is_active']);
    }

    private function nextDisplayOrder($machineId)
    {
        $orders = array_column($this->dao->getByMachine($machineId), 'display_order');
        return $orders === [] ? 1 : max($orders) + 1;
    }
}
