<?php
require_once __DIR__ . '/ApiTestCase.php';

use Firebase\JWT\JWT;

// The real app over HTTP. Every test registers its own user under an email of its own: route tests are not rolled
// back, and the sample accounts are only read, never changed.
final class AuthRoutesTest extends ApiTestCase
{
    private const PASSWORD = 'secret123';
    private const TIME = '\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]';

    private string $logFolder;

    protected function setUp(): void
    {
        $this->logFolder = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'auth-routes-test-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->logFolder . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->logFolder)) {
            rmdir($this->logFolder);
        }
    }

    private function register(string $code, array $overrides = [], ?string $baseUrl = null): array
    {
        return $this->request('POST', '/backend/auth/register', array_merge([
            'full_name' => "Auth route user $code", 'email' => "auth.$code@example.com", 'password' => self::PASSWORD,
        ], $overrides), $baseUrl);
    }

    private function bearer(string $token): array
    {
        return ["Authorization: Bearer $token"];
    }

    private function logText(string $file): string
    {
        $path = $this->logFolder . DIRECTORY_SEPARATOR . $file;
        return file_exists($path) ? file_get_contents($path) : '';
    }

    /* ---------- POST /auth/register ---------- */

    public function testRegisterAnswers201WithTheUserAndAToken(): void
    {
        $response = $this->register('REG-OK');

        $this->assertSame(201, $response['status']);
        $this->assertSame('auth.reg-ok@example.com', $response['body']['user']['email']);
        $this->assertNotEmpty($response['body']['token']);
        $this->assertArrayNotHasKey('password_hash', $response['body']['user']);
        $this->assertDocumented('POST', '/auth/register', 201);
    }

    public function testRegisterIgnoresTheRoleTheClientSends(): void
    {
        $response = $this->register('REG-ROLE', ['role' => 'admin']);

        $this->assertSame('operator', $response['body']['user']['role']);
    }

    public function testRegisterWithAWeakPasswordAnswers400(): void
    {
        $response = $this->register('REG-WEAK', ['password' => 'abc']);

        $this->assertSame(400, $response['status']);
        $this->assertDocumented('POST', '/auth/register', 400);
    }

    /* ---------- POST /auth/login ---------- */

    public function testLoginAnswers200WithTheUserAndAToken(): void
    {
        $this->register('LOGIN-OK');

        $response = $this->request('POST', '/backend/auth/login', ['email' => 'Auth.LOGIN-OK@example.com', 'password' => self::PASSWORD]);

        $this->assertSame(200, $response['status']);
        $this->assertSame('auth.login-ok@example.com', $response['body']['user']['email']);
        $this->assertNotEmpty($response['body']['token']);
        $this->assertDocumented('POST', '/auth/login', 200);
    }

    public function testLoginWithAWrongPasswordAndWithAnUnknownEmailAnswerTheSame401(): void
    {
        $this->register('LOGIN-BAD');

        $wrongPassword = $this->request('POST', '/backend/auth/login', ['email' => 'auth.login-bad@example.com', 'password' => 'wrong-pass1']);
        $unknownEmail = $this->request('POST', '/backend/auth/login', ['email' => 'nobody@example.com', 'password' => 'wrong-pass1']);

        $this->assertSame(401, $wrongPassword['status']);
        $this->assertSame(['status' => 401, 'error' => 'Invalid email or password.'], $wrongPassword['body']);
        $this->assertSame($wrongPassword['body'], $unknownEmail['body']);
        $this->assertDocumented('POST', '/auth/login', 401);
    }

    public function testLoginWithoutAPasswordAnswers400(): void
    {
        $response = $this->request('POST', '/backend/auth/login', ['email' => 'auth.login-bad@example.com']);

        $this->assertSame(400, $response['status']);
        $this->assertSame("Field 'password' is required.", $response['body']['error']);
        $this->assertDocumented('POST', '/auth/login', 400);
    }

    // The sample account that README promises is refused (read only: nothing about it is changed).
    public function testTheDeactivatedSampleUserCannotLogIn(): void
    {
        $response = $this->request('POST', '/backend/auth/login', ['email' => 'former@example.com', 'password' => 'former789']);

        $this->assertSame(401, $response['status']);
        $this->assertSame('This account is deactivated.', $response['body']['error']);
    }

    /* ---------- GET /auth/me ---------- */

    public function testMeAnswersWithTheLoggedInUser(): void
    {
        $token = $this->register('ME-OK')['body']['token'];

        $response = $this->request('GET', '/backend/auth/me', null, null, $this->bearer($token));

        $this->assertSame(200, $response['status']);
        $this->assertSame('auth.me-ok@example.com', $response['body']['email']);
        $this->assertSame('operator', $response['body']['role']);
        $this->assertArrayNotHasKey('password_hash', $response['body']);
        $this->assertDocumented('GET', '/auth/me', 200);
    }

    public function testMeWithoutATokenAnswers401(): void
    {
        $response = $this->request('GET', '/backend/auth/me');

        $this->assertSame(401, $response['status']);
        $this->assertSame(['status' => 401, 'error' => 'Missing authentication token.'], $response['body']);
        $this->assertDocumented('GET', '/auth/me', 401);
    }

    // Only "Bearer <token>" is a token here: another scheme (Basic) is the same as no token.
    public function testMeWithAnotherAuthorizationSchemeAnswers401(): void
    {
        $response = $this->request('GET', '/backend/auth/me', null, null, ['Authorization: Basic YWRtaW46YWRtaW4=']);

        $this->assertSame(401, $response['status']);
        $this->assertSame('Missing authentication token.', $response['body']['error']);
    }

    public function testMeWithAChangedTokenAnswers401(): void
    {
        $token = $this->register('ME-BAD')['body']['token'];
        [$header, $payload, $signature] = explode('.', $token);
        $changedPayload = rtrim(strtr(base64_encode(json_encode(['user' => ['id' => 1], 'exp' => time() + 100])), '+/', '-_'), '=');

        $response = $this->request('GET', '/backend/auth/me', null, null, $this->bearer("$header.$changedPayload.$signature"));

        $this->assertSame(401, $response['status']);
        $this->assertSame('Invalid authentication token.', $response['body']['error']);
    }

    public function testMeWithAnExpiredTokenAnswers401(): void
    {
        $id = $this->register('ME-OLD')['body']['user']['id'];
        $token = JWT::encode(['user' => ['id' => $id], 'iat' => time() - 200, 'exp' => time() - 100], Config::JWT_SECRET(), 'HS256');

        $response = $this->request('GET', '/backend/auth/me', null, null, $this->bearer($token));

        $this->assertSame(401, $response['status']);
        $this->assertSame('Your session has expired. Please log in again.', $response['body']['error']);
    }

    // The token is still valid for hours, but the account is not (stale tokens: Option B).
    public function testMeAnswers401WhenTheUserWasDeactivatedAfterLoggingIn(): void
    {
        $registered = $this->register('ME-OFF')['body'];
        $this->request('PUT', '/backend/users/' . $registered['user']['id'], [
            'full_name' => $registered['user']['full_name'], 'email' => $registered['user']['email'], 'is_active' => false,
        ]);

        $response = $this->request('GET', '/backend/auth/me', null, null, $this->bearer($registered['token']));

        $this->assertSame(401, $response['status']);
        $this->assertSame('This account is deactivated.', $response['body']['error']);
    }

    /* ---------- what reaches the logs ---------- */

    public function testALoginIsLoggedButNeitherThePasswordNorTheTokenIs(): void
    {
        $server = self::startServer(['LOG_DIRECTORY' => $this->logFolder]);
        $registered = $this->register('LOG-OK', [], $server)['body'];
        $login = $this->request('POST', '/backend/auth/login', ['email' => 'auth.log-ok@example.com', 'password' => self::PASSWORD], $server);
        $this->request('GET', '/backend/auth/me', null, $server, $this->bearer($login['body']['token']));

        $info = $this->logText('info.log');
        $this->assertMatchesRegularExpression('/^' . self::TIME . ' INFO LOGIN user=' . $registered['user']['id'] . ' role=operator?$/m', $info);
        $this->assertMatchesRegularExpression('/^' . self::TIME . ' INFO GET \/auth\/me 200 user=' . $registered['user']['id'] . ' role=operator \d+ms?$/m', $info);
        foreach ([$info, $this->logText('error.log')] as $log) {
            $this->assertStringNotContainsString(self::PASSWORD, $log);
            $this->assertStringNotContainsString($login['body']['token'], $log);
        }
    }

    public function testAFailedLoginIsLoggedWithTheEmailButNotThePassword(): void
    {
        $server = self::startServer(['LOG_DIRECTORY' => $this->logFolder]);

        $this->request('POST', '/backend/auth/login', ['email' => 'Intruder@Example.com', 'password' => 'guess-pass1'], $server);

        $error = $this->logText('error.log');
        $this->assertMatchesRegularExpression('/^' . self::TIME . ' ERROR LOGIN FAILED email=intruder@example\.com?$/m', $error);
        $this->assertMatchesRegularExpression('/^' . self::TIME . ' ERROR POST \/auth\/login 401 user=- role=- Invalid email or password\.?$/m', $error);
        $this->assertStringNotContainsString('guess-pass1', $error);
    }
}
