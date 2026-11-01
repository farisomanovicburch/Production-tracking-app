<?php
require_once __DIR__ . '/BaseDao.php';

class UserDao extends BaseDao
{
    // Everything except password_hash: the hash leaves the database only for the login check.
    private const PUBLIC_COLUMNS = 'id, full_name, email, role, is_active, created_at';

    public function __construct()
    {
        parent::__construct('users', ['full_name', 'email', 'password_hash', 'role', 'is_active']);
    }

    public function getAll()
    {
        return $this->query('SELECT ' . self::PUBLIC_COLUMNS . ' FROM users ORDER BY full_name');
    }

    public function getById($id)
    {
        return $this->queryUnique('SELECT ' . self::PUBLIC_COLUMNS . ' FROM users WHERE id = :id', ['id' => $id]);
    }

    // BaseDao::add() returns the array it was given, hash included, so the new user is read back without it.
    public function add($entity)
    {
        return $this->getById(parent::add($entity)['id']);
    }

    public function getByEmail($email)
    {
        return $this->queryUnique('SELECT * FROM users WHERE email = :email', ['email' => $email]);
    }
}
