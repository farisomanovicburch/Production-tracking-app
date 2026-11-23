<?php
require_once __DIR__ . '/BaseService.php';
require_once __DIR__ . '/../dao/MachineDao.php';

class MachineService extends BaseService
{
    // The values of the machine_type ENUM. Other services compare against these, never against typed-out strings.
    public const EXTRUSION = 'extrusion';
    public const CONVERSION = 'conversion';

    protected $entityName = 'Machine';
    protected $inUseMessage = 'This machine has runs, conversions or maintenance records and cannot be deleted. Deactivate it instead.';

    public function __construct($dao = null)
    {
        parent::__construct($dao ?? new MachineDao());
    }

    public function add($machine)
    {
        return parent::add($this->prepare($machine));
    }

    public function update($machine, $id)
    {
        return parent::update($this->prepare($machine), $id);
    }

    protected function validate($machine, $id = null)
    {
        // The type may be left out of an update: the edit form shows it locked, and a disabled field is not sent.
        Validator::required($machine, $id === null ? ['name', 'code', 'machine_type'] : ['name', 'code']);
        Validator::length($machine['name'], 'name', 2, 100);
        Validator::length($machine['code'], 'code', 2, 20);
        if (array_key_exists('machine_type', $machine)) {
            Validator::oneOf($machine['machine_type'], 'machine_type', [self::EXTRUSION, self::CONVERSION]);
        }
        if (array_key_exists('is_active', $machine)) {
            Validator::boolean($machine['is_active'], 'is_active');
        }

        // Runs, conversions, parameters and product links were all checked against the type the machine has now.
        if ($id !== null && array_key_exists('machine_type', $machine)
            && $machine['machine_type'] !== $this->dao->getById($id)['machine_type']) {
            throw new ValidationException('The machine type cannot be changed. Add a new machine instead.');
        }

        $existing = $this->dao->getByCode($machine['code']);
        if ($existing && $existing['id'] != $id) {
            throw new ValidationException('A machine with this code already exists.');
        }
    }

    private function prepare($machine)
    {
        $machine = $this->trimStrings($machine, ['name', 'code']);
        return $this->normalizeBooleans($machine, ['is_active']);
    }
}
