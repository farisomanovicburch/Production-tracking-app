<?php
require_once __DIR__ . '/BaseService.php';
require_once __DIR__ . '/RecipeService.php';
require_once __DIR__ . '/../dao/RecipeProductDao.php';
require_once __DIR__ . '/../dao/ProductDao.php';

/*
 * Which recipes a product can be made with. A product has at most one default recipe (the run form preselects it).
 * The database cannot express "at most one TRUE per product", so this service does: making a link the default
 * first unsets the product's current default, and both writes happen in one transaction.
 */
class RecipeProductService extends BaseService
{
    protected $entityName = 'Recipe product link';

    private $recipeService;
    private $productDao;

    public function __construct($dao = null, $recipeService = null, $productDao = null)
    {
        parent::__construct($dao ?? new RecipeProductDao());
        $this->recipeService = $recipeService ?? new RecipeService();
        $this->productDao = $productDao ?? new ProductDao();
    }

    public function getByRecipe($recipeId)
    {
        $this->recipeService->getById($recipeId);
        return $this->dao->getByRecipe($recipeId);
    }

    public function addToRecipe($recipeId, $link)
    {
        $this->recipeService->getById($recipeId);
        $link = $this->normalizeBooleans(array_intersect_key($link, array_flip(['product_id', 'is_default'])), ['is_default']);
        $link['recipe_id'] = $recipeId;
        $this->validate($link);

        // All or nothing: if saving the new link fails, the old default is not left unset.
        return $this->dao->transaction(function () use ($link) {
            if (($link['is_default'] ?? 0) === 1) {
                $this->unsetDefault($link['product_id']);
            }
            $created = $this->dao->add($link);
            return $this->dao->getById($created['id']);
        });
    }

    // The inherited add() would skip the recipe check, the allow-list and the default swap.
    public function add($link)
    {
        throw new LogicException('Use addToRecipe(): the recipe comes from the URL.');
    }

    // Only is_default can change: a different product or recipe is a different link (remove it, add another).
    public function update($link, $id)
    {
        $existing = $this->getById($id);
        $link = $this->normalizeBooleans(array_intersect_key($link, ['is_default' => true]), ['is_default']);
        $this->validate($link, $id);

        // Unset first, then save: in the other order, a link that already is the default would unset itself.
        return $this->dao->transaction(function () use ($link, $id, $existing) {
            if ($link['is_default'] === 1) {
                $this->unsetDefault($existing['product_id']);
            }
            $this->dao->update($link, $id);
            return $this->dao->getById($id);
        });
    }

    protected function validate($link, $id = null)
    {
        if ($id !== null) {
            Validator::required($link, ['is_default']);
            Validator::boolean($link['is_default'], 'is_default');
            return;
        }

        Validator::required($link, ['product_id']);
        Validator::integer($link['product_id'], 'product_id', 1);
        if (array_key_exists('is_default', $link)) {
            Validator::boolean($link['is_default'], 'is_default');
        }
        // The product id comes from the body, so a wrong one is a bad request (400), not a missing page (404).
        if (!$this->productDao->getById($link['product_id'])) {
            throw new ValidationException('The selected product does not exist.');
        }
        if ($this->dao->getLink($link['recipe_id'], $link['product_id'])) {
            throw new ValidationException('This product is already linked to the recipe.');
        }
    }

    private function unsetDefault($productId)
    {
        $current = $this->dao->getDefaultForProduct($productId);
        if ($current) {
            $this->dao->update(['is_default' => 0], $current['id']);
        }
    }
}
