<?php
require_once __DIR__ . '/ApiTestCase.php';

// Happy path + main failure case per route. The rules themselves are tested in RunParameterValueServiceTest.
final class RunParameterValueRoutesTest extends ApiTestCase
{
    private const NOT_FOUND = ['status' => 404, 'error' => 'Run parameter value not found.'];

    // A run of its own with one value (the setup's parameter) and a second parameter added afterwards, without a
    // value yet: the case the child route exists for.
    private function createRun(string $code): array
    {
        $setup = $this->createRunSetup($code);
        $run = $this->request('POST', '/backend/production-runs', $this->runBody($setup))['body'];
        $newParameterId = $this->request('POST', "/backend/machines/{$setup['machine_id']}/parameters", ['name' => 'Route speed'])['body']['id'];
        return ['run_id' => $run['id'], 'value_id' => $run['parameter_values'][0]['id'], 'new_parameter_id' => $newParameterId];
    }

    public function testListTheValuesOfARun(): void
    {
        $response = $this->request('GET', '/backend/production-runs/2/parameter-values');

        $this->assertSame(200, $response['status']);
        $this->assertSame(['Zone 1 temperature', 'Screw speed', 'Haul-off speed'], array_column($response['body'], 'parameter_name'));
        $this->assertDocumented('GET', '/production-runs/{id}/parameter-values', 200);
    }

    public function testListTheValuesOfAnUnknownRunIsNotFound(): void
    {
        $response = $this->request('GET', '/backend/production-runs/999999/parameter-values');

        $this->assertSame(['status' => 404, 'error' => 'Production run not found.'], $response['body']);
        $this->assertDocumented('GET', '/production-runs/{id}/parameter-values', 404);
    }

    public function testAddAValueAnswers201(): void
    {
        $run = $this->createRun('RPV-ADD');

        $response = $this->request('POST', "/backend/production-runs/{$run['run_id']}/parameter-values", ['machine_parameter_id' => $run['new_parameter_id'], 'value' => 62]);

        $this->assertSame(201, $response['status']);
        $this->assertSame('62.000', $response['body']['value']);
        $this->assertDocumented('POST', '/production-runs/{id}/parameter-values', 201);
    }

    // Parameter 7 belongs to the bag machine.
    public function testAddAValueForAParameterOfAnotherMachineIsABadRequest(): void
    {
        $run = $this->createRun('RPV-BAD');

        $response = $this->request('POST', "/backend/production-runs/{$run['run_id']}/parameter-values", ['machine_parameter_id' => 7, 'value' => 1]);

        $this->assertSame(['status' => 400, 'error' => "The selected parameter does not belong to this run's machine."], $response['body']);
        $this->assertDocumented('POST', '/production-runs/{id}/parameter-values', 400);
    }

    public function testAddAValueToAnUnknownRunIsNotFound(): void
    {
        $response = $this->request('POST', '/backend/production-runs/999999/parameter-values', ['machine_parameter_id' => 1, 'value' => 1]);

        $this->assertSame(['status' => 404, 'error' => 'Production run not found.'], $response['body']);
        $this->assertDocumented('POST', '/production-runs/{id}/parameter-values', 404);
    }

    public function testUpdateAValue(): void
    {
        $run = $this->createRun('RPV-PUT');

        $response = $this->request('PUT', "/backend/run-parameter-values/{$run['value_id']}", ['value' => 183.5]);

        $this->assertSame(200, $response['status']);
        $this->assertSame('183.500', $response['body']['value']);
        $this->assertDocumented('PUT', '/run-parameter-values/{id}', 200);
    }

    public function testUpdateAValueWithTextIsABadRequest(): void
    {
        $run = $this->createRun('RPV-TXT');

        $response = $this->request('PUT', "/backend/run-parameter-values/{$run['value_id']}", ['value' => 'hot']);

        $this->assertSame(['status' => 400, 'error' => "Field 'value' must be a number."], $response['body']);
        $this->assertDocumented('PUT', '/run-parameter-values/{id}', 400);
    }

    public function testUpdateAnUnknownValueIsNotFound(): void
    {
        $response = $this->request('PUT', '/backend/run-parameter-values/999999', ['value' => 1]);

        $this->assertSame(self::NOT_FOUND, $response['body']);
        $this->assertDocumented('PUT', '/run-parameter-values/{id}', 404);
    }

    public function testDeleteAValue(): void
    {
        $run = $this->createRun('RPV-DEL');

        $response = $this->request('DELETE', "/backend/run-parameter-values/{$run['value_id']}");

        $this->assertSame(['message' => 'Run parameter value deleted.'], $response['body']);
        $this->assertSame([], $this->request('GET', "/backend/production-runs/{$run['run_id']}/parameter-values")['body']);
        $this->assertDocumented('DELETE', '/run-parameter-values/{id}', 200);
    }

    public function testDeleteAnUnknownValueIsNotFound(): void
    {
        $response = $this->request('DELETE', '/backend/run-parameter-values/999999');

        $this->assertSame(self::NOT_FOUND, $response['body']);
        $this->assertDocumented('DELETE', '/run-parameter-values/{id}', 404);
    }
}
