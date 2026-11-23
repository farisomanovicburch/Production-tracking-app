<?php
require_once __DIR__ . '/../api/ApiTestCase.php';
require_once __DIR__ . '/../../rest/services/WeatherService.php';

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/*
 * The weather service against a fake Open-Meteo (tests/fixtures/fake-open-meteo.php), so the tests need no internet
 * and can make the API fail on purpose. It extends ApiTestCase only to start that fake server; it uses no database.
 * One test calls the real API: it is in the "internet" group, which phpunit.xml leaves out unless asked for.
 */
final class WeatherServiceTest extends ApiTestCase
{
    private const UNAVAILABLE = 'The weather service is not available right now. Please try again later.';

    private string $logFile;
    private string|false $previousLog;

    // error_log() goes to a file of this test's own, so the test can read what the service logged.
    protected function setUp(): void
    {
        $this->logFile = tempnam(sys_get_temp_dir(), 'weather-log-');
        $this->previousLog = ini_set('error_log', $this->logFile);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', (string) $this->previousLog);
        @unlink($this->logFile);
    }

    private function service(string $scenario, array $overrides = []): WeatherService
    {
        return new WeatherService(
            $overrides['url'] ?? self::fakeOpenMeteoUrl() . "/$scenario/v1/forecast",
            $overrides['latitude'] ?? 43.8563,
            18.4131,
            'Sarajevo',
            $overrides['timeout'] ?? 5
        );
    }

    // The service must answer 503 with a message for the user, and log the real reason for the developer.
    private function assertUnavailable(WeatherService $service, string $loggedReason): void
    {
        try {
            $service->getForecast();
            $this->fail('The forecast was returned, expected a 503.');
        } catch (ExternalServiceException $exception) {
            $this->assertSame([503, self::UNAVAILABLE], [$exception->getStatus(), $exception->getMessage()]);
        }
        $this->assertStringContainsString($loggedReason, (string) file_get_contents($this->logFile));
    }

    /* ---------- a normal answer, reduced to what the dashboard card shows ---------- */

    public function testReturnsTheCurrentWeatherForTheLocation(): void
    {
        $weather = $this->service('ok')->getForecast();

        $this->assertSame('Sarajevo', $weather['location']);
        $this->assertSame(
            ['time' => '2026-11-21T14:00', 'temperature_c' => 14.2, 'feels_like_c' => 12.1, 'humidity_percent' => 62,
                'wind_speed_ms' => 3.1, 'weather_code' => 2, 'condition' => 'partly-cloudy', 'description' => 'Partly cloudy'],
            $weather['current']
        );
    }

    // The card shows four forecasts three hours apart, starting with the first full hour after now.
    public function testTheNextHoursAreEveryThirdHourAfterNow(): void
    {
        $nextHours = $this->service('ok')->getForecast()['next_hours'];

        $this->assertSame(
            [['2026-11-21T15:00', 15.1, 'Mainly clear'], ['2026-11-21T18:00', 13.9, 'Overcast'],
                ['2026-11-21T21:00', 10.8, 'Slight rain'], ['2026-11-22T00:00', 8.9, 'Overcast']],
            array_map(fn ($hour) => [$hour['time'], $hour['temperature_c'], $hour['description']], $nextHours)
        );
    }

    // A thunderstorm at 19:00 falls between two of the shown hours, but must still raise the warning.
    public function testWarnsAboutAThunderstormAnywhereInTheComingHours(): void
    {
        $this->assertTrue($this->service('thunder')->getForecast()['thunderstorm_expected']);
    }

    public function testDoesNotWarnWithoutAThunderstorm(): void
    {
        $this->assertFalse($this->service('ok')->getForecast()['thunderstorm_expected']);
    }

    // CC BY 4.0: Open-Meteo must be credited with a link wherever its data is shown.
    public function testTheAnswerCarriesTheAttributionTheLicenceRequires(): void
    {
        $this->assertSame(
            ['text' => 'Weather data by Open-Meteo.com', 'url' => 'https://open-meteo.com/'],
            $this->service('ok')->getForecast()['attribution']
        );
    }

    /* ---------- the WMO weather codes Open-Meteo sends instead of text ---------- */

    public static function weatherCodes(): array
    {
        return [
            'clear' => [0, 'clear', 'Clear sky'],
            'partly cloudy' => [2, 'partly-cloudy', 'Partly cloudy'],
            'overcast' => [3, 'cloudy', 'Overcast'],
            'fog' => [48, 'fog', 'Depositing rime fog'],
            'drizzle' => [55, 'drizzle', 'Dense drizzle'],
            'freezing rain' => [66, 'rain', 'Light freezing rain'],
            'shower' => [82, 'rain', 'Violent rain showers'],
            'snow' => [75, 'snow', 'Heavy snowfall'],
            'snow shower' => [86, 'snow', 'Heavy snow showers'],
            'thunderstorm' => [95, 'thunderstorm', 'Thunderstorm'],
            'thunderstorm with hail' => [96, 'thunderstorm', 'Thunderstorm with slight hail'],
            'heavy thunderstorm' => [97, 'thunderstorm', 'Heavy thunderstorm'],
            'thunderstorm with heavy hail' => [99, 'thunderstorm', 'Thunderstorm with heavy hail'],
            'unknown' => [42, 'unknown', 'Unknown weather'],
        ];
    }

    #[DataProvider('weatherCodes')]
    public function testDescribesTheWeatherCode(int $code, string $condition, string $description): void
    {
        $this->assertSame(['condition' => $condition, 'description' => $description], WeatherService::describe($code));
    }

    /* ---------- failures: a 503 for the user, the reason in the log ---------- */

    public function testAnErrorAnswerFromOpenMeteoBecomesA503(): void
    {
        $this->assertUnavailable($this->service('ok', ['latitude' => 91]), 'HTTP 400: Latitude must be in range of -90 to 90');
    }

    public function testAnUnreachableServiceBecomesA503(): void
    {
        // Nothing listens on port 1, so the connection is refused at once.
        $this->assertUnavailable($this->service('ok', ['url' => 'http://127.0.0.1:1/v1/forecast']), 'request failed');
    }

    public function testASlowServiceIsCutOffByTheTimeout(): void
    {
        $started = microtime(true);

        $this->assertUnavailable($this->service('slow', ['timeout' => 1]), 'timed out');
        $this->assertLessThan(2.5, microtime(true) - $started, 'The request was not cut off after 1 second.');
    }

    public function testAnAnswerThatIsNotJsonBecomesA503(): void
    {
        $this->assertUnavailable($this->service('garbage'), 'the answer is not JSON');
    }

    public function testAnAnswerWithoutTheForecastBecomesA503(): void
    {
        $this->assertUnavailable($this->service('incomplete'), 'the answer is missing');
    }

    /* ---------- the real API, only with --group internet ---------- */

    #[Group('internet')]
    public function testTheRealOpenMeteoAnswersForTheConfiguredLocation(): void
    {
        $weather = (new WeatherService())->getForecast();

        $this->assertIsFloat((float) $weather['current']['temperature_c']);
        $this->assertCount(4, $weather['next_hours']);
    }
}
