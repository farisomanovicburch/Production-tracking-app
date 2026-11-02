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

    /* ---------- fixtures ---------- */

    // Codes, emails and default names carry a running number, so fixtures never collide with each other
    // or with the sample data (EX-01, P-100, admin@example.com, ...).
    private int $fixtureNumber = 0;

    protected function createUser(string $fullName = 'Test User'): int
    {
        $number = ++$this->fixtureNumber;
        return $this->insertRow('users', [
            'full_name' => $fullName, 'email' => "fixture$number@example.com", 'password_hash' => 'not-a-real-hash',
        ]);
    }

    protected function createMachine(string $type, string $name = 'Test machine'): int
    {
        return $this->insertRow('machines', ['name' => $name, 'code' => 'T-M' . ++$this->fixtureNumber, 'machine_type' => $type]);
    }

    protected function createProduct(string $name = 'Test product', string $unit = 'kg'): int
    {
        return $this->insertRow('products', ['code' => 'T-P' . ++$this->fixtureNumber, 'name' => $name, 'unit' => $unit]);
    }

    protected function createRecipe(?string $name = null): int
    {
        return $this->insertRow('recipes', ['name' => $name ?? 'Test recipe ' . ++$this->fixtureNumber]);
    }

    protected function createMaterial(?string $name = null): int
    {
        return $this->insertRow('materials', ['name' => $name ?? 'Test material ' . ++$this->fixtureNumber]);
    }

    protected function createRun(int $machineId, array $overrides = []): int
    {
        return $this->insertRow('production_runs', array_merge([
            'machine_id' => $machineId,
            'product_id' => $this->createProduct(),
            'recipe_id' => $this->createRecipe(),
            'user_id' => $this->createUser(),
            'started_at' => '2026-10-20 08:00:00',
            'ended_at' => '2026-10-20 14:00:00',
            'quantity_produced' => 500,
        ], $overrides));
    }
}
