<?php
use OpenApi\Attributes as OA;

/*
 * Documentation of rest/routes/AuthRoutes.php. AuthRoutesTest checks every status listed here against the real answers.
 * Register and login say security: [] because a user who is not logged in has no token yet.
 */
class AuthPaths
{
    #[OA\Post(
        path: '/auth/register',
        tags: ['auth'],
        security: [],
        summary: 'Create an account and log in',
        description: 'The new account is always an active operator: a role or is_active in the body is ignored. ' .
            'The answer holds the user and a token, so the new user is logged in right away.',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RegisterInput')),
        responses: [
            new OA\Response(response: 201, description: 'The new user and a token', content: new OA\JsonContent(ref: '#/components/schemas/AuthResponse')),
            new OA\Response(response: 400, description: 'Invalid data, weak password or email already in use', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function postAuthRegister() {}

    #[OA\Post(
        path: '/auth/login',
        tags: ['auth'],
        security: [],
        summary: 'Log in and get a token',
        description: 'Send the token with every other request in the header "Authorization: Bearer <token>". ' .
            'An unknown email and a wrong password get the same answer. A deactivated user cannot log in.',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/LoginInput')),
        responses: [
            new OA\Response(response: 200, description: 'The user and a token', content: new OA\JsonContent(ref: '#/components/schemas/AuthResponse')),
            new OA\Response(response: 400, description: 'Email or password missing or not text', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 401, description: 'Wrong email or password, or the account is deactivated', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function postAuthLogin() {}

    #[OA\Get(
        path: '/auth/me',
        tags: ['auth'],
        security: [['bearerAuth' => []]],
        summary: 'The user the token belongs to',
        description: 'The role and the active flag are read from the database on every request, not from the token.',
        responses: [
            new OA\Response(response: 200, description: 'The logged-in user', content: new OA\JsonContent(ref: '#/components/schemas/User')),
            new OA\Response(
                response: 401,
                description: 'Missing, invalid or expired token, or the account was deactivated after the token was issued',
                content: new OA\JsonContent(ref: '#/components/schemas/Error')
            )
        ]
    )]
    public function getAuthMe() {}
}
