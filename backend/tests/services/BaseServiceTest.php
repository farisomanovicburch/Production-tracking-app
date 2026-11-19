<?php
require_once __DIR__ . '/../DatabaseTestCase.php';
require_once __DIR__ . '/../../rest/dao/MaterialDao.php';
require_once __DIR__ . '/../../rest/services/BaseService.php';

// A minimal child service: BaseService is tested once here, every real service inherits this behaviour.
final class MaterialTestService extends BaseService
{
    protected $entityName = 'Material';
    public $validatedWithId = 'validate() was not called';

    public function __construct()
    {
        parent::__construct(new MaterialDao());
    }

    protected function validate($entity, $id = null)
    {
        $this->validatedWithId = $id;
        Validator::required($entity, ['name']);
    }

    // emptyToNull() and normalizeDatetimes() are helpers for child services, so they are protected.
    public function callEmptyToNull($entity, $fields) { return $this->emptyToNull($entity, $fields); }
    public function callNormalizeDatetimes($entity, $fields) { return $this->normalizeDatetimes($entity, $fields); }
}

final class BaseServiceTest extends DatabaseTestCase
{
    private MaterialTestService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MaterialTestService();
    }

    public function testGetByIdReturnsTheEntity(): void
    {
        $id = $this->createMaterial('Test LDPE');

        $this->assertSame('Test LDPE', $this->service->getById($id)['name']);
    }

    public function testGetByIdThrowsNotFoundNamingTheEntity(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Material not found.');

        $this->service->getById(999999);
    }

    public function testAddValidatesWithoutAnIdAndReturnsTheRowReadBackFromTheDatabase(): void
    {
        $added = $this->service->add(['name' => 'Test LDPE']);

        $this->assertNull($this->service->validatedWithId);
        // stock_qty was not sent: '0.000' can only come from the database default, so the row was read back.
        $this->assertSame('0.000', $added['stock_qty']);
    }

    public function testAddSavesNothingWhenValidationFails(): void
    {
        $countBefore = (int) $this->db->query('SELECT COUNT(*) FROM materials')->fetchColumn();

        try {
            $this->service->add(['supplier' => 'Supplier X']);
            $this->fail('An entity without a name was accepted.');
        } catch (ValidationException $exception) {
            $this->assertSame("Field 'name' is required.", $exception->getMessage());
        }

        $this->assertSame($countBefore, (int) $this->db->query('SELECT COUNT(*) FROM materials')->fetchColumn());
    }

    public function testUpdateValidatesWithTheIdAndReturnsTheSavedRow(): void
    {
        $id = $this->createMaterial('Test LDPE');

        $updated = $this->service->update(['name' => 'Test HDPE'], $id);

        $this->assertSame($id, $this->service->validatedWithId);
        $this->assertSame('Test HDPE', $updated['name']);
    }

    // An unknown id is 404 even when the body is also invalid: "it does not exist" is the more useful answer.
    public function testUpdateOfAnUnknownIdIsNotFoundBeforeTheBodyIsValidated(): void
    {
        $this->expectException(NotFoundException::class);

        $this->service->update([], 999999);
    }

    // update() of identical values changes 0 rows; that must still be a success, not a 404.
    public function testUpdateWithUnchangedValuesSucceeds(): void
    {
        $id = $this->createMaterial('Test LDPE');

        $this->assertSame('Test LDPE', $this->service->update(['name' => 'Test LDPE'], $id)['name']);
    }

    public function testDeleteRemovesTheEntity(): void
    {
        $id = $this->createMaterial();

        $this->service->delete($id);

        $this->assertSame(0, $this->countRows('materials', 'id', $id));
    }

    public function testDeleteOfAnUnknownIdThrowsNotFound(): void
    {
        $this->expectException(NotFoundException::class);

        $this->service->delete(999999);
    }

    public function testEmptyToNullTurnsBlankOptionalFieldsIntoNull(): void
    {
        $entity = $this->service->callEmptyToNull(
            ['supplier' => '  ', 'min_stock_qty' => '', 'name' => '', 'unit' => 'kg'],
            ['supplier', 'min_stock_qty', 'absent_field']
        );

        $this->assertSame(['supplier' => null, 'min_stock_qty' => null, 'name' => '', 'unit' => 'kg'], $entity);
    }

    public function testNormalizeDatetimesTurnsTheBrowserFormatIntoTheDatabaseFormat(): void
    {
        $entity = $this->service->callNormalizeDatetimes(
            ['started_at' => '2026-10-05T06:00', 'ended_at' => '2026-10-05 14:30', 'stable_at' => '2026-10-05 07:00:00', 'notes' => 'T'],
            ['started_at', 'ended_at', 'stable_at', 'warmup_started_at']
        );

        $this->assertSame(
            ['started_at' => '2026-10-05 06:00:00', 'ended_at' => '2026-10-05 14:30:00', 'stable_at' => '2026-10-05 07:00:00', 'notes' => 'T'],
            $entity
        );
    }
}
