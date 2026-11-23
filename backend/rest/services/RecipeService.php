<?php
require_once __DIR__ . '/BaseService.php';
require_once __DIR__ . '/../dao/RecipeDao.php';

class RecipeService extends BaseService
{
    protected $entityName = 'Recipe';
    protected $inUseMessage = 'This recipe has production runs and cannot be deleted. Deactivate it instead.';

    public function __construct($dao = null)
    {
        parent::__construct($dao ?? new RecipeDao());
    }

    public function add($recipe)
    {
        return parent::add($this->prepare($recipe));
    }

    public function update($recipe, $id)
    {
        return parent::update($this->prepare($recipe), $id);
    }

    protected function validate($recipe, $id = null)
    {
        Validator::required($recipe, ['name']);
        Validator::length($recipe['name'], 'name', 2, 100);
        if (isset($recipe['notes'])) {
            Validator::length($recipe['notes'], 'notes', 1, 255);
        }
        if (array_key_exists('is_active', $recipe)) {
            Validator::boolean($recipe['is_active'], 'is_active');
        }

        $existing = $this->dao->getByName($recipe['name']);
        if ($existing && $existing['id'] != $id) {
            throw new ValidationException('A recipe with this name already exists.');
        }
    }

    private function prepare($recipe)
    {
        $recipe = $this->trimStrings($recipe, ['name', 'notes']);
        $recipe = $this->emptyToNull($recipe, ['notes']);
        return $this->normalizeBooleans($recipe, ['is_active']);
    }
}
