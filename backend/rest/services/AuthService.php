<?php
require_once __DIR__ . '/UserService.php';
require_once __DIR__ . '/../dao/UserDao.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../utils/Roles.php';
require_once __DIR__ . '/../../utils/exceptions.php';
require_once __DIR__ . '/../../utils/Validator.php';
require_once __DIR__ . '/../../config/config.php';

use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/*
 * Who is logged in: registering, logging in, and checking the token a client sends back. It is not an entity
 * service (there is no "auth" table, so no CRUD to inherit): the users table belongs to UserService, which register()
 * reuses so the password rules and the hashing live in one place.
 */
class AuthService
{
    private $userDao;
    private $userService;

    // Constructor arguments let a test hand in other objects, like the other services.
    public function __construct($userDao = null, $userService = null)
    {
        $this->userDao = $userDao ?? new UserDao();
        $this->userService = $userService ?? new UserService($this->userDao);
    }

    // Returns the same shape as login(), so a new user is logged in right away.
    public function register($data)
    {
        // An allow-list, not "everything except": role and is_active decide what the account may do, so only an admin
        // (through POST /users) may set them. Anyone who registers is an active operator.
        $account = array_intersect_key($data, array_flip(['full_name', 'email', 'password']));
        $account['role'] = Roles::OPERATOR;

        $user = $this->userService->add($account);
        return ['user' => $user, 'token' => $this->createToken($user['id'])];
    }

    public function login($data)
    {
        Validator::required($data, ['email', 'password']);
        foreach (['email', 'password'] as $field) {
            // JSON can send an array or a number, and strtolower() on that would crash (500) instead of answering 400.
            if (!is_string($data[$field])) {
                throw new ValidationException("Field '$field' must be text.");
            }
        }
        $email = strtolower(trim($data['email']));

        // getByEmail() returns the hash: it is read here, compared, and never leaves this method.
        $user = $this->userDao->getByEmail($email);

        // One message for "no such email" and "wrong password": two messages would tell a stranger which emails
        // have an account. Limit: an unknown email skips the slow bcrypt check, so it answers a little faster.
        if (!$user || !password_verify($data['password'], $user['password_hash'])) {
            Logger::error("LOGIN FAILED email=$email");
            throw new UnauthorizedException('Invalid email or password.');
        }

        // Checked after the password: now the user has proven who they are, so there is nothing left to hide.
        if (!$user['is_active']) {
            Logger::error("LOGIN FAILED email=$email account deactivated");
            throw new UnauthorizedException('This account is deactivated.');
        }

        Logger::info("LOGIN user={$user['id']} role={$user['role']}");
        // The user is read again without the hash, so no code path can send it to the client by accident.
        return ['user' => $this->userDao->getById($user['id']), 'token' => $this->createToken($user['id'])];
    }

    /*
     * What AuthMiddleware asks on every request: who is behind this token? Returns the user (without the hash) or
     * throws a 401. The token only proves "this is user 7, and the server signed it"; whether user 7 may still log in
     * and what their role is now comes from the database each time (stale tokens, Option B in todo.md), so a
     * deactivation or a role change counts at once instead of when the token expires.
     */
    public function userFromToken($token)
    {
        try {
            $decoded = JWT::decode($token, new Key(Config::JWT_SECRET(), 'HS256'));
        } catch (ExpiredException $exception) {
            throw new UnauthorizedException('Your session has expired. Please log in again.');
        } catch (Exception $exception) {
            // A changed payload, another secret, a half-copied token, plain text: the client needs no more detail.
            throw new UnauthorizedException('Invalid authentication token.');
        }

        // Correctly signed does not mean complete: a token without a user must be a 401, not a PHP warning.
        $id = $decoded->user->id ?? null;
        $user = is_int($id) ? $this->userDao->getById($id) : null;
        if (!$user) {
            throw new UnauthorizedException('Invalid authentication token.');
        }
        if (!$user['is_active']) {
            throw new UnauthorizedException('This account is deactivated.');
        }
        return $user;
    }

    // Anyone can read the payload (it is only encoded, not encrypted), so it holds no email, role or secret.
    private function createToken($userId)
    {
        $now = time();
        return JWT::encode(
            ['user' => ['id' => $userId], 'iat' => $now, 'exp' => $now + (int) Config::JWT_EXPIRATION()],
            Config::JWT_SECRET(),
            'HS256'
        );
    }
}
