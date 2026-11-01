<?php
require_once __DIR__ . '/../rest/dao/BaseDao.php';

use PHPUnit\Framework\TestCase;

// Every test runs inside a transaction that is rolled back afterwards, so rows one test inserts
// never reach the next test. It uses BaseDao's shared connection, so DAO writes are rolled back too.
abstract class DatabaseTestCase extends TestCase
{
    protected PDO $db;

    protected function setUp(): void
    {
        $this->db = BaseDao::connection();
        $this->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    // Table and column names come from the test code only, never from user input.
    protected function insertRow(string $table, array $row): int
    {
        $columns = array_keys($row);
        $this->db->prepare(
            "INSERT INTO $table (" . implode(', ', $columns) . ') VALUES (:' . implode(', :', $columns) . ')'
        )->execute($row);
        return (int) $this->db->lastInsertId();
    }

    protected function countRows(string $table, string $column, int $value): int
    {
        $statement = $this->db->prepare("SELECT COUNT(*) FROM $table WHERE $column = :value");
        $statement->execute(['value' => $value]);
        return (int) $statement->fetchColumn();
    }
}
