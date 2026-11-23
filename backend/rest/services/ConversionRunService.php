<?php
require_once __DIR__ . '/BaseService.php';
require_once __DIR__ . '/MachineParameterService.php';
require_once __DIR__ . '/ConversionRunParameterValueService.php';
require_once __DIR__ . '/../dao/ConversionRunDao.php';
require_once __DIR__ . '/../dao/ConversionRunParameterValueDao.php';
require_once __DIR__ . '/../dao/MachineDao.php';
require_once __DIR__ . '/../dao/ProductDao.php';
require_once __DIR__ . '/../dao/UserDao.php';
require_once __DIR__ . '/../dao/MachineProductDao.php';

/*
 * One bag-making job: which film (input) became which bags (output) on which machine, with which settings.
 * Logged once, after the job. No stock moves: the factory does not track film or bag stock. Scrap is
 * input_kg - output_kg, calculated by ConversionRunDao and never stored, so it cannot disagree with them.
 */
class ConversionRunService extends BaseService
{
    protected $entityName = 'Conversion run';

    private const MAX_KG = 999999999.999;
    // The largest number an INT column can hold.
    private const MAX_BAGS = 2147483647;
    private const TIME_FIELDS = ['started_at', 'ended_at'];
    // Locked after create, like a run's: the parameters and the product links depend on the machine.
    private const IDENTITY_FIELDS = ['machine_id', 'user_id', 'input_product_id', 'output_product_id'];

    private $machineDao;
    private $productDao;
    private $userDao;
    private $machineProductDao;
    private $parameterValueDao;
    private $machineParameterService;
    private $parameterValueService;

    // Only the conversion's own DAO can be passed in, for the same reason as in ProductionRunService.
    public function __construct($dao = null)
    {
        parent::__construct($dao ?? new ConversionRunDao());
        $this->machineDao = new MachineDao();
        $this->productDao = new ProductDao();
        $this->userDao = new UserDao();
        $this->machineProductDao = new MachineProductDao();
        $this->parameterValueDao = new ConversionRunParameterValueDao();
        $this->machineParameterService = new MachineParameterService();
        $this->parameterValueService = new ConversionRunParameterValueService();
    }

    // The details page gets the conversion and its values in one request; the list stays one row per conversion.
    public function getById($id)
    {
        $conversion = parent::getById($id);
        $conversion['parameter_values'] = $this->parameterValueDao->getByConversionRun($id);
        return $conversion;
    }

    // All or nothing: a value that breaks a rule rolls back the conversion row written just before it.
    public function add($conversion)
    {
        $conversion = $this->prepare($conversion);
        $this->validate($conversion);
        $this->validateLinks($conversion);
        $parameterValues = $this->listOfObjects($conversion, 'parameter_values');
        $this->machineParameterService->assertValuesForEveryActiveParameter($conversion['machine_id'], $parameterValues);

        return $this->dao->transaction(function () use ($conversion, $parameterValues) {
            $conversionId = $this->dao->add($conversion)['id'];
            foreach ($parameterValues as $value) {
                $this->parameterValueService->addToConversion($conversionId, $value);
            }
            return $this->getById($conversionId);
        });
    }

    // Same as a run: own fields only, left-out fields keep their saved value, rules checked on the merged result,
    // and no active checks (the conversion is history).
    public function update($conversion, $id)
    {
        $existing = parent::getById($id);
        $conversion = $this->prepare(array_diff_key($conversion, array_flip(array_merge(self::IDENTITY_FIELDS, ['parameter_values']))));
        $this->validate(array_merge($existing, $conversion), $id);
        $this->dao->update($conversion, $id);
        return $this->getById($id);
    }

    protected function validate($conversion, $id = null)
    {
        $idFields = ['machine_id', 'user_id', 'input_product_id', 'output_product_id'];
        Validator::required($conversion, array_merge($idFields, ['started_at', 'ended_at', 'input_kg', 'output_kg', 'bag_count']));
        foreach ($idFields as $field) {
            Validator::integer($conversion[$field], $field, 1);
        }
        foreach (self::TIME_FIELDS as $field) {
            Validator::datetime($conversion[$field], $field);
        }
        // Some film must go in (also a CHECK); zero bags out is possible if the whole job was scrap.
        Validator::decimal($conversion['input_kg'], 'input_kg', 0.001, self::MAX_KG);
        Validator::decimal($conversion['output_kg'], 'output_kg', 0, self::MAX_KG);
        Validator::integer($conversion['bag_count'], 'bag_count', 0, self::MAX_BAGS);
        if (isset($conversion['notes'])) {
            Validator::length($conversion['notes'], 'notes', 1, 1000);
        }

        // Compared in grams (whole numbers), so 410.1 vs 410.1 cannot differ by a floating-point rounding error.
        if (self::grams($conversion['output_kg']) > self::grams($conversion['input_kg'])) {
            throw new ValidationException('The output cannot weigh more than the input.');
        }
        // 'YYYY-MM-DD HH:MM:SS' text sorts in time order, so comparing the text compares the moments.
        if (strcmp($conversion['ended_at'], $conversion['started_at']) <= 0) {
            throw new ValidationException('The conversion must end after it starts.');
        }
    }

    // Only for a new conversion: an old one keeps its machine, products and user after they are deactivated.
    private function validateLinks($conversion)
    {
        $machine = $this->machineDao->getById($conversion['machine_id']);
        if (!$machine) {
            throw new ValidationException('The selected machine does not exist.');
        }
        if (!$machine['is_active']) {
            throw new ValidationException('The selected machine is deactivated and cannot be used for new conversions.');
        }
        if ($machine['machine_type'] !== 'conversion') {
            throw new ValidationException('The selected machine is not a conversion machine.');
        }

        // The role is part of the rule: the film must be an input of the machine, the bags an output.
        $this->validateProduct($conversion, 'input_product_id', 'input', 'The selected machine does not take the selected input product.');
        $this->validateProduct($conversion, 'output_product_id', 'output', 'The selected machine does not make the selected output product.');

        $user = $this->userDao->getById($conversion['user_id']);
        if (!$user) {
            throw new ValidationException('The selected user does not exist.');
        }
        if (!$user['is_active']) {
            throw new ValidationException('The selected user is deactivated and cannot log new conversions.');
        }
    }

    private function validateProduct($conversion, $field, $role, $notLinkedMessage)
    {
        $product = $this->productDao->getById($conversion[$field]);
        if (!$product) {
            throw new ValidationException("The selected $role product does not exist.");
        }
        if (!$product['is_active']) {
            throw new ValidationException("The selected $role product is deactivated and cannot be used for new conversions.");
        }
        if (!$this->machineProductDao->getLink($conversion['machine_id'], $conversion[$field], $role)) {
            throw new ValidationException($notLinkedMessage);
        }
    }

    private function prepare($conversion)
    {
        $conversion = $this->trimStrings($conversion, ['notes']);
        $conversion = $this->emptyToNull($conversion, ['notes']);
        return $this->normalizeDatetimes($conversion, self::TIME_FIELDS);
    }
}
