<?php
require_once __DIR__ . '/../rest/dao/BaseDao.php';

use PHPUnit\Framework\TestCase;

final class DatabaseConnectionTest extends TestCase
{
    public function testConnectsToTheProductionTrackerDatabase(): void
    {
        $databaseName = BaseDao::connection()->query('SELECT DATABASE()')->fetchColumn();

        $this->assertSame('production_tracker', $databaseName);
    }

    // With emulated prepares PDO pastes the value into the SQL as the string '1', and LIMIT '1' is a syntax error.
    // Real prepares send the value separately, so LIMIT ? works like any other placeholder.
    public function testABoundValueCanBeUsedAsTheLimit(): void
    {
        $statement = BaseDao::connection()->prepare('SELECT id FROM materials ORDER BY id LIMIT ?');
        $statement->execute([1]);

        $this->assertCount(1, $statement->fetchAll());
    }
}
