<?php
require_once __DIR__ . '/ApiTestCase.php';

// Happy path + main failure case per route. The rules themselves are tested in ConversionRunParameterValueServiceTest.
final class ConversionRunParameterValueRoutesTest extends ApiTestCase
{
    private const NOT_FOUND = ['status' => 404, 'error' => 'Conversion parameter value not found.'];

    // A conversion of its own with one value, and a second parameter added afterwards without a value yet.
    private function createConversion(string $code): array
    {
        $setup = $this->createConversionSetup($code);
        $conversion = $this->request('POST', '/backend/conversion-runs', $this->conversionBody($setup))['body'];
        $newParameterId = $this->request('POST', "/backend/machines/{$setup['machine_id']}/parameters", ['name' => 'Route speed'])['body']['id'];
        return ['conversion_id' => $conversion['id'], 'value_id' => $conversion['parameter_values'][0]['id'], 'new_parameter_id' => $newParameterId];
    }

    public function testListTheValuesOfAConversion(): void
    {
        $response = $this->request('GET', '/backend/conversion-runs/2/parameter-values');

        $this->assertSame(200, $response['status']);
        $this->assertSame(['0.500', '0.300', '85.000'], array_column($response['body'], 'value'));
        $this->assertDocumented('GET', '/conversion-runs/{id}/parameter-values', 200);
    }

    public function testListTheValuesOfAnUnknownConversionIsNotFound(): void
    {
        $response = $this->request('GET', '/backend/conversion-runs/999999/parameter-values');

        $this->assertSame(['status' => 404, 'error' => 'Conversion run not found.'], $response['body']);
        $this->assertDocumented('GET', '/conversion-runs/{id}/parameter-values', 404);
    }

    public function testAddAValueAnswers201(): void
    {
        $conversion = $this->createConversion('CPV-ADD');

        $response = $this->request('POST', "/backend/conversion-runs/{$conversion['conversion_id']}/parameter-values", ['machine_parameter_id' => $conversion['new_parameter_id'], 'value' => 72]);

        $this->assertSame(201, $response['status']);
        $this->assertSame('72.000', $response['body']['value']);
        $this->assertDocumented('POST', '/conversion-runs/{id}/parameter-values', 201);
    }

    // Parameter 1 belongs to Extruder 1.
    public function testAddAValueForAParameterOfAnotherMachineIsABadRequest(): void
    {
        $conversion = $this->createConversion('CPV-BAD');

        $response = $this->request('POST', "/backend/conversion-runs/{$conversion['conversion_id']}/parameter-values", ['machine_parameter_id' => 1, 'value' => 1]);

        $this->assertSame(['status' => 400, 'error' => "The selected parameter does not belong to this conversion's machine."], $response['body']);
        $this->assertDocumented('POST', '/conversion-runs/{id}/parameter-values', 400);
    }

    public function testAddAValueToAnUnknownConversionIsNotFound(): void
    {
        $response = $this->request('POST', '/backend/conversion-runs/999999/parameter-values', ['machine_parameter_id' => 7, 'value' => 1]);

        $this->assertSame(['status' => 404, 'error' => 'Conversion run not found.'], $response['body']);
        $this->assertDocumented('POST', '/conversion-runs/{id}/parameter-values', 404);
    }

    public function testUpdateAValue(): void
    {
        $conversion = $this->createConversion('CPV-PUT');

        $response = $this->request('PUT', "/backend/conversion-run-parameter-values/{$conversion['value_id']}", ['value' => 0.55]);

        $this->assertSame(200, $response['status']);
        $this->assertSame('0.550', $response['body']['value']);
        $this->assertDocumented('PUT', '/conversion-run-parameter-values/{id}', 200);
    }

    public function testUpdateAValueWithTextIsABadRequest(): void
    {
        $conversion = $this->createConversion('CPV-TXT');

        $response = $this->request('PUT', "/backend/conversion-run-parameter-values/{$conversion['value_id']}", ['value' => 'short']);

        $this->assertSame(['status' => 400, 'error' => "Field 'value' must be a number."], $response['body']);
        $this->assertDocumented('PUT', '/conversion-run-parameter-values/{id}', 400);
    }

    public function testUpdateAnUnknownValueIsNotFound(): void
    {
        $response = $this->request('PUT', '/backend/conversion-run-parameter-values/999999', ['value' => 1]);

        $this->assertSame(self::NOT_FOUND, $response['body']);
        $this->assertDocumented('PUT', '/conversion-run-parameter-values/{id}', 404);
    }

    public function testDeleteAValue(): void
    {
        $conversion = $this->createConversion('CPV-DEL');

        $response = $this->request('DELETE', "/backend/conversion-run-parameter-values/{$conversion['value_id']}");

        $this->assertSame(['message' => 'Conversion parameter value deleted.'], $response['body']);
        $this->assertSame([], $this->request('GET', "/backend/conversion-runs/{$conversion['conversion_id']}/parameter-values")['body']);
        $this->assertDocumented('DELETE', '/conversion-run-parameter-values/{id}', 200);
    }

    public function testDeleteAnUnknownValueIsNotFound(): void
    {
        $response = $this->request('DELETE', '/backend/conversion-run-parameter-values/999999');

        $this->assertSame(self::NOT_FOUND, $response['body']);
        $this->assertDocumented('DELETE', '/conversion-run-parameter-values/{id}', 404);
    }
}
