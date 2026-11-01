<?php
require_once __DIR__ . '/../DatabaseTestCase.php';

// The sample data is what the reviewer and the examiner see first, so the promises the README makes about it are tested.
final class SampleDataTest extends DatabaseTestCase
{
    public function testDemoAccountsLogInWithTheDocumentedPasswords(): void
    {
        $accounts = [
            'admin@example.com' => ['password' => 'admin123', 'role' => 'admin', 'is_active' => 1],
            'operator@example.com' => ['password' => 'operator456', 'role' => 'operator', 'is_active' => 1],
            'former@example.com' => ['password' => 'former789', 'role' => 'operator', 'is_active' => 0],
        ];

        foreach ($accounts as $email => $expected) {
            $statement = $this->db->prepare('SELECT password_hash, role, is_active FROM users WHERE email = :email');
            $statement->execute(['email' => $email]);
            $user = $statement->fetch();

            $this->assertNotFalse($user, "$email is missing from the sample data");
            $this->assertTrue(password_verify($expected['password'], $user['password_hash']), "wrong password hash for $email");
            $this->assertSame($expected['role'], $user['role'], "wrong role for $email");
            $this->assertSame($expected['is_active'], (int) $user['is_active'], "wrong is_active for $email");
        }
    }

    public function testEveryRecipeAddsUpToOneHundredPercent(): void
    {
        $totals = $this->db->query(
            'SELECT r.name, COALESCE(SUM(ri.percentage), 0) AS total
             FROM recipes r LEFT JOIN recipe_items ri ON ri.recipe_id = r.id
             GROUP BY r.id, r.name'
        )->fetchAll();

        // Without this, an empty recipes table would pass the loop below without checking anything.
        $this->assertNotEmpty($totals, 'the sample data has no recipes');
        foreach ($totals as $recipe) {
            $this->assertEquals(100, $recipe['total'], "{$recipe['name']} adds up to {$recipe['total']} %");
        }
    }

    public function testAtLeastOneMaterialIsAtOrBelowItsMinimumStock(): void
    {
        $lowStockCount = (int) $this->db->query(
            'SELECT COUNT(*) FROM materials WHERE min_stock_qty IS NOT NULL AND stock_qty <= min_stock_qty'
        )->fetchColumn();

        $this->assertGreaterThan(0, $lowStockCount, 'the dashboard low-stock card would be empty');
    }
}
