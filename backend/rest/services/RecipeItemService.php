<?php
require_once __DIR__ . '/BaseService.php';
require_once __DIR__ . '/RecipeService.php';
require_once __DIR__ . '/../dao/RecipeItemDao.php';
require_once __DIR__ . '/../dao/MaterialDao.php';

/*
 * The materials of a recipe and their share in percent. The shares may never add up to more than 100 %; a recipe
 * whose shares add up to less is still being written, and only a complete one (exactly 100 %) can be used in a
 * production run (ProductionRunService checks it with totalPercentage()).
 */
class RecipeItemService extends BaseService
{
    protected $entityName = 'Recipe item';

    // recipe_id is not on the list: it comes from the URL when adding, and an item never moves to another recipe.
    private const ACCEPTED_FIELDS = ['material_id', 'percentage'];

    // All shares are compared in hundredths of a percent, as whole numbers (see sumInHundredths()).
    private const HUNDRED_PERCENT = 10000;

    private $recipeService;
    private $materialDao;

    public function __construct($dao = null, $recipeService = null, $materialDao = null)
    {
        parent::__construct($dao ?? new RecipeItemDao());
        $this->recipeService = $recipeService ?? new RecipeService();
        $this->materialDao = $materialDao ?? new MaterialDao();
    }

    public function getByRecipe($recipeId)
    {
        $this->recipeService->getById($recipeId);
        return $this->dao->getByRecipe($recipeId);
    }

    public function addToRecipe($recipeId, $item)
    {
        $this->recipeService->getById($recipeId);
        $item = array_intersect_key($item, array_flip(self::ACCEPTED_FIELDS));
        $item['recipe_id'] = $recipeId;
        return parent::add($item);
    }

    // The inherited add() would skip the recipe check and the allow-list: a mistake in the code, not in the input.
    public function add($item)
    {
        throw new LogicException('Use addToRecipe(): the recipe comes from the URL.');
    }

    public function update($item, $id)
    {
        return parent::update(array_intersect_key($item, array_flip(self::ACCEPTED_FIELDS)), $id);
    }

    protected function validate($item, $id = null)
    {
        Validator::required($item, ['material_id', 'percentage']);
        Validator::integer($item['material_id'], 'material_id', 1);
        Validator::decimal($item['percentage'], 'percentage', 0.01, 100);
        if (!$this->materialDao->getById($item['material_id'])) {
            throw new ValidationException('The selected material does not exist.');
        }

        // On update the item itself is left out: its new share replaces the old one instead of being added to it.
        $recipeId = $id === null ? $item['recipe_id'] : $this->dao->getById($id)['recipe_id'];
        $otherItems = array_filter($this->dao->getByRecipe($recipeId), fn ($other) => $other['id'] != $id);

        if (in_array((int) $item['material_id'], array_column($otherItems, 'material_id'), true)) {
            throw new ValidationException('This material is already in the recipe.');
        }

        $total = $this->sumInHundredths(array_merge(array_column($otherItems, 'percentage'), [$item['percentage']]));
        if ($total > self::HUNDRED_PERCENT) {
            $shown = number_format($total / 100, 2, '.', '');
            throw new ValidationException("The materials of this recipe would add up to $shown %. They may not add up to more than 100 %.");
        }
    }

    // "100.00" for a complete recipe. Text with two decimals, so a caller compares it exactly and can show it.
    public function totalPercentage($recipeId)
    {
        $total = $this->sumInHundredths(array_column($this->dao->getByRecipe($recipeId), 'percentage'));
        return number_format($total / 100, 2, '.', '');
    }

    /*
     * 90.2 + 0.9 + 8.9 is 100.00000000000001 in floating point (0.1 cannot be stored exactly in binary, like 1/3
     * cannot be written exactly in decimals), so a correct recipe would be refused. The column keeps two decimals,
     * so every share is turned into a whole number of hundredths (90.2 -> 9020) before adding: whole numbers add
     * up exactly.
     */
    private function sumInHundredths($percentages)
    {
        $sum = 0;
        foreach ($percentages as $percentage) {
            $sum += (int) round((float) $percentage * 100);
        }
        return $sum;
    }
}
