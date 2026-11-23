<?php
require_once __DIR__ . '/ApiTestCase.php';

// Happy path + main failure case per route. The rules themselves are tested in UserServiceTest;
// these tests prove each URL reaches the right service method and answers the documented status.
final class UserRoutesTest extends ApiTestCase
{
    private function createUserThroughTheApi(string $email): array
    {
        return $this->request('POST', '/backend/users', ['full_name' => 'Route Test', 'email' => $email, 'password' => 'route1234']);
    }

    public function testListUsersWithoutPasswordHashes(): void
    {
        $response = $this->request('GET', '/backend/users');

        $this->assertSame(200, $response['status']);
        $this->assertContains('admin@example.com', array_column($response['body'], 'email'));
        $this->assertArrayNotHasKey('password_hash', $response['body'][0]);
        $this->assertDocumented('GET', '/users', 200);
    }

    public function testGetOneUser(): void
    {
        $response = $this->request('GET', '/backend/users/1');

        $this->assertSame(200, $response['status']);
        $this->assertSame('admin@example.com', $response['body']['email']);
        $this->assertDocumented('GET', '/users/{id}', 200);
    }

    public function testGetAnUnknownUserIsNotFound(): void
    {
        $response = $this->request('GET', '/backend/users/999999');

        // The message proves the 404 comes from the service, not from a route that does not exist.
        $this->assertSame(['status' => 404, 'error' => 'User not found.'], $response['body']);
        $this->assertDocumented('GET', '/users/{id}', 404);
    }

    public function testCreateAUserAnswers201WithTheNewUser(): void
    {
        $response = $this->createUserThroughTheApi('route-create@example.com');

        $this->assertSame(201, $response['status']);
        $this->assertIsInt($response['body']['id']);
        $this->assertArrayNotHasKey('password_hash', $response['body']);
        $this->assertDocumented('POST', '/users', 201);
    }

    public function testCreateAUserWithInvalidDataIsABadRequest(): void
    {
        $response = $this->request('POST', '/backend/users', ['full_name' => 'Route Test', 'email' => 'route-invalid@example.com']);

        $this->assertSame(400, $response['status']);
        $this->assertSame("Field 'password' is required.", $response['body']['error']);
        $this->assertDocumented('POST', '/users', 400);
    }

    public function testUpdateAUser(): void
    {
        $id = $this->createUserThroughTheApi('route-update@example.com')['body']['id'];

        $response = $this->request('PUT', "/backend/users/$id", ['full_name' => 'Route Renamed', 'email' => 'route-update@example.com']);

        $this->assertSame(200, $response['status']);
        $this->assertSame('Route Renamed', $response['body']['full_name']);
        $this->assertDocumented('PUT', '/users/{id}', 200);
    }

    public function testUpdateAnUnknownUserIsNotFound(): void
    {
        $response = $this->request('PUT', '/backend/users/999999', ['full_name' => 'Nobody', 'email' => 'nobody@example.com']);

        $this->assertSame(['status' => 404, 'error' => 'User not found.'], $response['body']);
        $this->assertDocumented('PUT', '/users/{id}', 404);
    }

    public function testUpdateAUserWithInvalidDataIsABadRequest(): void
    {
        $response = $this->request('PUT', '/backend/users/2', ['full_name' => 'Demo Operator', 'email' => 'admin@example.com']);

        $this->assertSame(400, $response['status']);
        $this->assertDocumented('PUT', '/users/{id}', 400);
    }

    public function testDeleteAUser(): void
    {
        $id = $this->createUserThroughTheApi('route-delete@example.com')['body']['id'];

        $response = $this->request('DELETE', "/backend/users/$id");

        $this->assertSame(200, $response['status']);
        $this->assertSame(['message' => 'User deleted.'], $response['body']);
        $this->assertSame(404, $this->request('GET', "/backend/users/$id")['status']);
        $this->assertDocumented('DELETE', '/users/{id}', 200);
    }

    public function testDeleteAUserWithHistoryIsABadRequest(): void
    {
        $response = $this->request('DELETE', '/backend/users/2');

        $this->assertSame(400, $response['status']);
        $this->assertStringContainsString('Deactivate the account instead.', $response['body']['error']);
        $this->assertDocumented('DELETE', '/users/{id}', 400);
    }

    public function testDeleteAnUnknownUserIsNotFound(): void
    {
        $response = $this->request('DELETE', '/backend/users/999999');

        $this->assertSame(['status' => 404, 'error' => 'User not found.'], $response['body']);
        $this->assertDocumented('DELETE', '/users/{id}', 404);
    }
}
