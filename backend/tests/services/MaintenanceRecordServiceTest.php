<?php
require_once __DIR__ . '/../DatabaseTestCase.php';
require_once __DIR__ . '/../../rest/services/MaintenanceRecordService.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class MaintenanceRecordServiceTest extends DatabaseTestCase
{
    private MaintenanceRecordService $service;
    private int $machineId;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MaintenanceRecordService();
        $this->machineId = $this->createMachine('extrusion', 'Test extruder');
        $this->userId = $this->createUser('Test mechanic');
    }

    private function validRecord(array $overrides = []): array
    {
        return array_merge([
            'machine_id' => $this->machineId,
            'user_id' => $this->userId,
            'maintenance_type' => 'corrective',
            'started_at' => '2026-10-21 10:15:00',
            'ended_at' => '2026-10-21 12:00:00',
            'description' => 'Replaced a heater band on zone 3',
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

    /* ---------- add ---------- */

    public function testAddSavesTheRecordAndReturnsItWithTheNames(): void
    {
        $record = $this->service->add($this->validRecord());

        $this->assertSame(
            ['Test extruder', 'Test mechanic', 'corrective', 'Replaced a heater band on zone 3'],
            [$record['machine_name'], $record['user_name'], $record['maintenance_type'], $record['description']]
        );
    }

    public function testAddAcceptsTheTimesADatetimeLocalFieldSends(): void
    {
        $record = $this->service->add($this->validRecord(['started_at' => '2026-10-21T10:15', 'ended_at' => '2026-10-21T12:00']));

        $this->assertSame(['2026-10-21 10:15:00', '2026-10-21 12:00:00'], [$record['started_at'], $record['ended_at']]);
    }

    public function testAddTrimsTheTextAndSavesEmptyNotesAsNull(): void
    {
        $record = $this->service->add($this->validRecord(['description' => '  Gearbox oil change ', 'notes' => '']));

        $this->assertSame(['Gearbox oil change', null], [$record['description'], $record['notes']]);
    }

    public static function requiredFields(): array
    {
        return [
            'machine' => ['machine_id'], 'user' => ['user_id'], 'type' => ['maintenance_type'],
            'start' => ['started_at'], 'end' => ['ended_at'], 'description' => ['description'],
        ];
    }

    #[DataProvider('requiredFields')]
    public function testAddRequiresTheField(string $field): void
    {
        $this->assertRejected(fn () => $this->service->add($this->validRecord([$field => ' '])), "Field '$field' is required.");
    }

    public function testAddRejectsAnUnknownType(): void
    {
        $this->assertRejected(
            fn () => $this->service->add($this->validRecord(['maintenance_type' => 'inspection'])),
            "Field 'maintenance_type' must be one of: preventive, corrective."
        );
    }

    public function testAddRejectsADescriptionThatIsTooLong(): void
    {
        $this->assertRejected(
            fn () => $this->service->add($this->validRecord(['description' => str_repeat('a', 1001)])),
            "Field 'description' must be between 1 and 1000 characters long."
        );
    }

    public function testAddRejectsMaintenanceThatDoesNotEndAfterItStarts(): void
    {
        $this->assertRejected(
            fn () => $this->service->add($this->validRecord(['ended_at' => '2026-10-21 10:15:00'])),
            'The maintenance must end after it starts.'
        );
    }

    public function testAddRejectsAnUnknownMachine(): void
    {
        $this->assertRejected(fn () => $this->service->add($this->validRecord(['machine_id' => 999999])), 'The selected machine does not exist.');
    }

    // A machine is often out of service exactly because it is being repaired.
    public function testAddAcceptsADeactivatedMachine(): void
    {
        $this->deactivate('machines', $this->machineId);

        $this->assertSame($this->machineId, $this->service->add($this->validRecord())['machine_id']);
    }

    public function testAddRejectsAnUnknownUser(): void
    {
        $this->assertRejected(fn () => $this->service->add($this->validRecord(['user_id' => 999999])), 'The selected user does not exist.');
    }

    public function testAddRejectsADeactivatedUser(): void
    {
        $this->deactivate('users', $this->userId);

        $this->assertRejected(
            fn () => $this->service->add($this->validRecord()),
            'The selected user is deactivated and cannot log new maintenance records.'
        );
    }

    /* ---------- read ---------- */

    public function testGetByIdOfAnUnknownRecordIsNotFound(): void
    {
        $this->assertNotFound(fn () => $this->service->getById(999999), 'Maintenance record not found.');
    }

    /* ---------- update: every field is editable, nothing depends on them ---------- */

    public function testUpdateCanChangeEveryField(): void
    {
        $id = $this->service->add($this->validRecord())['id'];
        $otherMachineId = $this->createMachine('conversion', 'Test bag machine');

        $record = $this->service->update($this->validRecord([
            'machine_id' => $otherMachineId, 'maintenance_type' => 'preventive', 'ended_at' => '2026-10-21T13:00', 'notes' => 'Spare part: 2 kW heater band.',
        ]), $id);

        $this->assertSame(
            ['Test bag machine', 'preventive', '2026-10-21 13:00:00', 'Spare part: 2 kW heater band.'],
            [$record['machine_name'], $record['maintenance_type'], $record['ended_at'], $record['notes']]
        );
    }

    public function testUpdateKeepsTheFieldsThatAreLeftOut(): void
    {
        $id = $this->service->add($this->validRecord())['id'];

        $record = $this->service->update(['notes' => 'Checked again a week later'], $id);

        $this->assertSame(['corrective', '2026-10-21 12:00:00'], [$record['maintenance_type'], $record['ended_at']]);
    }

    public function testUpdateChecksTheTimeOrderAgainstTheSavedTimes(): void
    {
        $id = $this->service->add($this->validRecord())['id'];

        $this->assertRejected(fn () => $this->service->update(['started_at' => '2026-10-21 12:30:00'], $id), 'The maintenance must end after it starts.');
    }

    public function testUpdateRejectsAnUnknownMachine(): void
    {
        $id = $this->service->add($this->validRecord())['id'];

        $this->assertRejected(fn () => $this->service->update(['machine_id' => 999999], $id), 'The selected machine does not exist.');
    }

    // The record is history: it stays editable after the person who logged it left.
    public function testUpdateKeepsADeactivatedUserWhoIsAlreadyOnTheRecord(): void
    {
        $id = $this->service->add($this->validRecord())['id'];
        $this->deactivate('users', $this->userId);

        $this->assertSame('Corrected', $this->service->update(['notes' => 'Corrected'], $id)['notes']);
    }

    public function testUpdateRejectsChangingToADeactivatedUser(): void
    {
        $id = $this->service->add($this->validRecord())['id'];
        $formerUserId = $this->createUser('Former mechanic');
        $this->deactivate('users', $formerUserId);

        $this->assertRejected(
            fn () => $this->service->update(['user_id' => $formerUserId], $id),
            'The selected user is deactivated and cannot log new maintenance records.'
        );
    }

    public function testUpdateOfAnUnknownRecordIsNotFound(): void
    {
        $this->assertNotFound(fn () => $this->service->update(['notes' => 'x'], 999999), 'Maintenance record not found.');
    }

    /* ---------- delete ---------- */

    public function testDeleteRemovesTheRecord(): void
    {
        $id = $this->service->add($this->validRecord())['id'];

        $this->service->delete($id);

        $this->assertSame(0, $this->countRows('maintenance_records', 'id', $id));
    }

    public function testDeleteOfAnUnknownRecordIsNotFound(): void
    {
        $this->assertNotFound(fn () => $this->service->delete(999999), 'Maintenance record not found.');
    }
}
