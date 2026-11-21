<?php
require_once __DIR__ . '/../DatabaseTestCase.php';
require_once __DIR__ . '/../../rest/services/ConversionRunParameterValueService.php';

// Values added to or corrected on a conversion that already exists. The values sent with a new conversion are
// tested in ConversionRunServiceTest.
final class ConversionRunParameterValueServiceTest extends DatabaseTestCase
{
    private ConversionRunParameterValueService $service;
    private int $machineId;
    private int $parameterId;
    private int $conversionId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ConversionRunParameterValueService();
        $this->machineId = $this->createMachine('conversion');
        $this->parameterId = $this->insertRow('machine_parameters', ['machine_id' => $this->machineId, 'name' => 'Test weld time']);
        $this->conversionId = $this->createConversion();
    }

    private function createConversion(): int
    {
        return $this->insertRow('conversion_runs', [
            'machine_id' => $this->machineId, 'user_id' => $this->createUser(),
            'input_product_id' => $this->createProduct(), 'output_product_id' => $this->createProduct(),
            'started_at' => '2026-10-22 08:00:00', 'ended_at' => '2026-10-22 15:30:00',
            'input_kg' => 410, 'output_kg' => 360, 'bag_count' => 9000,
        ]);
    }

    private function addValue($value = '0.5', ?int $parameterId = null): array
    {
        return $this->service->addToConversion($this->conversionId, ['machine_parameter_id' => $parameterId ?? $this->parameterId, 'value' => $value]);
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

    public function testGetByConversionListsTheValuesWithParameterNames(): void
    {
        $this->addValue();

        $this->assertSame(['Test weld time'], array_column($this->service->getByConversion($this->conversionId), 'parameter_name'));
    }

    public function testGetByConversionOfAnUnknownConversionIsNotFound(): void
    {
        $this->assertNotFound(fn () => $this->service->getByConversion(999999), 'Conversion run not found.');
    }

    /* ---------- add ---------- */

    public function testAddToConversionSavesTheValueOnThatConversion(): void
    {
        $value = $this->addValue('0.65');

        $this->assertSame([$this->conversionId, '0.650'], [$value['conversion_run_id'], $value['value']]);
    }

    public function testAddToConversionIgnoresAConversionIdInTheBody(): void
    {
        $value = $this->service->addToConversion($this->conversionId, [
            'machine_parameter_id' => $this->parameterId, 'value' => 1, 'conversion_run_id' => $this->createConversion(),
        ]);

        $this->assertSame($this->conversionId, $value['conversion_run_id']);
    }

    public function testAddToAnUnknownConversionIsNotFound(): void
    {
        $this->assertNotFound(
            fn () => $this->service->addToConversion(999999, ['machine_parameter_id' => $this->parameterId, 'value' => 1]),
            'Conversion run not found.'
        );
    }

    public function testAddToConversionRequiresAValue(): void
    {
        $this->assertRejected(fn () => $this->addValue(''), "Field 'value' is required.");
    }

    public function testAddToConversionRejectsAValueThatIsNotANumber(): void
    {
        $this->assertRejected(fn () => $this->addValue('short'), "Field 'value' must be a number.");
    }

    public function testAddToConversionRejectsAnUnknownParameter(): void
    {
        $this->assertRejected(fn () => $this->addValue('1', 999999), 'The selected parameter does not exist.');
    }

    public function testAddToConversionRejectsAParameterOfAnotherMachine(): void
    {
        $foreignParameterId = $this->insertRow('machine_parameters', ['machine_id' => $this->createMachine('conversion'), 'name' => 'Foreign']);

        $this->assertRejected(fn () => $this->addValue('1', $foreignParameterId), "The selected parameter does not belong to this conversion's machine.");
    }

    public function testAddToConversionRejectsADeactivatedParameter(): void
    {
        $oldParameterId = $this->insertRow('machine_parameters', ['machine_id' => $this->machineId, 'name' => 'Old knife', 'is_active' => 0]);

        $this->assertRejected(fn () => $this->addValue('1', $oldParameterId), 'The selected parameter is deactivated.');
    }

    public function testAddToConversionRejectsASecondValueForTheSameParameter(): void
    {
        $this->addValue();

        $this->assertRejected(fn () => $this->addValue('0.6'), 'This conversion already has a value for this parameter.');
    }

    public function testTheInheritedAddIsAMistakeInTheCode(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Use addToConversion(): the conversion comes from the URL.');

        $this->service->add(['conversion_run_id' => $this->conversionId, 'machine_parameter_id' => $this->parameterId, 'value' => 1]);
    }

    /* ---------- update ---------- */

    public function testUpdateChangesTheValue(): void
    {
        $id = $this->addValue()['id'];

        $this->assertSame('0.700', $this->service->update(['value' => '0.7'], $id)['value']);
    }

    public function testUpdateIgnoresAnotherParameter(): void
    {
        $id = $this->addValue()['id'];
        $otherParameterId = $this->insertRow('machine_parameters', ['machine_id' => $this->machineId, 'name' => 'Test speed']);

        $this->assertSame($this->parameterId, $this->service->update(['machine_parameter_id' => $otherParameterId, 'value' => '80'], $id)['machine_parameter_id']);
    }

    public function testUpdateIsAllowedAfterTheParameterIsDeactivated(): void
    {
        $id = $this->addValue()['id'];
        $this->db->prepare('UPDATE machine_parameters SET is_active = FALSE WHERE id = :id')->execute(['id' => $this->parameterId]);

        $this->assertSame('0.450', $this->service->update(['value' => '0.45'], $id)['value']);
    }

    public function testUpdateRejectsAValueThatIsNotANumber(): void
    {
        $id = $this->addValue()['id'];

        $this->assertRejected(fn () => $this->service->update(['value' => 'short'], $id), "Field 'value' must be a number.");
    }

    public function testUpdateOfAnUnknownValueIsNotFound(): void
    {
        $this->assertNotFound(fn () => $this->service->update(['value' => '1'], 999999), 'Conversion parameter value not found.');
    }

    /* ---------- delete ---------- */

    public function testDeleteRemovesTheValue(): void
    {
        $id = $this->addValue()['id'];

        $this->service->delete($id);

        $this->assertSame(0, $this->countRows('conversion_run_parameter_values', 'id', $id));
    }

    public function testDeleteOfAnUnknownValueIsNotFound(): void
    {
        $this->assertNotFound(fn () => $this->service->delete(999999), 'Conversion parameter value not found.');
    }
}
