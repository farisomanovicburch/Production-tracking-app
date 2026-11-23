<?php
require_once __DIR__ . '/BaseService.php';
require_once __DIR__ . '/../dao/ConversionRunParameterValueDao.php';
require_once __DIR__ . '/../dao/ConversionRunDao.php';
require_once __DIR__ . '/../dao/MachineParameterDao.php';

/*
 * The machine settings recorded for a conversion (weld time, cooling time, speed). The same rules as a run's
 * values (RunParameterValueService): only parameters of the conversion's own machine, only active ones for new
 * values. ConversionRunService uses addToConversion() for the values sent with a new conversion.
 */
class ConversionRunParameterValueService extends BaseService
{
    protected $entityName = 'Conversion parameter value';

    private const ACCEPTED_FIELDS = ['machine_parameter_id', 'value'];
    private const MAX_VALUE = 999999999.999;

    private $conversionDao;
    private $machineParameterDao;

    // The conversion is looked up through its DAO, not ConversionRunService, which creates this service.
    public function __construct($dao = null, $conversionDao = null, $machineParameterDao = null)
    {
        parent::__construct($dao ?? new ConversionRunParameterValueDao());
        $this->conversionDao = $conversionDao ?? new ConversionRunDao();
        $this->machineParameterDao = $machineParameterDao ?? new MachineParameterDao();
    }

    public function getByConversion($conversionId)
    {
        $this->getConversion($conversionId);
        return $this->dao->getByConversionRun($conversionId);
    }

    public function addToConversion($conversionId, $value)
    {
        $conversion = $this->getConversion($conversionId);
        $value = array_intersect_key($value, array_flip(self::ACCEPTED_FIELDS));
        $this->validate($value);

        $parameter = $this->machineParameterDao->getById($value['machine_parameter_id']);
        if (!$parameter) {
            throw new ValidationException('The selected parameter does not exist.');
        }
        if ($parameter['machine_id'] != $conversion['machine_id']) {
            throw new ValidationException("The selected parameter does not belong to this conversion's machine.");
        }
        if (!$parameter['is_active']) {
            throw new ValidationException('The selected parameter is deactivated.');
        }
        $existing = array_column($this->dao->getByConversionRun($conversionId), 'machine_parameter_id');
        if (in_array((int) $value['machine_parameter_id'], $existing, true)) {
            throw new ValidationException('This conversion already has a value for this parameter.');
        }

        $value['conversion_run_id'] = $conversionId;
        $created = $this->dao->add($value);
        return $this->dao->getById($created['id']);
    }

    // The inherited add() would skip the conversion check, the allow-list and the parameter rules.
    public function add($value)
    {
        throw new LogicException('Use addToConversion(): the conversion comes from the URL.');
    }

    // Only the number can be corrected, also after its parameter was deactivated (the conversion is history).
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

    private function getConversion($conversionId)
    {
        $conversion = $this->conversionDao->getById($conversionId);
        if (!$conversion) {
            throw new NotFoundException('Conversion run not found.');
        }
        return $conversion;
    }
}
