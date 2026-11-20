<?php
require_once __DIR__ . '/BaseService.php';
require_once __DIR__ . '/../dao/MaterialDao.php';

/*
 * Stock is set once, when a material is created (the count on the day the app starts being used). After that it
 * changes only through a delivery (+) and through production runs (-, commit 7), so every kilogram has a reason.
 * An edit form that could type in a new stock number would hide losses and mistakes.
 */
class MaterialService extends BaseService
{
    protected $entityName = 'Material';
    protected $inUseMessage = 'This material is used in recipes or production runs and cannot be deleted. Deactivate it instead.';

    // The largest amount a DECIMAL(12,3) column can hold.
    private const MAX_KG = 999999999.999;

    // NOT NULL columns with a default: an empty form field means "use the default" when adding and "keep the
    // current value" when updating, so the field is left out instead of being sent as NULL.
    private const DEFAULTED_FIELDS = ['stock_qty', 'unit'];

    public function __construct($dao = null)
    {
        parent::__construct($dao ?? new MaterialDao());
    }

    public function add($material)
    {
        return parent::add($this->prepare($material));
    }

    // stock_qty is dropped, not rejected: the edit form may send the whole material back, stock included.
    public function update($material, $id)
    {
        $material = $this->prepare($material);
        unset($material['stock_qty']);
        return parent::update($material, $id);
    }

    public function addDelivery($id, $delivery)
    {
        $this->getById($id);
        Validator::required($delivery, ['quantity_kg']);
        // More than zero: a negative "delivery" would be exactly the hidden correction this route exists to prevent.
        Validator::decimal($delivery['quantity_kg'], 'quantity_kg', 0.001, self::MAX_KG);
        // changeStock() adds inside MySQL in one statement, so a delivery and a run saved at the same moment
        // cannot overwrite each other's result.
        $this->dao->changeStock($id, $delivery['quantity_kg']);
        return $this->dao->getById($id);
    }

    protected function validate($material, $id = null)
    {
        Validator::required($material, ['name']);
        Validator::length($material['name'], 'name', 2, 100);
        if (isset($material['unit'])) {
            Validator::length($material['unit'], 'unit', 1, 10);
        }
        if (isset($material['supplier'])) {
            Validator::length($material['supplier'], 'supplier', 1, 100);
        }
        if (isset($material['stock_qty'])) {
            Validator::decimal($material['stock_qty'], 'stock_qty', 0, self::MAX_KG);
        }
        if (isset($material['min_stock_qty'])) {
            Validator::decimal($material['min_stock_qty'], 'min_stock_qty', 0, self::MAX_KG);
        }
        if (array_key_exists('is_active', $material)) {
            Validator::boolean($material['is_active'], 'is_active');
        }

        $existing = $this->dao->getByName($material['name']);
        if ($existing && $existing['id'] != $id) {
            throw new ValidationException('A material with this name already exists.');
        }
    }

    private function prepare($material)
    {
        $material = $this->trimStrings($material, ['name', 'unit', 'supplier']);
        // NULL for min_stock_qty means "no low-stock warning for this material".
        $material = $this->emptyToNull($material, ['supplier', 'min_stock_qty', 'stock_qty', 'unit']);
        foreach (self::DEFAULTED_FIELDS as $field) {
            if (array_key_exists($field, $material) && $material[$field] === null) {
                unset($material[$field]);
            }
        }
        return $this->normalizeBooleans($material, ['is_active']);
    }
}
