<?php
require_once __DIR__ . '/../DatabaseTestCase.php';
require_once __DIR__ . '/../../rest/services/UserService.php';

final class UserServiceTest extends DatabaseTestCase
{
    private const PASSWORD = 'secret123';
    private const NAME = 'Test Operator';
    private const EMAIL = 'service-test@example.com';

    private UserService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UserService();
    }

    private function newUser(array $overrides = []): array
    {
        return array_merge(['full_name' => self::NAME, 'email' => self::EMAIL, 'password' => self::PASSWORD], $overrides);
    }

    private function storedHash(int $id): string
    {
        $statement = $this->db->prepare('SELECT password_hash FROM users WHERE id = :id');
        $statement->execute(['id' => $id]);
        return $statement->fetchColumn();
    }

    private function assertRejected(callable $action, string $message): void
    {
        try {
            $action();
            $this->fail("Accepted, expected: $message");
        } catch (ValidationException $exception) {
            $this->assertSame($message, $exception->getMessage());
        }
    }

    /* ---------- add ---------- */

    public function testAddStoresOnlyABcryptHashOfThePassword(): void
    {
        $user = $this->service->add($this->newUser());

        $hash = $this->storedHash($user['id']);
        $this->assertNotSame(self::PASSWORD, $hash);
        $this->assertTrue(password_verify(self::PASSWORD, $hash));
    }

    public function testAddReturnsTheUserWithoutPasswordOrHash(): void
    {
        $user = $this->service->add($this->newUser());

        $this->assertArrayNotHasKey('password', $user);
        $this->assertArrayNotHasKey('password_hash', $user);
    }

    public function testAddRequiresAPassword(): void
    {
        $this->assertRejected(fn () => $this->service->add($this->newUser(['password' => null])), "Field 'password' is required.");
    }

    // Checked before hashing: a bcrypt hash is always 60 characters, so after hashing "abc" would look strong.
    public function testAddRejectsAWeakPassword(): void
    {
        $this->assertRejected(
            fn () => $this->service->add($this->newUser(['password' => 'abc'])),
            "Field 'password' must have at least 8 characters, including a letter and a number."
        );
    }

    public function testAddStoresTheEmailTrimmedAndInLowerCase(): void
    {
        $user = $this->service->add($this->newUser(['email' => '  Service-Test@Example.COM ']));

        $this->assertSame(self::EMAIL, $user['email']);
    }

    public function testAddRejectsAnEmailThatAlreadyHasAnAccountInAnyCase(): void
    {
        $this->assertRejected(
            fn () => $this->service->add($this->newUser(['email' => 'Admin@Example.com'])),
            'An account with this email already exists.'
        );
    }

    public function testAddRejectsAnInvalidEmail(): void
    {
        $this->assertRejected(
            fn () => $this->service->add($this->newUser(['email' => 'not-an-email'])),
            "Field 'email' must be a valid email address."
        );
    }

    public function testAddRejectsATooShortName(): void
    {
        $this->assertRejected(
            fn () => $this->service->add($this->newUser(['full_name' => 'A'])),
            "Field 'full_name' must be between 2 and 100 characters long."
        );
    }

    public function testANewUserIsAnActiveOperatorUnlessARoleIsGiven(): void
    {
        $user = $this->service->add($this->newUser());

        $this->assertSame('operator', $user['role']);
        $this->assertSame(1, $user['is_active']);
    }

    public function testAddRejectsAnUnknownRole(): void
    {
        $this->assertRejected(
            fn () => $this->service->add($this->newUser(['role' => 'superuser'])),
            "Field 'role' must be one of: admin, operator."
        );
    }

    /* ---------- update ---------- */

    public function testUpdateLetsAUserKeepTheirOwnEmail(): void
    {
        $id = $this->service->add($this->newUser())['id'];

        $updated = $this->service->update(['full_name' => 'Renamed Operator', 'email' => self::EMAIL], $id);

        $this->assertSame('Renamed Operator', $updated['full_name']);
    }

    public function testUpdateRejectsTheEmailOfAnotherAccount(): void
    {
        $id = $this->service->add($this->newUser())['id'];

        $this->assertRejected(
            fn () => $this->service->update(['full_name' => self::NAME, 'email' => 'admin@example.com'], $id),
            'An account with this email already exists.'
        );
    }

    public function testUpdateWithoutAPasswordKeepsTheOldOne(): void
    {
        $id = $this->service->add($this->newUser())['id'];
        $hashBefore = $this->storedHash($id);

        $this->service->update(['full_name' => self::NAME, 'email' => self::EMAIL], $id);

        $this->assertSame($hashBefore, $this->storedHash($id));
    }

    // password_hash is a writable DAO column: without the allow-list, an update without a password could set a hash
    // of the client's choosing and take over the account.
    public function testUpdateIgnoresAPasswordHashSentByTheClient(): void
    {
        $id = $this->service->add($this->newUser())['id'];

        $this->service->update(
            ['full_name' => self::NAME, 'email' => self::EMAIL, 'password_hash' => password_hash('attacker1', PASSWORD_BCRYPT)],
            $id
        );

        $this->assertTrue(password_verify(self::PASSWORD, $this->storedHash($id)));
    }

    public function testUpdateWithAPasswordStoresTheNewOneHashed(): void
    {
        $id = $this->service->add($this->newUser())['id'];

        $this->service->update(['full_name' => self::NAME, 'email' => self::EMAIL, 'password' => 'changed456'], $id);

        $this->assertTrue(password_verify('changed456', $this->storedHash($id)));
    }

    // A deactivated user can no longer log in (Milestone 4), but the runs they logged keep their name.
    public function testUpdateCanDeactivateAUserWithFalseFromJson(): void
    {
        $id = $this->service->add($this->newUser())['id'];

        $updated = $this->service->update(['full_name' => self::NAME, 'email' => self::EMAIL, 'is_active' => false], $id);

        $this->assertSame(0, $updated['is_active']);
    }

    /* ---------- delete ---------- */

    public function testDeleteOfAUserWithHistoryAsksToDeactivateInstead(): void
    {
        $userId = $this->createUser();
        $this->createRun($this->createMachine('extrusion'), ['user_id' => $userId]);

        $this->assertRejected(
            fn () => $this->service->delete($userId),
            'This user has logged runs, conversions or maintenance and cannot be deleted. Deactivate the account instead.'
        );
    }
}
