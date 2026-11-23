<?php
require_once __DIR__ . '/../api/ApiTestCase.php';
require_once __DIR__ . '/../../rest/services/WeatherService.php';
require_once __DIR__ . '/../../utils/Logger.php';

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

    private string $logFolder;

    // The Logger writes into a folder of this test's own, so the test can read what the service logged.
    protected function setUp(): void
    {
        $this->logFolder = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'weather-log-' . bin2hex(random_bytes(6));
        Logger::setDirectory($this->logFolder);
    }

    protected function tearDown(): void
    {
        Logger::setDirectory(null);
        foreach (glob($this->logFolder . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->logFolder)) {
            rmdir($this->logFolder);
        }
    }

    // '' when nothing was logged to that file.
    private function logged(string $file): string
    {
        $path = $this->logFolder . DIRECTORY_SEPARATOR . $file;
        return file_exists($path) ? (string) file_get_contents($path) : '';
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
        $this->assertStringContainsString($loggedReason, $this->logged('error.log'));
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

    /* ---------- every call to the external API is written down ---------- */

    public function testACallThatGetsAnAnswerIsLoggedWithItsStatusAndDuration(): void
    {
        $this->service('ok')->getForecast();

        $this->assertMatchesRegularExpression(
            '/\] INFO EXTERNAL Open-Meteo GET http:\/\/127\.0\.0\.1:\d+\/ok\/v1\/forecast 200 \d+ms\R$/',
            $this->logged('info.log')
        );
        $this->assertSame('', $this->logged('error.log'));
    }

    // The call itself worked (Open-Meteo answered), so it is in info.log; that it refused is an error.
    public function testAnErrorAnswerIsLoggedWithItsStatusAndItsReason(): void
    {
        $this->assertUnavailable($this->service('ok', ['latitude' => 91]), 'HTTP 400: Latitude must be in range of -90 to 90');

        $this->assertMatchesRegularExpression('/\] INFO EXTERNAL Open-Meteo GET \S+ 400 \d+ms\R$/', $this->logged('info.log'));
        $this->assertMatchesRegularExpression('/\] ERROR Weather API \(Open-Meteo\) unavailable: HTTP 400: /', $this->logged('error.log'));
    }

    // No answer, no status to write down: only the error, with how long the call took before it gave up.
    public function testACallThatGetsNoAnswerIsLoggedAsAnErrorOnly(): void
    {
        $this->assertUnavailable($this->service('ok', ['url' => 'http://127.0.0.1:1/v1/forecast']), 'request failed');

        $this->assertMatchesRegularExpression('/\] ERROR Weather API \(Open-Meteo\) unavailable: request failed after \d+ms: /', $this->logged('error.log'));
        $this->assertSame('', $this->logged('info.log'));
    }

    public function testTheLocationOfTheFactoryIsNotLogged(): void
    {
        $this->service('ok')->getForecast();

        $this->assertStringNotContainsString('43.8563', $this->logged('info.log'));
        $this->assertStringNotContainsString('latitude', $this->logged('info.log'));
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
