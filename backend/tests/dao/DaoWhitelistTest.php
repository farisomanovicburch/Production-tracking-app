<?php
require_once __DIR__ . '/../DatabaseTestCase.php';
foreach (glob(__DIR__ . '/../../rest/dao/*Dao.php') as $daoFile) {
    require_once $daoFile;
}

use PHPUnit\Framework\Attributes\DataProvider;

// A column missing from a DAO's whitelist is silently dropped on every insert and update, so each
// whitelist is compared with the real table: every column except the generated ones must be writable.
final class DaoWhitelistTest extends DatabaseTestCase
{
    public static function daos(): array
    {
        return [
            'users' => [UserDao::class, 'users'],
            'machines' => [MachineDao::class, 'machines'],
            'machine_parameters' => [MachineParameterDao::class, 'machine_parameters'],
            'machine_products' => [MachineProductDao::class, 'machine_products'],
            'products' => [ProductDao::class, 'products'],
            'materials' => [MaterialDao::class, 'materials'],
            'recipes' => [RecipeDao::class, 'recipes'],
            'recipe_items' => [RecipeItemDao::class, 'recipe_items'],
            'recipe_products' => [RecipeProductDao::class, 'recipe_products'],
        ];
    }

    #[DataProvider('daos')]
    public function testTheDaoWritesEveryColumnOfItsTableExceptTheGeneratedOnes(string $daoClass, string $table): void
    {
        $statement = $this->db->prepare(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name
               AND COLUMN_NAME NOT IN ('id', 'created_at', 'updated_at')"
        );
        $statement->execute(['table_name' => $table]);
        $tableColumns = $statement->fetchAll(PDO::FETCH_COLUMN);

        $dao = new $daoClass();
        $this->assertSame($table, (new ReflectionProperty(BaseDao::class, 'tableName'))->getValue($dao));
        $this->assertEqualsCanonicalizing($tableColumns, (new ReflectionProperty(BaseDao::class, 'columns'))->getValue($dao));
    }
}
