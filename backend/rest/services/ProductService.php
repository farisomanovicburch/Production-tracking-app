<?php
require_once __DIR__ . '/BaseService.php';
require_once __DIR__ . '/../dao/ProductDao.php';

class ProductService extends BaseService
{
    // The values of the products.unit ENUM: film is counted in kilograms, bags in pieces.
    public const KG = 'kg';
    public const PCS = 'pcs';

    protected $entityName = 'Product';
    protected $inUseMessage = 'This product has production runs or conversions and cannot be deleted. Deactivate it instead.';

    // The largest values the columns can hold (INT, DECIMAL(10,2)). Bigger numbers would be refused by MySQL
    // with a PDOException, which the user would see as a 500.
    private const MAX_INT = 2147483647;
    private const MAX_LENGTH_M = 99999999.99;

    public function __construct($dao = null)
    {
        parent::__construct($dao ?? new ProductDao());
    }

    public function add($product)
    {
        return parent::add($this->prepare($product));
    }

    public function update($product, $id)
    {
        return parent::update($this->prepare($product), $id);
    }

    protected function validate($product, $id = null)
    {
        Validator::required($product, ['code', 'name']);
        Validator::length($product['code'], 'code', 2, 20);
        Validator::length($product['name'], 'name', 2, 100);
        if (array_key_exists('unit', $product)) {
            Validator::oneOf($product['unit'], 'unit', [self::KG, self::PCS]);
        }
        // Checked here and not left to the INT column: MySQL would quietly round 300.5 mm to 301 and save it.
        foreach (['width_mm', 'thickness_um'] as $field) {
            if (isset($product[$field])) {
                Validator::integer($product[$field], $field, 1, self::MAX_INT);
            }
        }
        if (isset($product['length_m'])) {
            Validator::decimal($product['length_m'], 'length_m', 0.01, self::MAX_LENGTH_M);
        }
        if (isset($product['description'])) {
            Validator::length($product['description'], 'description', 1, 255);
        }
        if (array_key_exists('is_active', $product)) {
            Validator::boolean($product['is_active'], 'is_active');
        }

        // Runs and conversions store quantities in this unit: switching kg to pcs would turn 500 kg into 500 bags.
        if ($id !== null && array_key_exists('unit', $product) && $product['unit'] !== $this->dao->getById($id)['unit']) {
            throw new ValidationException('The unit cannot be changed. Add a new product instead.');
        }

        $existing = $this->dao->getByCode($product['code']);
        if ($existing && $existing['id'] != $id) {
            throw new ValidationException('A product with this code already exists.');
        }
    }

    private function prepare($product)
    {
        $product = $this->trimStrings($product, ['code', 'name', 'description']);
        // Only the optional columns: an empty code or name must stay "" so that required() rejects it.
        $product = $this->emptyToNull($product, ['width_mm', 'thickness_um', 'length_m', 'description']);
        return $this->normalizeBooleans($product, ['is_active']);
    }
}
