<?php
require_once __DIR__ . '/../DatabaseTestCase.php';
require_once __DIR__ . '/../../rest/services/MachineService.php';

final class MachineServiceTest extends DatabaseTestCase
{
    private const CODE = 'T-EX';

    private MachineService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MachineService();
    }

    private function newMachine(array $overrides = []): array
    {
        return array_merge(['name' => 'Test extruder', 'code' => self::CODE, 'machine_type' => 'extrusion'], $overrides);
    }

    /* ---------- add ---------- */

    public function testANewMachineIsActiveUnlessToldOtherwise(): void
    {
        $machine = $this->service->add($this->newMachine());

        $this->assertIsInt($machine['id']);
        $this->assertSame(1, $machine['is_active']);
    }

    public function testAddStoresTheNameAndCodeTrimmed(): void
    {
        $machine = $this->service->add($this->newMachine(['name' => '  Test extruder ', 'code' => ' T-EX  ']));

        $this->assertSame('Test extruder', $machine['name']);
        $this->assertSame(self::CODE, $machine['code']);
    }

    public function testAddRequiresACode(): void
    {
        $this->assertRejected(fn () => $this->service->add($this->newMachine(['code' => ' '])), "Field 'code' is required.");
    }

    public function testAddRejectsATooLongCode(): void
    {
        $this->assertRejected(
            fn () => $this->service->add($this->newMachine(['code' => str_repeat('X', 21)])),
            "Field 'code' must be between 2 and 20 characters long."
        );
    }

    public function testAddRejectsAnUnknownMachineType(): void
    {
        $this->assertRejected(
            fn () => $this->service->add($this->newMachine(['machine_type' => 'printing'])),
            "Field 'machine_type' must be one of: extrusion, conversion."
        );
    }

    // Without this check the UNIQUE index would refuse the insert with a PDOException, which the user sees as a 500.
    public function testAddRejectsTheCodeOfAnotherMachineInAnyCase(): void
    {
        $this->assertRejected(
            fn () => $this->service->add($this->newMachine(['code' => 'ex-01'])),
            'A machine with this code already exists.'
        );
    }

    /* ---------- update ---------- */

    public function testUpdateLetsAMachineKeepItsOwnCode(): void
    {
        $id = $this->service->add($this->newMachine())['id'];

        $updated = $this->service->update(['name' => 'Renamed extruder', 'code' => self::CODE], $id);

        $this->assertSame('Renamed extruder', $updated['name']);
    }

    public function testUpdateRejectsTheCodeOfAnotherMachine(): void
    {
        $id = $this->service->add($this->newMachine())['id'];

        $this->assertRejected(
            fn () => $this->service->update(['name' => 'Test extruder', 'code' => 'EX-01'], $id),
            'A machine with this code already exists.'
        );
    }

    public function testUpdateCanDeactivateAMachineWithFalseFromJson(): void
    {
        $id = $this->service->add($this->newMachine())['id'];

        $updated = $this->service->update(['name' => 'Test extruder', 'code' => self::CODE, 'is_active' => false], $id);

        $this->assertSame(0, $updated['is_active']);
    }

    // Runs, conversions, parameters and product links were all checked against the old type: an extruder turned into
    // a bag machine would keep its production runs and its output-only links.
    public function testUpdateCannotChangeTheMachineType(): void
    {
        $id = $this->service->add($this->newMachine())['id'];

        $this->assertRejected(
            fn () => $this->service->update($this->newMachine(['machine_type' => 'conversion']), $id),
            'The machine type cannot be changed. Add a new machine instead.'
        );
    }

    // The edit form shows the type locked, and a disabled field is not sent at all.
    public function testUpdateWithoutAMachineTypeKeepsTheType(): void
    {
        $id = $this->service->add($this->newMachine())['id'];

        $updated = $this->service->update(['name' => 'Test extruder', 'code' => self::CODE], $id);

        $this->assertSame('extrusion', $updated['machine_type']);
    }

    /* ---------- delete ---------- */

    // Parameters and product links describe the machine, they are not history: they go with it (ON DELETE CASCADE).
    public function testDeleteOfANewMachineAlsoRemovesItsParametersAndProductLinks(): void
    {
        $machineId = $this->createMachine('extrusion');
        $this->insertRow('machine_parameters', ['machine_id' => $machineId, 'name' => 'Screw speed']);
        $this->insertRow('machine_products', ['machine_id' => $machineId, 'product_id' => $this->createProduct(), 'role' => 'output']);

        $this->service->delete($machineId);

        $this->assertSame(0, $this->countRows('machines', 'id', $machineId));
        $this->assertSame(0, $this->countRows('machine_parameters', 'machine_id', $machineId));
        $this->assertSame(0, $this->countRows('machine_products', 'machine_id', $machineId));
    }

    public function testDeleteOfAMachineWithHistoryAsksToDeactivateInstead(): void
    {
        $machineId = $this->createMachine('extrusion');
        $this->createRun($machineId);

        $this->assertRejected(
            fn () => $this->service->delete($machineId),
            'This machine has runs, conversions or maintenance records and cannot be deleted. Deactivate it instead.'
        );
    }
}
