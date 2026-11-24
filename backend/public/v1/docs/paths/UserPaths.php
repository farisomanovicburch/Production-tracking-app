<?php
use OpenApi\Attributes as OA;

/*
 * Documentation of rest/routes/UserRoutes.php: one empty method per route. Every status the route and its service
 * can answer with is listed; UserRoutesTest checks that against the real answers.
 */
class UserPaths
{
    #[OA\Get(
        path: '/users',
        tags: ['users'],
        summary: 'List all users (never with passwords)',
        responses: [
            new OA\Response(response: 200, description: 'All users', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/User'))),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized')
        ]
    )]
    public function getUsers() {}

    #[OA\Get(
        path: '/users/{id}',
        tags: ['users'],
        summary: 'Get one user',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 1, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'The user', content: new OA\JsonContent(ref: '#/components/schemas/User')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, description: 'User not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function getUsersById() {}

    #[OA\Post(
        path: '/users',
        tags: ['users'],
        summary: 'Create a user',
        description: 'The password is stored only as a bcrypt hash. The email is stored in lower case and must not belong to another account.',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UserInput')),
        responses: [
            new OA\Response(response: 201, description: 'The created user', content: new OA\JsonContent(ref: '#/components/schemas/User')),
            new OA\Response(response: 400, description: 'Invalid data, weak password or email already in use', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized')
        ]
    )]
    public function postUsers() {}

    #[OA\Put(
        path: '/users/{id}',
        tags: ['users'],
        summary: 'Update a user',
        description: 'Leave the password out to keep the current one. Set is_active to false to deactivate the account.',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 2, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UserInput')),
        responses: [
            new OA\Response(response: 200, description: 'The updated user', content: new OA\JsonContent(ref: '#/components/schemas/User')),
            new OA\Response(response: 400, description: 'Invalid data or email already in use', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, description: 'User not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function putUsersById() {}

    #[OA\Delete(
        path: '/users/{id}',
        tags: ['users'],
        summary: 'Delete a user who has logged nothing',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 4, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 400, description: 'The user has logged runs, conversions or maintenance: deactivate instead', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, description: 'User not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function deleteUsersById() {}
}
