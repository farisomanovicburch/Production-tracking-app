<?php
require_once __DIR__ . '/../DatabaseTestCase.php';
require_once __DIR__ . '/../../rest/services/MachineProductService.php';

final class MachineProductServiceTest extends DatabaseTestCase
{
    private MachineProductService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MachineProductService();
    }

    private function assertNotFound(callable $action, string $message): void
    {
        try {
            $action();
            $this->fail("Accepted, expected: $message");
        } catch (NotFoundException $exception) {
            $this->assertSame($message, $exception->getMessage());
        }
    }

    /* ---------- list ---------- */

    public function testGetByMachineListsTheLinkedProductsWithTheirNames(): void
    {
        $machineId = $this->createMachine('extrusion');
        $this->service->addToMachine($machineId, ['product_id' => $this->createProduct('Test film'), 'role' => 'output']);

        $this->assertSame(['Test film'], array_column($this->service->getByMachine($machineId), 'product_name'));
    }

    public function testGetByMachineOfAnUnknownMachineIsNotFound(): void
    {
        $this->assertNotFound(fn () => $this->service->getByMachine(999999), 'Machine not found.');
    }

    /* ---------- add ---------- */

    public function testAConversionMachineTakesInputAndOutputProducts(): void
    {
        $machineId = $this->createMachine('conversion');

        $input = $this->service->addToMachine($machineId, ['product_id' => $this->createProduct(), 'role' => 'input']);
        $output = $this->service->addToMachine($machineId, ['product_id' => $this->createProduct(), 'role' => 'output']);

        $this->assertSame([$machineId, 'input'], [$input['machine_id'], $input['role']]);
        $this->assertSame([$machineId, 'output'], [$output['machine_id'], $output['role']]);
    }

    // An extruder melts granulate (a material, not a product) into film: no product goes into it.
    public function testAnExtrusionMachineTakesNoInputProducts(): void
    {
        $machineId = $this->createMachine('extrusion');

        $this->assertRejected(
            fn () => $this->service->addToMachine($machineId, ['product_id' => $this->createProduct(), 'role' => 'input']),
            'An extrusion machine only makes products: it cannot have input products.'
        );
    }

    public function testAddToAnUnknownMachineIsNotFound(): void
    {
        $this->assertNotFound(
            fn () => $this->service->addToMachine(999999, ['product_id' => $this->createProduct(), 'role' => 'output']),
            'Machine not found.'
        );
    }

    public function testAddRequiresARole(): void
    {
        $this->assertRejected(
            fn () => $this->service->addToMachine($this->createMachine('conversion'), ['product_id' => $this->createProduct()]),
            "Field 'role' is required."
        );
    }

    public function testAddRejectsAnUnknownRole(): void
    {
        $this->assertRejected(
            fn () => $this->service->addToMachine($this->createMachine('conversion'), ['product_id' => $this->createProduct(), 'role' => 'both']),
            "Field 'role' must be one of: input, output."
        );
    }

    public function testAddRejectsAProductIdThatIsNotAWholeNumber(): void
    {
        $this->assertRejected(
            fn () => $this->service->addToMachine($this->createMachine('extrusion'), ['product_id' => 'film', 'role' => 'output']),
            "Field 'product_id' must be a whole number."
        );
    }

    // The product comes from the body, not the URL: a wrong id is a bad request (400), not a missing page (404).
    public function testAddRejectsAProductThatDoesNotExist(): void
    {
        $this->assertRejected(
            fn () => $this->service->addToMachine($this->createMachine('extrusion'), ['product_id' => 999999, 'role' => 'output']),
            'The selected product does not exist.'
        );
    }

    public function testAddRejectsALinkThatAlreadyExists(): void
    {
        $machineId = $this->createMachine('extrusion');
        $productId = $this->createProduct();
        $this->service->addToMachine($machineId, ['product_id' => $productId, 'role' => 'output']);

        $this->assertRejected(
            fn () => $this->service->addToMachine($machineId, ['product_id' => $productId, 'role' => 'output']),
            'This product is already linked to the machine as output.'
        );
    }

    public function testAddToMachineIgnoresAMachineIdInTheBody(): void
    {
        $machineId = $this->createMachine('extrusion');

        $link = $this->service->addToMachine(
            $machineId,
            ['product_id' => $this->createProduct(), 'role' => 'output', 'machine_id' => $this->createMachine('conversion')]
        );

        $this->assertSame($machineId, $link['machine_id']);
    }

    /* ---------- methods inherited from BaseService that would skip the rules ---------- */

    // BaseService::add() would skip addToMachine(): no "Machine not found." check and no allow-list.
    public function testAddWithoutTheMachineFromTheUrlIsAProgrammingError(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Use addToMachine(): the machine comes from the URL.');

        $this->service->add(['machine_id' => $this->createMachine('extrusion'), 'product_id' => $this->createProduct(), 'role' => 'output']);
    }

    // A link is removed and added again instead. BaseService::update() has no allow-list, so it could even move the
    // link to another machine.
    public function testALinkCannotBeUpdated(): void
    {
        $productId = $this->createProduct();
        $linkId = $this->service->addToMachine($this->createMachine('extrusion'), ['product_id' => $productId, 'role' => 'output'])['id'];

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A machine product link is not edited: remove it and add a new one.');

        $this->service->update(['product_id' => $productId, 'role' => 'output'], $linkId);
    }

    /* ---------- delete ---------- */

    public function testDeleteRemovesTheLinkButNotTheProduct(): void
    {
        $productId = $this->createProduct();
        $linkId = $this->service->addToMachine($this->createMachine('extrusion'), ['product_id' => $productId, 'role' => 'output'])['id'];

        $this->service->delete($linkId);

        $this->assertSame(0, $this->countRows('machine_products', 'id', $linkId));
        $this->assertSame(1, $this->countRows('products', 'id', $productId));
    }

    public function testDeleteOfAnUnknownLinkIsNotFound(): void
    {
        $this->assertNotFound(fn () => $this->service->delete(999999), 'Machine product link not found.');
    }
}
