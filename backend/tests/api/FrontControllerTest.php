<?php
require_once __DIR__ . '/ApiTestCase.php';

final class FrontControllerTest extends ApiTestCase
{
    public function testTheApiRootAnswersThatTheBackendIsRunning(): void
    {
        $response = $this->request('GET', '/backend/');

        $this->assertSame(200, $response['status']);
        $this->assertSame('The backend is running.', $response['body']['message']);
    }

    // The frontend shows every error the same way, so even a URL that matches no route answers JSON.
    public function testAnUnknownEndpointAnswers404AsJsonNamingTheEndpoint(): void
    {
        $response = $this->request('DELETE', '/backend/machines/abc?force=1');

        $this->assertSame(404, $response['status']);
        $this->assertSame(
            ['status' => 404, 'error' => 'Endpoint DELETE /machines/abc does not exist.'],
            $response['body']
        );
    }
}
