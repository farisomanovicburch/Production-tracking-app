<?php
require_once __DIR__ . '/../../rest/dao/BaseDao.php';

use PHPUnit\Framework\TestCase;

// Outside the test transaction, as in the running app: beginTransaction() must open a real transaction.
// Only the rollback path is tested here, because a committed row would stay in the database for the other tests.
final class BaseDaoRealTransactionTest extends TestCase
{
    public function testRollBackOfARealTransactionUndoesTheWork(): void
    {
        $dao = new BaseDao('materials', ['name']);

        $dao->beginTransaction();
        $this->assertTrue(BaseDao::connection()->inTransaction());
        $id = $dao->add(['name' => 'Test real transaction'])['id'];
        $dao->rollBack();

        $this->assertFalse(BaseDao::connection()->inTransaction());
        $this->assertNull($dao->getById($id));
    }
}
