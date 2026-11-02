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
}
