<?php
require_once __DIR__ . '/../../utils/exceptions.php';
require_once __DIR__ . '/../../utils/Validator.php';

/*
 * The CRUD every service shares, written once. A child service names its entity, passes its DAO to the constructor
 * and fills validate() with its rules; it overrides a CRUD method only when that operation has extra rules.
 * Services are the only layer that calls DAOs, and they report every problem with an exception that carries
 * its HTTP status (utils/exceptions.php).
 */
class BaseService
{
    protected $dao;
    // Used in messages, e.g. "Machine not found."
    protected $entityName = 'Entity';
    // What the client reads when the row cannot be deleted because other rows point to it.
    protected $inUseMessage = null;

    // MySQL error 1451: ON DELETE RESTRICT refused the delete because other rows still point to this one.
    private const ROW_IS_REFERENCED = 1451;

    // The DAO is passed in instead of created here, so a test can hand the service a different one.
    public function __construct($dao)
    {
        $this->dao = $dao;
    }

    public function getAll()
    {
        return $this->dao->getAll();
    }

    // A DAO answers null for a missing row; turning that into an exception means no caller can forget the check.
    public function getById($id)
    {
        $entity = $this->dao->getById($id);
        if (!$entity) {
            throw new NotFoundException("{$this->entityName} not found.");
        }
        return $entity;
    }

    // The saved row is read back, so the caller sees the database defaults and the JOINed names, not its own input.
    public function add($entity)
    {
        $this->validate($entity);
        $created = $this->dao->add($entity);
        return $this->dao->getById($created['id']);
    }

    // Existence is checked with getById() first: update() returns 0 both for a missing row and for unchanged values.
    public function update($entity, $id)
    {
        $this->getById($id);
        $this->validate($entity, $id);
        $this->dao->update($entity, $id);
        return $this->dao->getById($id);
    }

    /*
     * The foreign keys already know whether anything points to the row, so instead of counting first, the delete is
     * tried and the database's refusal becomes a 400. Counting first would also leave a gap: a run saved between the
     * count and the delete.
     */
    public function delete($id)
    {
        $this->getById($id);
        try {
            $this->dao->delete($id);
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) !== self::ROW_IS_REFERENCED) {
                throw $exception;
            }
            throw new ValidationException($this->inUseMessage ?? "{$this->entityName} is still in use and cannot be deleted.");
        }
    }

    /*
     * Throws a ValidationException when $entity breaks a rule. $id is null when adding and the entity's own id when
     * updating, so a unique-name check can tell "another row has this name" from "this row keeps its name".
     */
    protected function validate($entity, $id = null)
    {
    }

    // A user cannot see the space in " EX-01", but the database would store it and compare with it: spaces around
    // a name or code are typing noise.
    protected function trimStrings($entity, $fields)
    {
        foreach ($fields as $field) {
            if (isset($entity[$field]) && is_string($entity[$field])) {
                $entity[$field] = trim($entity[$field]);
            }
        }
        return $entity;
    }

    // A form sends "" for an empty optional field, but "" is not a valid number or date: the column wants NULL.
    protected function emptyToNull($entity, $fields)
    {
        foreach ($fields as $field) {
            if (array_key_exists($field, $entity) && is_string($entity[$field]) && trim($entity[$field]) === '') {
                $entity[$field] = null;
            }
        }
        return $entity;
    }

    // PDO sends PHP's false as an empty string, which a BOOLEAN column refuses, so flags are stored as 1 and 0.
    // Values that are not a flag are left alone for Validator::boolean() to reject.
    protected function normalizeBooleans($entity, $fields)
    {
        foreach ($fields as $field) {
            if (!array_key_exists($field, $entity)) {
                continue;
            }
            if (in_array($entity[$field], [true, 1, '1'], true)) {
                $entity[$field] = 1;
            } elseif (in_array($entity[$field], [false, 0, '0'], true)) {
                $entity[$field] = 0;
            }
        }
        return $entity;
    }

    // <input type="datetime-local"> sends 2026-10-05T06:00; the DATETIME columns and Validator::datetime() expect
    // 2026-10-05 06:00:00. Values in any other shape are left alone, so the validator can reject them.
    protected function normalizeDatetimes($entity, $fields)
    {
        foreach ($fields as $field) {
            if (!isset($entity[$field]) || !is_string($entity[$field])) {
                continue;
            }
            if (preg_match('/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})(:\d{2})?$/', $entity[$field], $parts)) {
                $seconds = $parts[3] ?? '';
                $entity[$field] = $parts[1] . ' ' . $parts[2] . ($seconds === '' ? ':00' : $seconds);
            }
        }
        return $entity;
    }
}
