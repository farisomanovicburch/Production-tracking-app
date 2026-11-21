<?php
/*
 * A stand-in for api.open-meteo.com, run with php -S by the weather tests, so they work without internet and can
 * make the "API" fail on purpose. The first part of the path picks the behaviour:
 *
 *   /ok/...          a normal forecast (and a 400, like the real API, when a required parameter is missing)
 *   /thunder/...     a normal forecast with a thunderstorm in the coming hours
 *   /error/...       the real API's error answer: HTTP 400 {"error": true, "reason": "..."}
 *   /garbage/...     HTTP 200 with an HTML page instead of JSON
 *   /incomplete/...  HTTP 200 with JSON that lacks the hourly forecast
 *   /slow/...        a normal forecast, but only after 3 seconds
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$scenario = explode('/', trim($path, '/'))[0];

function answer(int $status, string $body, string $type = 'application/json'): void
{
    http_response_code($status);
    header("Content-Type: $type");
    echo $body;
}

function openMeteoError(string $reason): void
{
    answer(400, json_encode(['error' => true, 'reason' => $reason]));
}

function forecast(array $hourlyCodes): string
{
    $hours = [];
    for ($hour = 14; $hour < 26; $hour++) {
        $hours[] = sprintf('2026-11-%02dT%02d:00', $hour < 24 ? 21 : 22, $hour % 24);
    }
    return json_encode([
        'latitude' => 43.86,
        'longitude' => 18.42,
        'current' => [
            'time' => '2026-11-21T14:00',
            'temperature_2m' => 14.2,
            'apparent_temperature' => 12.1,
            'relative_humidity_2m' => 62,
            'wind_speed_10m' => 3.1,
            'weather_code' => 2,
        ],
        'hourly' => [
            'time' => $hours,
            'temperature_2m' => [14.2, 15.1, 15.3, 14.8, 13.9, 12.0, 11.4, 10.8, 10.1, 9.4, 8.9, 8.0],
            'weather_code' => $hourlyCodes,
        ],
    ]);
}

// The checks the real API makes that matter here: a missing or impossible location, and the variables the card needs.
function checkParameters(): bool
{
    $latitude = $_GET['latitude'] ?? null;
    if ($latitude === null || !is_numeric($latitude) || abs((float) $latitude) > 90) {
        openMeteoError("Latitude must be in range of -90 to 90°. Given: $latitude.");
        return false;
    }
    $current = explode(',', $_GET['current'] ?? '');
    foreach (['temperature_2m', 'apparent_temperature', 'relative_humidity_2m', 'wind_speed_10m', 'weather_code'] as $variable) {
        if (!in_array($variable, $current, true)) {
            openMeteoError("This fake API expects current=$variable.");
            return false;
        }
    }
    if (($_GET['wind_speed_unit'] ?? '') !== 'ms' || !isset($_GET['longitude'], $_GET['hourly'], $_GET['forecast_hours'])) {
        openMeteoError('This fake API expects longitude, hourly, forecast_hours and wind_speed_unit=ms.');
        return false;
    }
    return true;
}

$clearDay = [2, 1, 1, 2, 3, 3, 3, 61, 61, 3, 3, 2];

switch ($scenario) {
    case 'ok':
        if (checkParameters()) {
            answer(200, forecast($clearDay));
        }
        break;
    case 'thunder':
        if (checkParameters()) {
            answer(200, forecast([2, 3, 3, 3, 3, 95, 95, 61, 61, 3, 3, 2]));
        }
        break;
    case 'error':
        openMeteoError('Cannot initialize WeatherVariable from invalid String value tempeture_2m for key current');
        break;
    case 'garbage':
        answer(200, '<html><body>Maintenance</body></html>', 'text/html');
        break;
    case 'incomplete':
        answer(200, json_encode(['current' => ['time' => '2026-11-21T14:00', 'temperature_2m' => 14.2]]));
        break;
    case 'slow':
        sleep(3);
        answer(200, forecast($clearDay));
        break;
    default:
        answer(404, json_encode(['error' => true, 'reason' => "Unknown scenario '$scenario'."]));
}
