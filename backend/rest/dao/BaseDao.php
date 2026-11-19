<?php
require_once __DIR__ . '/../../config/config.php';

/**
 * Generic CRUD for one table. A child DAO only names its table and the columns that may be written:
 *
 *   class ProductDao extends BaseDao {
 *       public function __construct() { parent::__construct('products', ['code', 'name', ...]); }
 *   }
 */
class BaseDao
{
    // One connection shared by every DAO, created on first use: opening a new one per query would be slow,
    // and a transaction only covers queries that run on the same connection.
    private static $connection = null;

    protected $tableName;
    // Only these columns can be inserted or updated. Column names cannot be bound as ? parameters,
    // so anything else in the incoming array is dropped before it can reach the SQL text.
    protected $columns;

    public function __construct($tableName, $columns = [])
    {
        $this->tableName = $tableName;
        $this->columns = $columns;
    }

    public static function connection()
    {
        if (self::$connection === null) {
            self::$connection = new PDO(
                'mysql:host=' . Config::DB_HOST() . ';port=' . Config::DB_PORT()
                    . ';dbname=' . Config::DB_NAME() . ';charset=utf8mb4',
                Config::DB_USER(),
                Config::DB_PASSWORD(),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    // Real server-side prepares: values travel apart from the SQL text instead of being escaped
                    // into it by PHP, and LIMIT ? accepts a bound number.
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        }
        return self::$connection;
    }

    /* ---------- transactions ---------- */

    // How many of the open transaction levels are savepoints opened by beginTransaction(). Static, because the
    // connection is shared: a savepoint opened through one DAO is released through another.
    private static $savepointDepth = 0;

    // MySQL cannot open a transaction inside another one. Inside an open transaction (a PHPUnit test, or a service
    // calling another service), a SAVEPOINT marks the spot instead, so a rollback undoes only the work since then.
    public function beginTransaction()
    {
        if (self::connection()->inTransaction()) {
            self::$savepointDepth++;
            self::connection()->exec('SAVEPOINT sp' . self::$savepointDepth);
        } else {
            self::connection()->beginTransaction();
        }
    }

    public function commit()
    {
        if (self::$savepointDepth > 0) {
            self::connection()->exec('RELEASE SAVEPOINT sp' . self::$savepointDepth);
            self::$savepointDepth--;
        } else {
            self::connection()->commit();
        }
    }

    public function rollBack()
    {
        if (self::$savepointDepth > 0) {
            self::connection()->exec('ROLLBACK TO SAVEPOINT sp' . self::$savepointDepth);
            self::connection()->exec('RELEASE SAVEPOINT sp' . self::$savepointDepth);
            self::$savepointDepth--;
        } else {
            self::connection()->rollBack();
        }
    }

    // All or nothing: runs $work, commits if it finishes, rolls back and rethrows if it throws anything.
    public function transaction(callable $work)
    {
        $this->beginTransaction();
        try {
            $result = $work();
            $this->commit();
            return $result;
        } catch (Throwable $exception) {
            $this->rollBack();
            throw $exception;
        }
    }

    /* ---------- helpers for child DAOs ---------- */

    protected function query($sql, $params = [])
    {
        $statement = self::connection()->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    protected function queryUnique($sql, $params = [])
    {
        return $this->query($sql, $params)[0] ?? null;
    }

    // For INSERT, UPDATE and DELETE: returns how many rows changed.
    protected function execute($sql, $params = [])
    {
        $statement = self::connection()->prepare($sql);
        $statement->execute($params);
        return $statement->rowCount();
    }

    /* ---------- CRUD ---------- */

    public function getAll()
    {
        return $this->query("SELECT * FROM {$this->tableName} ORDER BY id");
    }

    public function getById($id)
    {
        return $this->queryUnique("SELECT * FROM {$this->tableName} WHERE id = :id", ['id' => $id]);
    }

    // INSERT INTO materials (name, stock_qty) VALUES (:name, :stock_qty)
    public function add($entity)
    {
        $entity = $this->onlyColumns($entity);
        // Unlike an empty update, an empty insert cannot quietly do nothing: the caller expects a new row back.
        if (count($entity) === 0) {
            throw new InvalidArgumentException("Nothing to insert into {$this->tableName}: no writable column was given.");
        }
        $columns = array_keys($entity);

        $this->execute(
            "INSERT INTO {$this->tableName} (" . implode(', ', $columns) . ')'
                . ' VALUES (:' . implode(', :', $columns) . ')',
            $entity
        );

        $entity['id'] = (int) self::connection()->lastInsertId();
        return $entity;
    }

    // UPDATE materials SET name = :name, stock_qty = :stock_qty WHERE id = :id
    public function update($entity, $id)
    {
        $entity = $this->onlyColumns($entity);
        if (count($entity) === 0) {
            return 0;
        }

        $assignments = [];
        foreach (array_keys($entity) as $column) {
            $assignments[] = "$column = :$column";
        }

        $entity['id'] = $id;
        return $this->execute(
            "UPDATE {$this->tableName} SET " . implode(', ', $assignments) . ' WHERE id = :id',
            $entity
        );
    }

    public function delete($id)
    {
        return $this->execute("DELETE FROM {$this->tableName} WHERE id = :id", ['id' => $id]);
    }

    protected function onlyColumns($entity)
    {
        return array_intersect_key($entity, array_flip($this->columns));
    }
}
