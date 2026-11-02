<?php
require_once __DIR__ . '/../DatabaseTestCase.php';
require_once __DIR__ . '/../../rest/dao/UserDao.php';

// The password hash may leave the database only for the login check (getByEmail),
// otherwise it would end up in the JSON the API sends to the browser.
final class UserDaoTest extends DatabaseTestCase
{
    private UserDao $dao;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dao = new UserDao();
    }

    public function testGetAllLeavesOutThePasswordHash(): void
    {
        $this->insertTestUser();

        $users = $this->dao->getAll();

        $this->assertNotEmpty($users);
        foreach ($users as $user) {
            $this->assertArrayNotHasKey('password_hash', $user);
        }
    }

    public function testGetByIdLeavesOutThePasswordHash(): void
    {
        $user = $this->dao->getById($this->insertTestUser());

        $this->assertSame('dao-test@example.com', $user['email']);
        $this->assertArrayNotHasKey('password_hash', $user);
    }

    public function testAddReturnsTheNewUserWithoutThePasswordHash(): void
    {
        $user = $this->dao->add([
            'full_name' => 'DAO Test', 'email' => 'dao-test@example.com', 'password_hash' => password_hash('test1234', PASSWORD_BCRYPT),
        ]);

        $this->assertIsInt($user['id']);
        $this->assertArrayNotHasKey('password_hash', $user);
    }

    public function testGetByEmailIncludesThePasswordHashForTheLoginCheck(): void
    {
        $this->insertTestUser();

        $user = $this->dao->getByEmail('dao-test@example.com');

        $this->assertSame('not-a-real-hash', $user['password_hash']);
    }

    private function insertTestUser(): int
    {
        return $this->insertRow('users', [
            'full_name' => 'DAO Test', 'email' => 'dao-test@example.com', 'password_hash' => 'not-a-real-hash',
        ]);
    }
}
