<?php
require_once __DIR__ . '/../DatabaseTestCase.php';
require_once __DIR__ . '/../../rest/services/MachineParameterService.php';

final class MachineParameterServiceTest extends DatabaseTestCase
{
    private const NAME = 'Screw speed';

    private MachineParameterService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MachineParameterService();
    }

    private function addParameter(int $machineId, array $overrides = []): array
    {
        return $this->service->addToMachine($machineId, array_merge(['name' => self::NAME, 'unit' => 'rpm'], $overrides));
    }

    private function assertMachineNotFound(callable $action): void
    {
        try {
            $action();
            $this->fail('Accepted, expected: Machine not found.');
        } catch (NotFoundException $exception) {
            $this->assertSame('Machine not found.', $exception->getMessage());
        }
    }

    /* ---------- list ---------- */

    public function testGetByMachineListsThatMachinesParameters(): void
    {
        $machineId = $this->createMachine('extrusion');
        $this->addParameter($machineId);

        $this->assertSame([self::NAME], array_column($this->service->getByMachine($machineId), 'name'));
    }

    // An empty list would hide a wrong id in the URL: "this machine has no parameters" is not the same answer.
    public function testGetByMachineOfAnUnknownMachineIsNotFound(): void
    {
        $this->assertMachineNotFound(fn () => $this->service->getByMachine(999999));
    }

    /* ---------- add ---------- */

    public function testAddToMachineSavesTheParameterOnThatMachine(): void
    {
        $machineId = $this->createMachine('extrusion');

        $parameter = $this->addParameter($machineId);

        $this->assertSame($machineId, $parameter['machine_id']);
        $this->assertSame(self::NAME, $parameter['name']);
        $this->assertSame(1, $parameter['is_active']);
    }

    // The machine comes from the URL; a machine_id in the body must not be able to point somewhere else.
    public function testAddToMachineIgnoresAMachineIdInTheBody(): void
    {
        $machineId = $this->createMachine('extrusion');

        $parameter = $this->addParameter($machineId, ['machine_id' => $this->createMachine('extrusion')]);

        $this->assertSame($machineId, $parameter['machine_id']);
    }

    public function testAddToAnUnknownMachineIsNotFound(): void
    {
        $this->assertMachineNotFound(fn () => $this->addParameter(999999));
    }

    // The column's default is 0, which would put every new parameter at the top of the run form.
    public function testANewParameterGoesToTheEndOfTheListUnlessAnOrderIsGiven(): void
    {
        $machineId = $this->createMachine('extrusion');
        $this->addParameter($machineId, ['name' => 'First', 'display_order' => 1]);
        $this->addParameter($machineId, ['name' => 'Fifth', 'display_order' => 5]);

        $parameter = $this->addParameter($machineId);

        $this->assertSame(6, $parameter['display_order']);
    }

    public function testTheFirstParameterOfAMachineIsNumberOne(): void
    {
        $this->assertSame(1, $this->addParameter($this->createMachine('extrusion'))['display_order']);
    }

    public function testAddRejectsANegativeDisplayOrder(): void
    {
        $this->assertRejected(
            fn () => $this->addParameter($this->createMachine('extrusion'), ['display_order' => -1]),
            "Field 'display_order' must be at least 0."
        );
    }

    // Some parameters are plain numbers; the column wants NULL, not an empty string.
    public function testAddStoresAnEmptyUnitAsNull(): void
    {
        $this->assertNull($this->addParameter($this->createMachine('extrusion'), ['unit' => ''])['unit']);
    }

    public function testAddRequiresAName(): void
    {
        $this->assertRejected(
            fn () => $this->addParameter($this->createMachine('extrusion'), ['name' => '']),
            "Field 'name' is required."
        );
    }

    public function testAddRejectsANameTheMachineAlreadyHasInAnyCase(): void
    {
        $machineId = $this->createMachine('extrusion');
        $this->addParameter($machineId);

        $this->assertRejected(
            fn () => $this->addParameter($machineId, ['name' => 'SCREW SPEED']),
            'This machine already has a parameter with this name.'
        );
    }

    public function testAnotherMachineMayUseTheSameName(): void
    {
        $this->addParameter($this->createMachine('extrusion'));

        $parameter = $this->addParameter($this->createMachine('extrusion'));

        $this->assertSame(self::NAME, $parameter['name']);
    }

    /* ---------- update ---------- */

    public function testUpdateLetsAParameterKeepItsOwnName(): void
    {
        $id = $this->addParameter($this->createMachine('extrusion'))['id'];

        $updated = $this->service->update(['name' => self::NAME, 'unit' => 'min-1'], $id);

        $this->assertSame('min-1', $updated['unit']);
    }

    public function testUpdateRejectsTheNameOfAnotherParameterOfTheSameMachine(): void
    {
        $machineId = $this->createMachine('extrusion');
        $this->addParameter($machineId, ['name' => 'Zone 1 temperature']);
        $id = $this->addParameter($machineId)['id'];

        $this->assertRejected(
            fn () => $this->service->update(['name' => 'Zone 1 temperature'], $id),
            'This machine already has a parameter with this name.'
        );
    }

    // Values already saved for this parameter belong to runs of this machine.
    public function testUpdateCannotMoveAParameterToAnotherMachine(): void
    {
        $machineId = $this->createMachine('extrusion');
        $id = $this->addParameter($machineId)['id'];

        $updated = $this->service->update(['name' => self::NAME, 'machine_id' => $this->createMachine('extrusion')], $id);

        $this->assertSame($machineId, $updated['machine_id']);
    }

    public function testUpdateCanDeactivateAParameter(): void
    {
        $id = $this->addParameter($this->createMachine('extrusion'))['id'];

        $this->assertSame(0, $this->service->update(['name' => self::NAME, 'is_active' => false], $id)['is_active']);
    }

    public function testUpdateOfAnUnknownParameterIsNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Machine parameter not found.');

        $this->service->update(['name' => self::NAME], 999999);
    }

    /* ---------- delete ---------- */

    public function testDeleteOfAParameterWithSavedValuesAsksToDeactivateInstead(): void
    {
        $machineId = $this->createMachine('extrusion');
        $id = $this->addParameter($machineId)['id'];
        $this->insertRow('run_parameter_values', ['production_run_id' => $this->createRun($machineId), 'machine_parameter_id' => $id, 'value' => 60]);

        $this->assertRejected(
            fn () => $this->service->delete($id),
            'Runs or conversions have saved values for this parameter, so it cannot be deleted. Deactivate it instead.'
        );
    }
}
