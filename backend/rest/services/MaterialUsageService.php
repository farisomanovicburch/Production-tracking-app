<?php
require_once __DIR__ . '/BaseService.php';
require_once __DIR__ . '/MaterialService.php';
require_once __DIR__ . '/../dao/MaterialUsageDao.php';
require_once __DIR__ . '/../dao/ProductionRunDao.php';
require_once __DIR__ . '/../dao/RecipeItemDao.php';

/*
 * How much of each material a run used. Every write here moves stock in the same transaction: a usage that is
 * saved without its stock movement (or the other way round) would make the stock lie from then on.
 * ProductionRunService uses addToRun() for the usages sent with a new run, so the rules live in one place.
 */
class MaterialUsageService extends BaseService
{
    protected $entityName = 'Material usage';

    private const ACCEPTED_FIELDS = ['material_id', 'quantity_kg'];
    private const MAX_KG = 999999999.999;

    private $runDao;
    private $recipeItemDao;
    private $materialService;

    // The run is looked up through its DAO, not ProductionRunService, which creates this service (see
    // RunParameterValueService).
    public function __construct($dao = null, $runDao = null, $recipeItemDao = null, $materialService = null)
    {
        parent::__construct($dao ?? new MaterialUsageDao());
        $this->runDao = $runDao ?? new ProductionRunDao();
        $this->recipeItemDao = $recipeItemDao ?? new RecipeItemDao();
        $this->materialService = $materialService ?? new MaterialService();
    }

    public function getByRun($runId)
    {
        $this->getRun($runId);
        return $this->dao->getByRun($runId);
    }

    // No active check: adding to an existing run corrects history. A NEW run refuses deactivated materials in
    // ProductionRunService before it gets here.
    public function addToRun($runId, $usage)
    {
        $run = $this->getRun($runId);
        $usage = array_intersect_key($usage, array_flip(self::ACCEPTED_FIELDS));
        $this->validate($usage);

        try {
            $this->materialService->getById($usage['material_id']);
        } catch (NotFoundException $exception) {
            // The id comes from the body, so a wrong one is a bad request (400), not a missing page (404).
            throw new ValidationException('The selected material does not exist.');
        }
        $recipeMaterials = array_column($this->recipeItemDao->getByRecipe($run['recipe_id']), 'material_id');
        if (!in_array((int) $usage['material_id'], $recipeMaterials, true)) {
            throw new ValidationException("The selected material is not in this run's recipe.");
        }
        if (in_array((int) $usage['material_id'], array_column($this->dao->getByRun($runId), 'material_id'), true)) {
            throw new ValidationException('This material is already used in this run.');
        }

        $usage['production_run_id'] = $runId;
        return $this->dao->transaction(function () use ($usage) {
            $created = $this->dao->add($usage);
            $this->materialService->takeStock($usage['material_id'], $usage['quantity_kg']);
            return $this->dao->getById($created['id']);
        });
    }

    // The inherited add() would skip the run check, the recipe rule and the stock movement.
    public function add($usage)
    {
        throw new LogicException('Use addToRun(): the run comes from the URL.');
    }

    /*
     * Only the amount can change; moving the usage to another material would have to move two stocks. Only the
     * DIFFERENCE moves: going from 30 to 50 kg takes 20 more, going to 10 gives 20 back.
     */
    public function update($usage, $id)
    {
        $existing = $this->getById($id);
        $usage = array_intersect_key($usage, ['quantity_kg' => true]);
        $this->validate($usage, $id);

        return $this->dao->transaction(function () use ($usage, $id, $existing) {
            $differenceGrams = MaterialService::grams($usage['quantity_kg']) - MaterialService::grams($existing['quantity_kg']);
            if ($differenceGrams > 0) {
                $this->materialService->takeStock($existing['material_id'], $differenceGrams / 1000);
            } elseif ($differenceGrams < 0) {
                $this->materialService->returnStock($existing['material_id'], -$differenceGrams / 1000);
            }
            $this->dao->update($usage, $id);
            return $this->dao->getById($id);
        });
    }

    public function delete($id)
    {
        $existing = $this->getById($id);
        $this->dao->transaction(function () use ($id, $existing) {
            $this->dao->delete($id);
            $this->materialService->returnStock($existing['material_id'], $existing['quantity_kg']);
        });
    }

    protected function validate($usage, $id = null)
    {
        $fields = $id === null ? ['material_id', 'quantity_kg'] : ['quantity_kg'];
        Validator::required($usage, $fields);
        if ($id === null) {
            Validator::integer($usage['material_id'], 'material_id', 1);
        }
        // More than zero (also a CHECK): a usage of 0 kg is no usage, and a negative one would be a hidden delivery.
        Validator::decimal($usage['quantity_kg'], 'quantity_kg', 0.001, self::MAX_KG);
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
