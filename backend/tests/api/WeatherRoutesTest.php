<?php
require_once __DIR__ . '/ApiTestCase.php';

// Happy path + main failure case. Each test starts the app with WEATHER_API_URL pointing at the fake Open-Meteo
// or at nothing, so no test depends on the internet. The service's rules are tested in WeatherServiceTest.
final class WeatherRoutesTest extends ApiTestCase
{
    public function testGetTheWeather(): void
    {
        $app = self::startServer(['WEATHER_API_URL' => self::fakeOpenMeteoUrl() . '/ok/v1/forecast']);

        $response = $this->request('GET', '/backend/weather', null, $app);

        $this->assertSame(200, $response['status']);
        $this->assertSame(['Partly cloudy', 4, false], [
            $response['body']['current']['description'], count($response['body']['next_hours']), $response['body']['thunderstorm_expected'],
        ]);
        $this->assertDocumented('GET', '/weather', 200);
    }

    // The requirement: when the external API is down, only the weather fails, not the app.
    public function testWhenTheWeatherServiceIsDownOnlyTheWeatherFails(): void
    {
        $app = self::startServer(['WEATHER_API_URL' => 'http://127.0.0.1:1/v1/forecast']);

        $weather = $this->request('GET', '/backend/weather', null, $app);
        $machines = $this->request('GET', '/backend/machines', null, $app);

        $this->assertSame(
            ['status' => 503, 'error' => 'The weather service is not available right now. Please try again later.'],
            $weather['body']
        );
        $this->assertSame(503, $weather['status']);
        $this->assertSame(200, $machines['status']);
        $this->assertDocumented('GET', '/weather', 503);
    }
}
