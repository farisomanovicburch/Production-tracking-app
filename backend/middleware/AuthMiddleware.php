<?php
require_once __DIR__ . '/../utils/exceptions.php';

/*
 * Runs before a protected route (FlightPHP calls before() itself). If it throws, the route never runs; the error
 * handler answers 401. If it returns, the route can ask who is calling:
 *
 *   Flight::get('user')['id'], Flight::get('user')['role']
 *
 * ErrorMiddleware and RequestLogMiddleware read that same key for their log lines.
 */
class AuthMiddleware
{
    public function before($params)
    {
        $token = $this->readToken();
        if ($token === null) {
            throw new UnauthorizedException('Missing authentication token.');
        }

        // The checking itself (signature, expiry, is the user still active) is in AuthService, next to the code that
        // creates the token, so the two can never disagree about what a token contains.
        $user = Flight::authService()->userFromToken($token);

        // Only what routes need, and from the database: never the token's own claims.
        Flight::set('user', ['id' => $user['id'], 'role' => $user['role']]);
    }

    // "Authorization: Bearer <token>". Apache sometimes moves the header to REDIRECT_HTTP_AUTHORIZATION (todo.md item 6).
    private function readToken()
    {
        $header = Flight::request()->getHeader('Authorization')
            ?: ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');

        // Any other scheme (Basic ...) is the same as no token.
        if (preg_match('/^Bearer\s+(\S+)$/i', trim($header), $match)) {
            return $match[1];
        }
        return null;
    }
}
