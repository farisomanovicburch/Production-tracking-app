<?php
require_once __DIR__ . '/../DatabaseTestCase.php';
require_once __DIR__ . '/../../rest/services/ConversionRunService.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class ConversionRunServiceTest extends DatabaseTestCase
{
    private ConversionRunService $service;
    private int $machineId;
    private int $parameterId;
    private int $inputProductId;
    private int $outputProductId;
    private int $userId;

    // A complete, valid setup: a bag machine with one active parameter that takes the film as input and makes the
    // bags as output, and an active user. Each test breaks exactly one piece of it.
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ConversionRunService();
        $this->machineId = $this->createMachine('conversion', 'Test bag machine');
        $this->parameterId = $this->insertRow('machine_parameters', ['machine_id' => $this->machineId, 'name' => 'Test weld time', 'display_order' => 1]);
        $this->inputProductId = $this->createProduct('Test film');
        $this->outputProductId = $this->createProduct('Test bag', 'pcs');
        $this->userId = $this->createUser('Test operator');

        $this->insertRow('machine_products', ['machine_id' => $this->machineId, 'product_id' => $this->inputProductId, 'role' => 'input']);
        $this->insertRow('machine_products', ['machine_id' => $this->machineId, 'product_id' => $this->outputProductId, 'role' => 'output']);
    }

    private function validConversion(array $overrides = []): array
    {
        return array_merge([
            'machine_id' => $this->machineId,
            'user_id' => $this->userId,
            'input_product_id' => $this->inputProductId,
            'output_product_id' => $this->outputProductId,
            'started_at' => '2026-10-22 08:00:00',
            'ended_at' => '2026-10-22 15:30:00',
            'input_kg' => '410',
            'output_kg' => '360',
            'bag_count' => '9000',
            'parameter_values' => [['machine_parameter_id' => $this->parameterId, 'value' => '0.5']],
        ], $overrides);
    }

    private function deactivate(string $table, int $id): void
    {
        $this->db->prepare("UPDATE $table SET is_active = FALSE WHERE id = :id")->execute(['id' => $id]);
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

    /* ---------- a valid conversion ---------- */

    public function testAddSavesTheConversionWithNamesAndCalculatedScrap(): void
    {
        $conversion = $this->service->add($this->validConversion());

        $this->assertSame(
            ['Test bag machine', 'Test film', 'Test bag', 'Test operator', '50.000', 9000],
            [$conversion['machine_name'], $conversion['input_product_name'], $conversion['output_product_name'],
                $conversion['user_name'], $conversion['scrap_kg'], $conversion['bag_count']]
        );
    }

    public function testAddAcceptsTheTimesADatetimeLocalFieldSends(): void
    {
        $conversion = $this->service->add($this->validConversion(['started_at' => '2026-10-22T08:00', 'ended_at' => '2026-10-22T15:30']));

        $this->assertSame(['2026-10-22 08:00:00', '2026-10-22 15:30:00'], [$conversion['started_at'], $conversion['ended_at']]);
    }

    public function testAddSavesEmptyNotesAsNull(): void
    {
        $this->assertNull($this->service->add($this->validConversion(['notes' => '  ']))['notes']);
    }

    // All input became bags: no scrap is a valid result, not an error.
    public function testAddAcceptsAnOutputEqualToTheInput(): void
    {
        $this->assertSame('0.000', $this->service->add($this->validConversion(['output_kg' => '410']))['scrap_kg']);
    }

    /* ---------- fields ---------- */

    public static function requiredFields(): array
    {
        return [
            'machine' => ['machine_id'], 'user' => ['user_id'], 'input product' => ['input_product_id'],
            'output product' => ['output_product_id'], 'start' => ['started_at'], 'end' => ['ended_at'],
            'input' => ['input_kg'], 'output' => ['output_kg'], 'bags' => ['bag_count'],
        ];
    }

    #[DataProvider('requiredFields')]
    public function testAddRequiresTheField(string $field): void
    {
        $this->assertRejected(fn () => $this->service->add($this->validConversion([$field => ''])), "Field '$field' is required.");
    }

    public function testAddRequiresSomeInput(): void
    {
        $this->assertRejected(
            fn () => $this->service->add($this->validConversion(['input_kg' => '0', 'output_kg' => '0'])),
            "Field 'input_kg' must be at least 0.001."
        );
    }

    public function testAddRejectsAPartialBag(): void
    {
        $this->assertRejected(fn () => $this->service->add($this->validConversion(['bag_count' => '4.5'])), "Field 'bag_count' must be a whole number.");
    }

    public function testAddRejectsMoreOutputThanInput(): void
    {
        $this->assertRejected(
            fn () => $this->service->add($this->validConversion(['output_kg' => '410.001'])),
            'The output cannot weigh more than the input.'
        );
    }

    public function testAddRejectsAConversionThatDoesNotEndAfterItStarts(): void
    {
        $this->assertRejected(
            fn () => $this->service->add($this->validConversion(['ended_at' => '2026-10-22 08:00:00'])),
            'The conversion must end after it starts.'
        );
    }

    /* ---------- machine ---------- */

    public function testAddRejectsAnUnknownMachine(): void
    {
        $this->assertRejected(fn () => $this->service->add($this->validConversion(['machine_id' => 999999])), 'The selected machine does not exist.');
    }

    public function testAddRejectsADeactivatedMachine(): void
    {
        $this->deactivate('machines', $this->machineId);

        $this->assertRejected(
            fn () => $this->service->add($this->validConversion()),
            'The selected machine is deactivated and cannot be used for new conversions.'
        );
    }

    public function testAddRejectsAnExtrusionMachine(): void
    {
        $this->assertRejected(
            fn () => $this->service->add($this->validConversion(['machine_id' => $this->createMachine('extrusion')])),
            'The selected machine is not a conversion machine.'
        );
    }

    /* ---------- products: the role is part of the rule ---------- */

    public function testAddRejectsAnUnknownInputProduct(): void
    {
        $this->assertRejected(
            fn () => $this->service->add($this->validConversion(['input_product_id' => 999999])),
            'The selected input product does not exist.'
        );
    }

    public function testAddRejectsADeactivatedInputProduct(): void
    {
        $this->deactivate('products', $this->inputProductId);

        $this->assertRejected(
            fn () => $this->service->add($this->validConversion()),
            'The selected input product is deactivated and cannot be used for new conversions.'
        );
    }

    public function testAddRejectsAnInputProductThatTheMachineDoesNotTake(): void
    {
        $this->assertRejected(
            fn () => $this->service->add($this->validConversion(['input_product_id' => $this->outputProductId])),
            'The selected machine does not take the selected input product.'
        );
    }

    public function testAddRejectsAnUnknownOutputProduct(): void
    {
        $this->assertRejected(
            fn () => $this->service->add($this->validConversion(['output_product_id' => 999999])),
            'The selected output product does not exist.'
        );
    }

    public function testAddRejectsADeactivatedOutputProduct(): void
    {
        $this->deactivate('products', $this->outputProductId);

        $this->assertRejected(
            fn () => $this->service->add($this->validConversion()),
            'The selected output product is deactivated and cannot be used for new conversions.'
        );
    }

    public function testAddRejectsAnOutputProductThatTheMachineDoesNotMake(): void
    {
        $this->assertRejected(
            fn () => $this->service->add($this->validConversion(['output_product_id' => $this->inputProductId])),
            'The selected machine does not make the selected output product.'
        );
    }

    /* ---------- user ---------- */

    public function testAddRejectsAnUnknownUser(): void
    {
        $this->assertRejected(fn () => $this->service->add($this->validConversion(['user_id' => 999999])), 'The selected user does not exist.');
    }

    public function testAddRejectsADeactivatedUser(): void
    {
        $this->deactivate('users', $this->userId);

        $this->assertRejected(
            fn () => $this->service->add($this->validConversion()),
            'The selected user is deactivated and cannot log new conversions.'
        );
    }

    /* ---------- parameter values, saved with the conversion ---------- */

    public function testAddSavesTheValuesWithTheConversion(): void
    {
        $values = $this->service->add($this->validConversion())['parameter_values'];

        $this->assertSame([['Test weld time', '0.500']], array_map(fn ($v) => [$v['parameter_name'], $v['value']], $values));
    }

    public function testAddRequiresAValueForEveryActiveParameter(): void
    {
        $this->insertRow('machine_parameters', ['machine_id' => $this->machineId, 'name' => 'Test speed', 'display_order' => 2]);

        $this->assertRejected(fn () => $this->service->add($this->validConversion()), 'Missing values for these parameters: Test speed.');
    }

    public function testAddDoesNotAskForADeactivatedParameter(): void
    {
        $this->insertRow('machine_parameters', ['machine_id' => $this->machineId, 'name' => 'Old knife', 'is_active' => 0]);

        $this->assertCount(1, $this->service->add($this->validConversion())['parameter_values']);
    }

    // The value is written after the conversion row, so the rollback has to remove the row again.
    public function testAddRejectsAValueForAParameterOfAnotherMachineAndSavesNothing(): void
    {
        $foreignParameterId = $this->insertRow('machine_parameters', ['machine_id' => $this->createMachine('conversion'), 'name' => 'Foreign']);

        $this->assertRejected(
            fn () => $this->service->add($this->validConversion(['parameter_values' => [
                ['machine_parameter_id' => $this->parameterId, 'value' => '0.5'],
                ['machine_parameter_id' => $foreignParameterId, 'value' => '1'],
            ]])),
            "The selected parameter does not belong to this conversion's machine."
        );

        $this->assertSame(0, $this->countRows('conversion_runs', 'machine_id', $this->machineId));
    }

    public function testAddRejectsParameterValuesThatAreNotAList(): void
    {
        $this->assertRejected(
            fn () => $this->service->add($this->validConversion(['parameter_values' => ['machine_parameter_id' => $this->parameterId]])),
            "Field 'parameter_values' must be a list of objects."
        );
    }

    /* ---------- read ---------- */

    public function testGetByIdIncludesTheValues(): void
    {
        $id = $this->service->add($this->validConversion())['id'];

        $this->assertCount(1, $this->service->getById($id)['parameter_values']);
    }

    public function testGetAllStaysFlat(): void
    {
        $this->service->add($this->validConversion());

        $this->assertArrayNotHasKey('parameter_values', $this->service->getAll()[0]);
    }

    public function testGetByIdOfAnUnknownConversionIsNotFound(): void
    {
        $this->assertNotFound(fn () => $this->service->getById(999999), 'Conversion run not found.');
    }

    /* ---------- update ---------- */

    public function testUpdateChangesTheTimesAndAmounts(): void
    {
        $id = $this->service->add($this->validConversion())['id'];

        $conversion = $this->service->update(['ended_at' => '2026-10-22T16:00', 'output_kg' => '380', 'bag_count' => '9500'], $id);

        $this->assertSame(
            ['2026-10-22 16:00:00', '380.000', 9500, '30.000'],
            [$conversion['ended_at'], $conversion['output_kg'], $conversion['bag_count'], $conversion['scrap_kg']]
        );
    }

    public function testUpdateKeepsTheFieldsThatAreLeftOut(): void
    {
        $id = $this->service->add($this->validConversion())['id'];

        $conversion = $this->service->update(['notes' => 'Only a note'], $id);

        $this->assertSame(['410.000', '360.000', 'Only a note'], [$conversion['input_kg'], $conversion['output_kg'], $conversion['notes']]);
    }

    public function testUpdateIgnoresTheMachineProductsAndUser(): void
    {
        $id = $this->service->add($this->validConversion())['id'];

        $conversion = $this->service->update([
            'machine_id' => $this->createMachine('conversion'), 'user_id' => $this->createUser(),
            'input_product_id' => $this->createProduct(), 'output_product_id' => $this->createProduct(),
        ], $id);

        $this->assertSame(
            [$this->machineId, $this->userId, $this->inputProductId, $this->outputProductId],
            [$conversion['machine_id'], $conversion['user_id'], $conversion['input_product_id'], $conversion['output_product_id']]
        );
    }

    // Only output_kg is sent; the saved input (410) is what it is compared with.
    public function testUpdateChecksTheOutputAgainstTheSavedInput(): void
    {
        $id = $this->service->add($this->validConversion())['id'];

        $this->assertRejected(fn () => $this->service->update(['output_kg' => '411'], $id), 'The output cannot weigh more than the input.');
    }

    public function testUpdateIsAllowedAfterTheMachineIsDeactivated(): void
    {
        $id = $this->service->add($this->validConversion())['id'];
        $this->deactivate('machines', $this->machineId);

        $this->assertSame('Corrected', $this->service->update(['notes' => 'Corrected'], $id)['notes']);
    }

    public function testUpdateOfAnUnknownConversionIsNotFound(): void
    {
        $this->assertNotFound(fn () => $this->service->update(['notes' => 'x'], 999999), 'Conversion run not found.');
    }

    /* ---------- delete ---------- */

    public function testDeleteRemovesTheConversionAndItsValues(): void
    {
        $id = $this->service->add($this->validConversion())['id'];

        $this->service->delete($id);

        $this->assertSame(
            [0, 0],
            [$this->countRows('conversion_runs', 'id', $id), $this->countRows('conversion_run_parameter_values', 'conversion_run_id', $id)]
        );
    }

    public function testDeleteOfAnUnknownConversionIsNotFound(): void
    {
        $this->assertNotFound(fn () => $this->service->delete(999999), 'Conversion run not found.');
    }
}
