<?php
require_once __DIR__ . '/ApiTestCase.php';

final class ErrorHandlerTest extends ApiTestCase
{
    // An HttpException thrown by a service reaches the client with its own status and message.
    public function testAServiceExceptionAnswersWithItsStatusAndMessage(): void
    {
        $response = $this->request('GET', '/backend/users/999999');

        $this->assertSame(404, $response['status']);
        $this->assertSame(['status' => 404, 'error' => 'User not found.'], $response['body']);
    }

    // Anything else is a server problem: 500 with a general message, and never SQL, file paths or a stack trace.
    public function testAnUnexpectedErrorAnswers500WithoutDetails(): void
    {
        $brokenServer = self::startServer(['DB_NAME' => 'database_that_does_not_exist']);

        $response = $this->request('GET', '/backend/users', null, $brokenServer);

        $this->assertSame(500, $response['status']);
        $this->assertSame(
            ['status' => 500, 'error' => 'Something went wrong on the server. Please try again later.'],
            $response['body']
        );
        $this->assertStringNotContainsString('SQLSTATE', $response['raw']);
    }

    public function testTheErrorSchemaDescribesWhatTheErrorHandlerSends(): void
    {
        $schema = $this->request('GET', '/backend/public/v1/docs/swagger.php')['body']['components']['schemas']['Error'];

        $this->assertSame(['status', 'error'], array_keys($schema['properties']));
    }
}
