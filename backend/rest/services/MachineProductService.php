<?php
require_once __DIR__ . '/BaseService.php';
require_once __DIR__ . '/MachineService.php';
require_once __DIR__ . '/../dao/MachineProductDao.php';
require_once __DIR__ . '/../dao/ProductDao.php';

/*
 * Which products a machine works with. A link is only added or removed, never edited: changing the product or the
 * role of a link is the same as removing it and adding another one.
 */
class MachineProductService extends BaseService
{
    public const INPUT = 'input';
    public const OUTPUT = 'output';

    protected $entityName = 'Machine product link';

    // machine_id comes from the URL, like for parameters.
    private const ACCEPTED_FIELDS = ['product_id', 'role'];

    private $machineService;
    private $productDao;

    public function __construct($dao = null, $machineService = null, $productDao = null)
    {
        parent::__construct($dao ?? new MachineProductDao());
        $this->machineService = $machineService ?? new MachineService();
        $this->productDao = $productDao ?? new ProductDao();
    }

    public function getByMachine($machineId)
    {
        $this->machineService->getById($machineId);
        return $this->dao->getByMachine($machineId);
    }

    public function addToMachine($machineId, $link)
    {
        $this->machineService->getById($machineId);
        $link = array_intersect_key($link, array_flip(self::ACCEPTED_FIELDS));
        $link['machine_id'] = $machineId;
        return parent::add($link);
    }

    protected function validate($link, $id = null)
    {
        Validator::required($link, ['product_id', 'role']);
        Validator::integer($link['product_id'], 'product_id', 1);
        Validator::oneOf($link['role'], 'role', [self::INPUT, self::OUTPUT]);

        // The product id comes from the body, so a wrong one is a bad request (400), not a missing page (404).
        if (!$this->productDao->getById($link['product_id'])) {
            throw new ValidationException('The selected product does not exist.');
        }

        // An extruder melts granulate (a material, not a product) into film: no product goes into it.
        $machine = $this->machineService->getById($link['machine_id']);
        if ($machine['machine_type'] === MachineService::EXTRUSION && $link['role'] === self::INPUT) {
            throw new ValidationException('An extrusion machine only makes products: it cannot have input products.');
        }

        if ($this->dao->getLink($link['machine_id'], $link['product_id'], $link['role'])) {
            throw new ValidationException("This product is already linked to the machine as {$link['role']}.");
        }
    }
}
