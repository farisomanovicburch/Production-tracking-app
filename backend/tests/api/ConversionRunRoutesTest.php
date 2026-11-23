<?php
require_once __DIR__ . '/ApiTestCase.php';

// Happy path + main failure case per route. The rules themselves are tested in ConversionRunServiceTest.
final class ConversionRunRoutesTest extends ApiTestCase
{
    private const NOT_FOUND = ['status' => 404, 'error' => 'Conversion run not found.'];

    private function createConversion(string $code): int
    {
        return $this->request('POST', '/backend/conversion-runs', $this->conversionBody($this->createConversionSetup($code)))['body']['id'];
    }

    public function testListConversionsWithoutTheirValues(): void
    {
        $response = $this->request('GET', '/backend/conversion-runs');

        $this->assertSame(200, $response['status']);
        $this->assertContains('Bag machine 1', array_column($response['body'], 'machine_name'));
        $this->assertArrayNotHasKey('parameter_values', $response['body'][0]);
        $this->assertDocumented('GET', '/conversion-runs', 200);
    }

    // Sample conversion 1: 528 kg of film became 500 kg of bags.
    public function testGetOneConversionWithItsValuesAndScrap(): void
    {
        $response = $this->request('GET', '/backend/conversion-runs/1');

        $this->assertSame(200, $response['status']);
        $this->assertSame('28.000', $response['body']['scrap_kg']);
        $this->assertSame(['Weld time', 'Cooling time', 'Speed'], array_column($response['body']['parameter_values'], 'parameter_name'));
        $this->assertDocumented('GET', '/conversion-runs/{id}', 200);
    }

    public function testGetAnUnknownConversionIsNotFound(): void
    {
        $response = $this->request('GET', '/backend/conversion-runs/999999');

        $this->assertSame(self::NOT_FOUND, $response['body']);
        $this->assertDocumented('GET', '/conversion-runs/{id}', 404);
    }

    public function testCreateAConversionAnswers201(): void
    {
        $response = $this->request('POST', '/backend/conversion-runs', $this->conversionBody($this->createConversionSetup('CR-ADD')));

        $this->assertSame(201, $response['status']);
        $this->assertSame(['2026-11-03 07:00:00', '28.000', '0.600'], [
            $response['body']['started_at'], $response['body']['scrap_kg'], $response['body']['parameter_values'][0]['value'],
        ]);
        $this->assertDocumented('POST', '/conversion-runs', 201);
    }

    public function testCreateAConversionWithMoreOutputThanInputIsABadRequest(): void
    {
        $response = $this->request('POST', '/backend/conversion-runs', $this->conversionBody($this->createConversionSetup('CR-BAD'), ['output_kg' => 600]));

        $this->assertSame(['status' => 400, 'error' => 'The output cannot weigh more than the input.'], $response['body']);
        $this->assertDocumented('POST', '/conversion-runs', 400);
    }

    public function testUpdateAConversion(): void
    {
        $id = $this->createConversion('CR-PUT');

        $response = $this->request('PUT', "/backend/conversion-runs/$id", ['output_kg' => 510, 'notes' => 'Recounted']);

        $this->assertSame(200, $response['status']);
        $this->assertSame(['510.000', '18.000', 'Recounted'], [$response['body']['output_kg'], $response['body']['scrap_kg'], $response['body']['notes']]);
        $this->assertDocumented('PUT', '/conversion-runs/{id}', 200);
    }

    public function testUpdateAConversionWithTheEndBeforeTheStartIsABadRequest(): void
    {
        $id = $this->createConversion('CR-TIME');

        $response = $this->request('PUT', "/backend/conversion-runs/$id", ['ended_at' => '2026-11-03T06:00']);

        $this->assertSame(['status' => 400, 'error' => 'The conversion must end after it starts.'], $response['body']);
        $this->assertDocumented('PUT', '/conversion-runs/{id}', 400);
    }

    public function testUpdateAnUnknownConversionIsNotFound(): void
    {
        $response = $this->request('PUT', '/backend/conversion-runs/999999', ['notes' => 'x']);

        $this->assertSame(self::NOT_FOUND, $response['body']);
        $this->assertDocumented('PUT', '/conversion-runs/{id}', 404);
    }

    public function testDeleteAConversion(): void
    {
        $id = $this->createConversion('CR-DEL');

        $response = $this->request('DELETE', "/backend/conversion-runs/$id");

        $this->assertSame(['message' => 'Conversion run deleted.'], $response['body']);
        $this->assertSame(404, $this->request('GET', "/backend/conversion-runs/$id")['status']);
        $this->assertDocumented('DELETE', '/conversion-runs/{id}', 200);
    }

    public function testDeleteAnUnknownConversionIsNotFound(): void
    {
        $response = $this->request('DELETE', '/backend/conversion-runs/999999');

        $this->assertSame(self::NOT_FOUND, $response['body']);
        $this->assertDocumented('DELETE', '/conversion-runs/{id}', 404);
    }
}
