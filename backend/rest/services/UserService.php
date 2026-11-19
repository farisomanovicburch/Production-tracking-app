<?php
require_once __DIR__ . '/BaseService.php';
require_once __DIR__ . '/../dao/UserDao.php';
require_once __DIR__ . '/../../utils/Roles.php';

class UserService extends BaseService
{
    protected $entityName = 'User';
    protected $inUseMessage = 'This user has logged runs, conversions or maintenance and cannot be deleted. Deactivate the account instead.';

    // What a client may send. password_hash is a writable DAO column, so without this list a client could send a
    // hash of its own choosing and skip the hashing.
    private const ACCEPTED_FIELDS = ['full_name', 'email', 'password', 'role', 'is_active'];

    public function __construct($dao = null)
    {
        parent::__construct($dao ?? new UserDao());
    }

    // A new account is an operator unless an admin chooses otherwise.
    public function add($user)
    {
        $user = $this->prepare($user);
        $user['role'] = $user['role'] ?? Roles::OPERATOR;
        $this->validate($user);
        // UserDao::add() reads the new user back without the hash.
        return $this->dao->add($this->hashPassword($user));
    }

    // Leaving the password out (or empty) keeps the current one: an edit of the name should not reset it.
    public function update($user, $id)
    {
        $user = $this->prepare($user);
        $this->getById($id);
        $this->validate($user, $id);
        $this->dao->update($this->hashPassword($user), $id);
        return $this->dao->getById($id);
    }

    protected function validate($user, $id = null)
    {
        Validator::required($user, $id === null ? ['full_name', 'email', 'password'] : ['full_name', 'email']);
        Validator::length($user['full_name'], 'full_name', 2, 100);
        Validator::email($user['email']);
        Validator::length($user['email'], 'email', 3, 255);
        // Checked before hashing: a bcrypt hash is always 60 characters, so after hashing "abc" would look strong.
        if ($this->passwordGiven($user)) {
            Validator::password($user['password']);
        }
        if (isset($user['role'])) {
            Validator::oneOf($user['role'], 'role', Roles::all());
        }
        if (array_key_exists('is_active', $user)) {
            Validator::boolean($user['is_active'], 'is_active');
        }

        // $id lets a user keep their own email on update. getByEmail() returns the hash, but it never leaves here.
        $existing = $this->dao->getByEmail($user['email']);
        if ($existing && $existing['id'] != $id) {
            throw new ValidationException('An account with this email already exists.');
        }
    }

    private function prepare($user)
    {
        $user = array_intersect_key($user, array_flip(self::ACCEPTED_FIELDS));
        foreach (['full_name', 'email'] as $field) {
            if (isset($user[$field]) && is_string($user[$field])) {
                $user[$field] = trim($user[$field]);
            }
        }
        // Lower case, so Ana@Example.com and ana@example.com are one account, also at login (Milestone 4).
        if (isset($user['email']) && is_string($user['email'])) {
            $user['email'] = strtolower($user['email']);
        }
        return $this->normalizeBooleans($user, ['is_active']);
    }

    private function passwordGiven($user)
    {
        return isset($user['password']) && $user['password'] !== '';
    }

    private function hashPassword($user)
    {
        if ($this->passwordGiven($user)) {
            $user['password_hash'] = password_hash($user['password'], PASSWORD_BCRYPT);
        }
        unset($user['password']);
        return $user;
    }
}
