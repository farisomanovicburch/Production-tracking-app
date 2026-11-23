<?php
require_once __DIR__ . '/../DatabaseTestCase.php';

// BaseDao is tested once, on the materials table; every other DAO inherits this CRUD instead of repeating it.
final class BaseDaoTest extends DatabaseTestCase
{
    private BaseDao $dao;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dao = new BaseDao('materials', ['name', 'unit', 'supplier', 'stock_qty', 'min_stock_qty', 'is_active']);
    }

    public function testAddInsertsTheRowAndReturnsItWithItsNewId(): void
    {
        $added = $this->dao->add(['name' => 'Test PP granulate', 'stock_qty' => 10]);

        $this->assertIsInt($added['id']);
        $this->assertSame('Test PP granulate', $this->dao->getById($added['id'])['name']);
    }

    public function testAddWritesOnlyTheWhitelistedColumns(): void
    {
        $added = $this->dao->add(['name' => 'Test PP granulate', 'id' => 999, 'hacker' => 'DROP TABLE materials']);

        $this->assertArrayNotHasKey('hacker', $added);
        $this->assertNotSame(999, $added['id']);
    }

    public function testAddWithoutWritableColumnsThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->dao->add(['nmae' => 'Test PP granulate']);
    }

    public function testGetByIdReturnsNullForAMissingId(): void
    {
        $this->assertNull($this->dao->getById(999999));
    }

    public function testGetAllReturnsEveryRowOfTheTable(): void
    {
        $this->dao->add(['name' => 'Test PP granulate']);

        $rows = $this->dao->getAll();

        $this->assertCount((int) $this->db->query('SELECT COUNT(*) FROM materials')->fetchColumn(), $rows);
        $this->assertContains('Test PP granulate', array_column($rows, 'name'));
    }

    public function testUpdateChangesOnlyTheGivenColumns(): void
    {
        $id = $this->dao->add(['name' => 'Test PP granulate', 'supplier' => 'Supplier X', 'stock_qty' => 10])['id'];

        $this->dao->update(['stock_qty' => 20], $id);

        $row = $this->dao->getById($id);
        $this->assertSame('20.000', $row['stock_qty']);
        $this->assertSame('Test PP granulate', $row['name']);
        $this->assertSame('Supplier X', $row['supplier']);
    }

    public function testUpdateWithoutWritableColumnsChangesNothing(): void
    {
        $id = $this->dao->add(['name' => 'Test PP granulate'])['id'];

        $this->assertSame(0, $this->dao->update(['hacker' => 'x'], $id));
        $this->assertSame('Test PP granulate', $this->dao->getById($id)['name']);
    }

    public function testDeleteRemovesTheRow(): void
    {
        $id = $this->dao->add(['name' => 'Test PP granulate'])['id'];

        $this->assertSame(1, $this->dao->delete($id));
        $this->assertNull($this->dao->getById($id));
    }

    /* ---------- transactions (this test already runs inside a transaction, so these use savepoints) ---------- */

    public function testRollBackUndoesOnlyTheWorkSinceBeginTransaction(): void
    {
        $before = $this->dao->add(['name' => 'Test before the transaction'])['id'];

        $this->dao->beginTransaction();
        $inside = $this->dao->add(['name' => 'Test inside the transaction'])['id'];
        $this->dao->rollBack();

        $this->assertNull($this->dao->getById($inside));
        $this->assertNotNull($this->dao->getById($before));
    }

    public function testCommitKeepsTheWorkOfTheTransaction(): void
    {
        $this->dao->beginTransaction();
        $id = $this->dao->add(['name' => 'Test committed'])['id'];
        $this->dao->commit();

        $this->assertNotNull($this->dao->getById($id));
    }

    public function testANestedRollBackLeavesTheOuterTransactionUsable(): void
    {
        $this->dao->beginTransaction();
        $outer = $this->dao->add(['name' => 'Test outer'])['id'];
        $this->dao->beginTransaction();
        $inner = $this->dao->add(['name' => 'Test inner'])['id'];
        $this->dao->rollBack();
        $this->dao->commit();

        $this->assertNull($this->dao->getById($inner));
        $this->assertNotNull($this->dao->getById($outer));
    }

    public function testTransactionCommitsWhatTheWorkDidAndReturnsItsResult(): void
    {
        $id = $this->dao->transaction(fn () => $this->dao->add(['name' => 'Test in transaction()'])['id']);

        $this->assertNotNull($this->dao->getById($id));
    }

    // All or nothing: the run is saved together with its stock change, or neither is.
    public function testTransactionRollsBackAndRethrowsWhenTheWorkFails(): void
    {
        $failure = new RuntimeException('Second write failed.');
        $writtenId = null;

        try {
            $this->dao->transaction(function () use (&$writtenId, $failure) {
                $writtenId = $this->dao->add(['name' => 'Test half-done work'])['id'];
                throw $failure;
            });
            $this->fail('The failure was swallowed.');
        } catch (RuntimeException $exception) {
            $this->assertSame($failure, $exception);
        }

        $this->assertNull($this->dao->getById($writtenId));
    }
}
