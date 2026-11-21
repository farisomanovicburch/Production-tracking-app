<?php
require_once __DIR__ . '/../DatabaseTestCase.php';
require_once __DIR__ . '/../../rest/services/RunParameterValueService.php';

// Values added to or corrected on a run that already exists. The values sent with a new run are tested in
// ProductionRunServiceTest.
final class RunParameterValueServiceTest extends DatabaseTestCase
{
    private RunParameterValueService $service;
    private int $machineId;
    private int $parameterId;
    private int $runId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RunParameterValueService();
        $this->machineId = $this->createMachine('extrusion');
        $this->parameterId = $this->insertRow('machine_parameters', ['machine_id' => $this->machineId, 'name' => 'Test zone']);
        $this->runId = $this->createRun($this->machineId);
    }

    private function addValue($value = '180', ?int $parameterId = null): array
    {
        return $this->service->addToRun($this->runId, ['machine_parameter_id' => $parameterId ?? $this->parameterId, 'value' => $value]);
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

    public function testGetByRunListsTheValuesWithParameterNames(): void
    {
        $this->addValue();

        $this->assertSame(['Test zone'], array_column($this->service->getByRun($this->runId), 'parameter_name'));
    }

    public function testGetByRunOfAnUnknownRunIsNotFound(): void
    {
        $this->assertNotFound(fn () => $this->service->getByRun(999999), 'Production run not found.');
    }

    /* ---------- add ---------- */

    public function testAddToRunSavesTheValueOnThatRun(): void
    {
        $value = $this->addValue('181.5');

        $this->assertSame([$this->runId, '181.500'], [$value['production_run_id'], $value['value']]);
    }

    public function testAddToRunIgnoresARunIdInTheBody(): void
    {
        $value = $this->service->addToRun($this->runId, [
            'machine_parameter_id' => $this->parameterId, 'value' => 1, 'production_run_id' => $this->createRun($this->machineId),
        ]);

        $this->assertSame($this->runId, $value['production_run_id']);
    }

    public function testAddToAnUnknownRunIsNotFound(): void
    {
        $this->assertNotFound(
            fn () => $this->service->addToRun(999999, ['machine_parameter_id' => $this->parameterId, 'value' => 1]),
            'Production run not found.'
        );
    }

    public function testAddToRunRequiresAValue(): void
    {
        $this->assertRejected(fn () => $this->addValue(''), "Field 'value' is required.");
    }

    public function testAddToRunRejectsAValueThatIsNotANumber(): void
    {
        $this->assertRejected(fn () => $this->addValue('hot'), "Field 'value' must be a number.");
    }

    public function testAddToRunRejectsAnUnknownParameter(): void
    {
        $this->assertRejected(fn () => $this->addValue('1', 999999), 'The selected parameter does not exist.');
    }

    public function testAddToRunRejectsAParameterOfAnotherMachine(): void
    {
        $foreignParameterId = $this->insertRow('machine_parameters', ['machine_id' => $this->createMachine('extrusion'), 'name' => 'Foreign zone']);

        $this->assertRejected(fn () => $this->addValue('1', $foreignParameterId), "The selected parameter does not belong to this run's machine.");
    }

    public function testAddToRunRejectsADeactivatedParameter(): void
    {
        $oldParameterId = $this->insertRow('machine_parameters', ['machine_id' => $this->machineId, 'name' => 'Old zone', 'is_active' => 0]);

        $this->assertRejected(fn () => $this->addValue('1', $oldParameterId), 'The selected parameter is deactivated.');
    }

    public function testAddToRunRejectsASecondValueForTheSameParameter(): void
    {
        $this->addValue();

        $this->assertRejected(fn () => $this->addValue('185'), 'This run already has a value for this parameter.');
    }

    public function testTheInheritedAddIsAMistakeInTheCode(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Use addToRun(): the run comes from the URL.');

        $this->service->add(['production_run_id' => $this->runId, 'machine_parameter_id' => $this->parameterId, 'value' => 1]);
    }

    /* ---------- update ---------- */

    public function testUpdateChangesTheValue(): void
    {
        $id = $this->addValue()['id'];

        $this->assertSame('190.000', $this->service->update(['value' => '190'], $id)['value']);
    }

    // The parameter is what the value means: a different parameter is a different value (delete it, add another).
    public function testUpdateIgnoresAnotherParameter(): void
    {
        $id = $this->addValue()['id'];
        $otherParameterId = $this->insertRow('machine_parameters', ['machine_id' => $this->machineId, 'name' => 'Test speed']);

        $value = $this->service->update(['machine_parameter_id' => $otherParameterId, 'value' => '60'], $id);

        $this->assertSame($this->parameterId, $value['machine_parameter_id']);
    }

    // The run is history: a typo in a value of a since-deactivated parameter can still be corrected.
    public function testUpdateIsAllowedAfterTheParameterIsDeactivated(): void
    {
        $id = $this->addValue()['id'];
        $this->db->prepare('UPDATE machine_parameters SET is_active = FALSE WHERE id = :id')->execute(['id' => $this->parameterId]);

        $this->assertSame('179.000', $this->service->update(['value' => '179'], $id)['value']);
    }

    public function testUpdateRejectsAValueThatIsNotANumber(): void
    {
        $id = $this->addValue()['id'];

        $this->assertRejected(fn () => $this->service->update(['value' => 'hot'], $id), "Field 'value' must be a number.");
    }

    public function testUpdateOfAnUnknownValueIsNotFound(): void
    {
        $this->assertNotFound(fn () => $this->service->update(['value' => '1'], 999999), 'Run parameter value not found.');
    }

    /* ---------- delete ---------- */

    public function testDeleteRemovesTheValue(): void
    {
        $id = $this->addValue()['id'];

        $this->service->delete($id);

        $this->assertSame(0, $this->countRows('run_parameter_values', 'id', $id));
    }

    public function testDeleteOfAnUnknownValueIsNotFound(): void
    {
        $this->assertNotFound(fn () => $this->service->delete(999999), 'Run parameter value not found.');
    }
}
