<?php
require_once __DIR__ . '/BaseService.php';
require_once __DIR__ . '/RecipeItemService.php';
require_once __DIR__ . '/MaterialService.php';
require_once __DIR__ . '/MachineParameterService.php';
require_once __DIR__ . '/RunParameterValueService.php';
require_once __DIR__ . '/MaterialUsageService.php';
require_once __DIR__ . '/../dao/ProductionRunDao.php';
require_once __DIR__ . '/../dao/MachineDao.php';
require_once __DIR__ . '/../dao/ProductDao.php';
require_once __DIR__ . '/../dao/RecipeDao.php';
require_once __DIR__ . '/../dao/UserDao.php';
require_once __DIR__ . '/../dao/MachineProductDao.php';
require_once __DIR__ . '/../dao/RecipeProductDao.php';
require_once __DIR__ . '/../dao/MaterialDao.php';
require_once __DIR__ . '/../dao/RunParameterValueDao.php';
require_once __DIR__ . '/../dao/MaterialUsageDao.php';

/*
 * One extrusion run: which machine made which product with which recipe, who logged it, when it ran and how much
 * came out, with its machine settings (parameter values) and the materials it used. The foreign keys only know
 * that the rows exist; this service checks that they belong together (the product is an output of that machine,
 * the recipe is meant for that product and is complete) and keeps the material stock in step with the usages.
 */
class ProductionRunService extends BaseService
{
    protected $entityName = 'Production run';

    // The largest numbers DECIMAL(12,3) and DECIMAL(10,4) can hold.
    private const MAX_KG = 999999999.999;
    private const MAX_WEIGHT_PER_UNIT_KG = 999999.9999;

    private const OPTIONAL_FIELDS = ['warmup_started_at', 'stable_at', 'net_weight_per_unit_kg', 'gross_weight_per_unit_kg', 'notes'];
    private const TIME_FIELDS = ['warmup_started_at', 'started_at', 'stable_at', 'ended_at'];
    // Locked after create: the parameters, the product links and the material rows all depend on them.
    // A run logged on the wrong machine is deleted and logged again.
    private const IDENTITY_FIELDS = ['machine_id', 'product_id', 'recipe_id', 'user_id'];

    private $machineDao;
    private $productDao;
    private $recipeDao;
    private $userDao;
    private $machineProductDao;
    private $recipeProductDao;
    private $machineParameterService;
    private $materialDao;
    private $runParameterValueDao;
    private $materialUsageDao;
    private $recipeItemService;
    private $materialService;
    private $runParameterValueService;
    private $materialUsageService;

    // Only the run's own DAO can be passed in: the others are created here, because a constructor with fifteen
    // optional arguments would be harder to read than it is worth, and no test replaces them.
    public function __construct($dao = null)
    {
        parent::__construct($dao ?? new ProductionRunDao());
        $this->machineDao = new MachineDao();
        $this->productDao = new ProductDao();
        $this->recipeDao = new RecipeDao();
        $this->userDao = new UserDao();
        $this->machineProductDao = new MachineProductDao();
        $this->recipeProductDao = new RecipeProductDao();
        $this->machineParameterService = new MachineParameterService();
        $this->materialDao = new MaterialDao();
        $this->runParameterValueDao = new RunParameterValueDao();
        $this->materialUsageDao = new MaterialUsageDao();
        $this->recipeItemService = new RecipeItemService();
        $this->materialService = new MaterialService();
        $this->runParameterValueService = new RunParameterValueService();
        $this->materialUsageService = new MaterialUsageService();
    }

    // The details page gets the run and its children in one request; the list (getAll) stays one row per run.
    public function getById($id)
    {
        $run = parent::getById($id);
        $run['parameter_values'] = $this->runParameterValueDao->getByRun($id);
        $run['material_usages'] = $this->materialUsageDao->getByRun($id);
        return $run;
    }

    /*
     * All or nothing: the run, its values, its usages and the stock decrease are written in one transaction. If the
     * last usage finds too little stock, the run and everything before it are rolled back as well, so there is
     * never a run without its usages or a stock that was lowered for a run that does not exist.
     * The children go through their own services, so a value or usage obeys the same rules here as on its own route.
     */
    public function add($run)
    {
        $run = $this->prepare($run);
        $this->validate($run);
        $this->validateLinks($run);
        $parameterValues = $this->listOfObjects($run, 'parameter_values');
        $materialUsages = $this->listOfObjects($run, 'material_usages');
        $this->validateNewRunChildren($run, $parameterValues, $materialUsages);

        return $this->dao->transaction(function () use ($run, $parameterValues, $materialUsages) {
            $runId = $this->dao->add($run)['id'];
            foreach ($parameterValues as $value) {
                $this->runParameterValueService->addToRun($runId, $value);
            }
            foreach ($materialUsages as $usage) {
                $this->materialUsageService->addToRun($runId, $usage);
            }
            return $this->getById($runId);
        });
    }

    /*
     * Changes the run's own fields; its values and usages have their own routes. A field left out keeps its saved
     * value, so the rules (e.g. the time order) are checked on the saved run with the changes applied, not on the
     * request alone. validateLinks() is not called: a run is history and stays editable after its machine,
     * product, recipe or user is deactivated.
     */
    public function update($run, $id)
    {
        $existing = parent::getById($id);
        $run = $this->prepare(array_diff_key($run, array_flip(array_merge(self::IDENTITY_FIELDS, ['parameter_values', 'material_usages']))));
        $this->validate(array_merge($existing, $run), $id);
        $this->dao->update($run, $id);
        return $this->getById($id);
    }

    // The values and usages go with the run (ON DELETE CASCADE); the material it used goes back on the shelf.
    public function delete($id)
    {
        parent::getById($id);
        $this->dao->transaction(function () use ($id) {
            foreach ($this->materialUsageDao->getByRun($id) as $usage) {
                $this->materialService->returnStock($usage['material_id'], $usage['quantity_kg']);
            }
            $this->dao->delete($id);
        });
    }

    protected function validate($run, $id = null)
    {
        Validator::required($run, ['machine_id', 'product_id', 'recipe_id', 'user_id', 'started_at', 'ended_at', 'quantity_produced']);
        foreach (['machine_id', 'product_id', 'recipe_id', 'user_id'] as $field) {
            Validator::integer($run[$field], $field, 1);
        }
        foreach (self::TIME_FIELDS as $field) {
            if (isset($run[$field])) {
                Validator::datetime($run[$field], $field);
            }
        }
        Validator::decimal($run['quantity_produced'], 'quantity_produced', 0, self::MAX_KG);
        if (isset($run['scrap_kg'])) {
            Validator::decimal($run['scrap_kg'], 'scrap_kg', 0, self::MAX_KG);
        }
        foreach (['net_weight_per_unit_kg', 'gross_weight_per_unit_kg'] as $field) {
            if (isset($run[$field])) {
                Validator::decimal($run[$field], $field, 0.0001, self::MAX_WEIGHT_PER_UNIT_KG);
            }
        }
        if (isset($run['notes'])) {
            Validator::length($run['notes'], 'notes', 1, 1000);
        }

        // Gross = product + packaging (core, bag), so it can never weigh less than the product alone.
        if (isset($run['net_weight_per_unit_kg'], $run['gross_weight_per_unit_kg'])
            && (float) $run['gross_weight_per_unit_kg'] < (float) $run['net_weight_per_unit_kg']) {
            throw new ValidationException('The gross weight per unit cannot be less than the net weight.');
        }

        $this->validateTimeOrder($run);
    }

    // The database checks the same order (CHECK constraints), but it would answer with a 500 and no readable reason.
    // The times are already 'YYYY-MM-DD HH:MM:SS', and in that shape comparing the text compares the moments.
    private function validateTimeOrder($run)
    {
        if (strcmp($run['ended_at'], $run['started_at']) <= 0) {
            throw new ValidationException('The run must end after it starts.');
        }
        if (isset($run['warmup_started_at']) && strcmp($run['warmup_started_at'], $run['started_at']) >= 0) {
            throw new ValidationException('The warm-up must start before the run starts.');
        }
        if (isset($run['stable_at'])
            && (strcmp($run['stable_at'], $run['started_at']) <= 0 || strcmp($run['stable_at'], $run['ended_at']) >= 0)) {
            throw new ValidationException('The run must become stable after it starts and before it ends.');
        }
    }

    // Only for a new run: an old run keeps its machine, product, recipe and user even after one of them is
    // deactivated, because the run is history.
    private function validateLinks($run)
    {
        $machine = $this->machineDao->getById($run['machine_id']);
        if (!$machine) {
            throw new ValidationException('The selected machine does not exist.');
        }
        if (!$machine['is_active']) {
            throw new ValidationException('The selected machine is deactivated and cannot be used for new runs.');
        }
        if ($machine['machine_type'] !== 'extrusion') {
            throw new ValidationException('The selected machine is not an extrusion machine.');
        }

        $product = $this->productDao->getById($run['product_id']);
        if (!$product) {
            throw new ValidationException('The selected product does not exist.');
        }
        if (!$product['is_active']) {
            throw new ValidationException('The selected product is deactivated and cannot be used for new runs.');
        }
        if (!$this->machineProductDao->getLink($run['machine_id'], $run['product_id'], 'output')) {
            throw new ValidationException('The selected machine does not make the selected product.');
        }

        $recipe = $this->recipeDao->getById($run['recipe_id']);
        if (!$recipe) {
            throw new ValidationException('The selected recipe does not exist.');
        }
        if (!$recipe['is_active']) {
            throw new ValidationException('The selected recipe is deactivated and cannot be used for new runs.');
        }
        if (!$this->recipeProductDao->getLink($run['recipe_id'], $run['product_id'])) {
            throw new ValidationException('The selected recipe is not linked to the selected product.');
        }
        $total = $this->recipeItemService->totalPercentage($run['recipe_id']);
        if ($total !== '100.00') {
            throw new ValidationException("The materials of the selected recipe add up to $total %, not 100 %.");
        }

        $user = $this->userDao->getById($run['user_id']);
        if (!$user) {
            throw new ValidationException('The selected user does not exist.');
        }
        if (!$user['is_active']) {
            throw new ValidationException('The selected user is deactivated and cannot log new runs.');
        }
    }

    // Rules only a NEW run has. Everything a single value or usage must obey is checked by its own service.
    private function validateNewRunChildren($run, $parameterValues, $materialUsages)
    {
        $this->machineParameterService->assertValuesForEveryActiveParameter($run['machine_id'], $parameterValues);

        // Without usages the stock would silently stay where it was, as if the run had used nothing.
        if ($materialUsages === []) {
            throw new ValidationException('A run must use at least one material.');
        }
        foreach ($materialUsages as $usage) {
            $material = isset($usage['material_id']) ? $this->materialDao->getById($usage['material_id']) : null;
            if ($material && !$material['is_active']) {
                throw new ValidationException('The selected material is deactivated and cannot be used for new runs.');
            }
        }
    }

    private function prepare($run)
    {
        $run = $this->trimStrings($run, ['notes']);
        $run = $this->emptyToNull($run, array_merge(self::OPTIONAL_FIELDS, ['scrap_kg']));
        // scrap_kg is NOT NULL with a default of 0: an empty field means "no scrap", so the column's default is used.
        if (array_key_exists('scrap_kg', $run) && $run['scrap_kg'] === null) {
            unset($run['scrap_kg']);
        }
        return $this->normalizeDatetimes($run, self::TIME_FIELDS);
    }
}
