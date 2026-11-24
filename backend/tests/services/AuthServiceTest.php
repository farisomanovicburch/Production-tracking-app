<?php
require_once __DIR__ . '/../DatabaseTestCase.php';
require_once __DIR__ . '/../../rest/services/AuthService.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class AuthServiceTest extends DatabaseTestCase
{
    private const PASSWORD = 'secret123';
    private const EMAIL = 'auth-test@example.com';

    private AuthService $service;
    private string $logFolder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AuthService();
        // Login writes to the logs: they go to a folder of this test, not into backend/logs.
        $this->logFolder = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'auth-service-test-' . bin2hex(random_bytes(6));
        Logger::setDirectory($this->logFolder);
    }

    protected function tearDown(): void
    {
        Logger::setDirectory(null);
        foreach (glob($this->logFolder . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->logFolder)) {
            rmdir($this->logFolder);
        }
        parent::tearDown();
    }

    private function registration(array $overrides = []): array
    {
        return array_merge(['full_name' => 'Auth Tester', 'email' => self::EMAIL, 'password' => self::PASSWORD], $overrides);
    }

    // The account every login test uses, created the way a real one is.
    private function registeredUser(): array
    {
        return $this->service->register($this->registration())['user'];
    }

    private function logText(string $file): string
    {
        $path = $this->logFolder . DIRECTORY_SEPARATOR . $file;
        return file_exists($path) ? file_get_contents($path) : '';
    }

    private function assertUnauthorized(callable $action, string $message): void
    {
        try {
            $action();
            $this->fail("Accepted, expected 401: $message");
        } catch (UnauthorizedException $exception) {
            $this->assertSame($message, $exception->getMessage());
        }
    }

    // Signs with the app's own secret, so a test can forge a token the way the server would accept it
    // (or, with another secret, the way an attacker would not).
    private function tokenWith(array $payload, ?string $secret = null): string
    {
        return JWT::encode($payload, $secret ?? Config::JWT_SECRET(), 'HS256');
    }

    private function base64Url(string $text): string
    {
        return rtrim(strtr(base64_encode($text), '+/', '-_'), '=');
    }

    /* ---------- register ---------- */

    public function testRegisterCreatesTheAccountAndLogsTheUserInRightAway(): void
    {
        $result = $this->service->register($this->registration());

        $this->assertSame(['user', 'token'], array_keys($result));
        $this->assertSame(self::EMAIL, $result['user']['email']);
        $this->assertSame($result['user']['id'], $this->service->userFromToken($result['token'])['id']);
    }

    // A client must never be able to make itself an admin, or an already deactivated account, by sending the field.
    public function testRegisterAlwaysCreatesAnActiveOperator(): void
    {
        $user = $this->service->register($this->registration(['role' => 'admin', 'is_active' => false]))['user'];

        $this->assertSame('operator', $user['role']);
        $this->assertSame(1, $user['is_active']);
    }

    public function testRegisterAnswersWithoutPasswordOrHash(): void
    {
        $user = $this->service->register($this->registration())['user'];

        $this->assertArrayNotHasKey('password', $user);
        $this->assertArrayNotHasKey('password_hash', $user);
    }

    public function testRegisterRejectsAWeakPassword(): void
    {
        $this->assertRejected(
            fn () => $this->service->register($this->registration(['password' => 'abc'])),
            "Field 'password' must have at least 8 characters, including a letter and a number."
        );
    }

    /* ---------- login ---------- */

    public function testLoginReturnsTheUserAndAToken(): void
    {
        $user = $this->registeredUser();

        $result = $this->service->login(['email' => self::EMAIL, 'password' => self::PASSWORD]);

        $this->assertSame(['user', 'token'], array_keys($result));
        $this->assertSame($user['id'], $result['user']['id']);
        $this->assertNotSame('', $result['token']);
    }

    public function testLoginAnswersWithoutPasswordOrHash(): void
    {
        $this->registeredUser();

        $user = $this->service->login(['email' => self::EMAIL, 'password' => self::PASSWORD])['user'];

        $this->assertArrayNotHasKey('password', $user);
        $this->assertArrayNotHasKey('password_hash', $user);
    }

    // The email was stored trimmed and in lower case, so a login typed " Auth-Test@Example.COM " is the same account.
    public function testLoginIgnoresCaseAndSpacesInTheEmail(): void
    {
        $user = $this->registeredUser();

        $result = $this->service->login(['email' => '  Auth-Test@Example.COM ', 'password' => self::PASSWORD]);

        $this->assertSame($user['id'], $result['user']['id']);
    }

    public function testLoginWithAWrongPasswordIsRefused(): void
    {
        $this->registeredUser();

        $this->assertUnauthorized(
            fn () => $this->service->login(['email' => self::EMAIL, 'password' => 'wrong-pass1']),
            'Invalid email or password.'
        );
    }

    // Two different messages would tell a stranger which emails have an account here.
    public function testAnUnknownEmailGetsTheSameMessageAsAWrongPassword(): void
    {
        $this->assertUnauthorized(
            fn () => $this->service->login(['email' => 'nobody@example.com', 'password' => self::PASSWORD]),
            'Invalid email or password.'
        );
    }

    // After the password is right, nothing is left to hide: the user learns why the door stays shut.
    public function testADeactivatedUserCannotLogInEvenWithTheRightPassword(): void
    {
        $user = $this->registeredUser();
        $this->db->prepare('UPDATE users SET is_active = 0 WHERE id = :id')->execute(['id' => $user['id']]);

        $this->assertUnauthorized(
            fn () => $this->service->login(['email' => self::EMAIL, 'password' => self::PASSWORD]),
            'This account is deactivated.'
        );
    }

    public function testLoginRequiresEmailAndPassword(): void
    {
        $this->assertRejected(fn () => $this->service->login(['password' => self::PASSWORD]), "Field 'email' is required.");
        $this->assertRejected(fn () => $this->service->login(['email' => self::EMAIL]), "Field 'password' is required.");
    }

    // JSON can send an array or a number: strtolower() on that would be a crash (500) instead of a 400.
    public function testLoginRejectsAnEmailOrPasswordThatIsNotText(): void
    {
        $this->assertRejected(fn () => $this->service->login(['email' => ['a@b.c'], 'password' => self::PASSWORD]), "Field 'email' must be text.");
        $this->assertRejected(fn () => $this->service->login(['email' => self::EMAIL, 'password' => 12345678]), "Field 'password' must be text.");
    }

    /* ---------- what login writes to the logs ---------- */

    public function testASuccessfulLoginIsWrittenToInfoLogWithUserAndRole(): void
    {
        $user = $this->registeredUser();

        $this->service->login(['email' => self::EMAIL, 'password' => self::PASSWORD]);

        $this->assertMatchesRegularExpression('/^\[[\d-]+ [\d:]+\] INFO LOGIN user=' . $user['id'] . ' role=operator?$/m', $this->logText('info.log'));
        $this->assertSame('', $this->logText('error.log'));
    }

    public function testAFailedLoginIsWrittenToErrorLogWithTheEmailButNeverThePassword(): void
    {
        $this->registeredUser();

        $this->assertUnauthorized(
            fn () => $this->service->login(['email' => 'Auth-Test@Example.com', 'password' => 'wrong-pass1']),
            'Invalid email or password.'
        );

        $errorLog = $this->logText('error.log');
        $this->assertMatchesRegularExpression('/^\[[\d-]+ [\d:]+\] ERROR LOGIN FAILED email=auth-test@example\.com?$/m', $errorLog);
        $this->assertStringNotContainsString('wrong-pass1', $errorLog);
        $this->assertSame('', $this->logText('info.log'));
    }

    public function testARefusedDeactivatedLoginIsWrittenToErrorLogToo(): void
    {
        $user = $this->registeredUser();
        $this->db->prepare('UPDATE users SET is_active = 0 WHERE id = :id')->execute(['id' => $user['id']]);

        $this->assertUnauthorized(
            fn () => $this->service->login(['email' => self::EMAIL, 'password' => self::PASSWORD]),
            'This account is deactivated.'
        );

        $this->assertMatchesRegularExpression('/ERROR LOGIN FAILED email=auth-test@example\.com account deactivated?$/m', $this->logText('error.log'));
        $this->assertSame('', $this->logText('info.log'));
    }

    /* ---------- the token ---------- */

    // Option B: the role is read from the database on every request, so the token carries only who the user is.
    // A role in it would invite a later route to trust it.
    public function testTheTokenCarriesOnlyTheUserIdAndTheTimes(): void
    {
        $user = $this->registeredUser();

        $token = $this->service->login(['email' => self::EMAIL, 'password' => self::PASSWORD])['token'];

        $payload = (array) JWT::decode($token, new Key(Config::JWT_SECRET(), 'HS256'));
        $this->assertEqualsCanonicalizing(['user', 'iat', 'exp'], array_keys($payload));
        $this->assertSame(['id' => $user['id']], (array) $payload['user']);
    }

    public function testTheTokenExpiresAfterJwtExpirationSeconds(): void
    {
        $this->registeredUser();

        $token = $this->service->login(['email' => self::EMAIL, 'password' => self::PASSWORD])['token'];

        $payload = JWT::decode($token, new Key(Config::JWT_SECRET(), 'HS256'));
        $this->assertSame((int) Config::JWT_EXPIRATION(), $payload->exp - $payload->iat);
    }

    /* ---------- userFromToken: what AuthMiddleware asks on every request ---------- */

    public function testUserFromTokenReturnsTheUserWithoutTheHash(): void
    {
        $user = $this->registeredUser();
        $token = $this->service->login(['email' => self::EMAIL, 'password' => self::PASSWORD])['token'];

        $found = $this->service->userFromToken($token);

        $this->assertSame($user['id'], $found['id']);
        $this->assertSame('operator', $found['role']);
        $this->assertArrayNotHasKey('password_hash', $found);
    }

    public function testTheRoleComesFromTheDatabaseNotFromTheToken(): void
    {
        $user = $this->registeredUser();
        $token = $this->service->login(['email' => self::EMAIL, 'password' => self::PASSWORD])['token'];
        $this->db->prepare("UPDATE users SET role = 'admin' WHERE id = :id")->execute(['id' => $user['id']]);

        $this->assertSame('admin', $this->service->userFromToken($token)['role']);
    }

    // The token is still valid for hours, but the account is not: Option B makes the deactivation count at once.
    public function testATokenOfAUserDeactivatedAfterLoginIsRefused(): void
    {
        $user = $this->registeredUser();
        $token = $this->service->login(['email' => self::EMAIL, 'password' => self::PASSWORD])['token'];
        $this->db->prepare('UPDATE users SET is_active = 0 WHERE id = :id')->execute(['id' => $user['id']]);

        $this->assertUnauthorized(fn () => $this->service->userFromToken($token), 'This account is deactivated.');
    }

    public function testATokenOfAUserWhoNoLongerExistsIsRefused(): void
    {
        $user = $this->registeredUser();
        $token = $this->service->login(['email' => self::EMAIL, 'password' => self::PASSWORD])['token'];
        $this->db->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $user['id']]);

        $this->assertUnauthorized(fn () => $this->service->userFromToken($token), 'Invalid authentication token.');
    }

    public function testAnExpiredTokenIsRefusedWithItsOwnMessage(): void
    {
        $user = $this->registeredUser();
        $token = $this->tokenWith(['user' => ['id' => $user['id']], 'iat' => time() - 200, 'exp' => time() - 100]);

        $this->assertUnauthorized(fn () => $this->service->userFromToken($token), 'Your session has expired. Please log in again.');
    }

    public function testATokenSignedWithAnotherSecretIsRefused(): void
    {
        $user = $this->registeredUser();
        $token = $this->tokenWith(['user' => ['id' => $user['id']], 'exp' => time() + 100], str_repeat('x', 40));

        $this->assertUnauthorized(fn () => $this->service->userFromToken($token), 'Invalid authentication token.');
    }

    // The attack the signature exists for: keep the signature of an operator's token, write the admin's id into it.
    public function testATokenWhosePayloadWasChangedIsRefused(): void
    {
        $this->registeredUser();
        $token = $this->service->login(['email' => self::EMAIL, 'password' => self::PASSWORD])['token'];
        [$header, , $signature] = explode('.', $token);
        $forgedPayload = $this->base64Url(json_encode(['user' => ['id' => 1], 'iat' => time(), 'exp' => time() + 100]));

        $this->assertUnauthorized(fn () => $this->service->userFromToken("$header.$forgedPayload.$signature"), 'Invalid authentication token.');
    }

    public function testSomethingThatIsNotATokenIsRefused(): void
    {
        $this->assertUnauthorized(fn () => $this->service->userFromToken('not-a-token'), 'Invalid authentication token.');
    }

    // Correctly signed, but without a user in it: without a check, reading ->user->id would be a PHP warning (500).
    public function testAValidlySignedTokenWithoutAUserIsRefused(): void
    {
        $token = $this->tokenWith(['exp' => time() + 100]);

        $this->assertUnauthorized(fn () => $this->service->userFromToken($token), 'Invalid authentication token.');
    }
}
