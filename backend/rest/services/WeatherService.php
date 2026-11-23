<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../utils/exceptions.php';
require_once __DIR__ . '/../../utils/Logger.php';

/*
 * The weather at the factory, from Open-Meteo (https://open-meteo.com/, free for non-commercial use, no API key),
 * for the dashboard card: is it safe to start a run, or is a thunderstorm coming?
 *
 * The backend calls the API, not the browser, so the location and the API address stay in config.php and the
 * frontend gets one small answer in our own shape. Anything that goes wrong (no connection, too slow, an error
 * answer, an answer we cannot read) is written to error.log and becomes a 503 with a message for the user;
 * every other endpoint keeps working, because only this request waits for Open-Meteo.
 */
class WeatherService
{
    private const UNAVAILABLE = 'The weather service is not available right now. Please try again later.';
    // Finding the server should take well under a second; 3 s means it is not there.
    private const CONNECT_TIMEOUT_SECONDS = 3;
    // The card shows 4 forecasts 3 hours apart; 12 hours also cover a thunderstorm between two shown hours.
    private const FORECAST_HOURS = 12;
    private const HOURS_BETWEEN_SHOWN_FORECASTS = 3;
    private const SHOWN_FORECASTS = 4;
    private const CURRENT_VARIABLES = ['temperature_2m', 'apparent_temperature', 'relative_humidity_2m', 'wind_speed_10m', 'weather_code'];

    // CC BY 4.0 asks for this credit, with the link, next to wherever the data is shown.
    private const ATTRIBUTION = ['text' => 'Weather data by Open-Meteo.com', 'url' => 'https://open-meteo.com/'];

    /*
     * Open-Meteo sends a WMO weather code instead of words (https://open-meteo.com/en/docs, "WMO Weather
     * interpretation codes"). condition groups the codes for the frontend, which picks an icon per group;
     * description is the text the documentation gives for the code.
     */
    private const WEATHER_CODES = [
        0 => ['clear', 'Clear sky'],
        1 => ['partly-cloudy', 'Mainly clear'],
        2 => ['partly-cloudy', 'Partly cloudy'],
        3 => ['cloudy', 'Overcast'],
        45 => ['fog', 'Fog'],
        48 => ['fog', 'Depositing rime fog'],
        51 => ['drizzle', 'Light drizzle'],
        53 => ['drizzle', 'Moderate drizzle'],
        55 => ['drizzle', 'Dense drizzle'],
        56 => ['drizzle', 'Light freezing drizzle'],
        57 => ['drizzle', 'Dense freezing drizzle'],
        61 => ['rain', 'Slight rain'],
        63 => ['rain', 'Moderate rain'],
        65 => ['rain', 'Heavy rain'],
        66 => ['rain', 'Light freezing rain'],
        67 => ['rain', 'Heavy freezing rain'],
        71 => ['snow', 'Slight snowfall'],
        73 => ['snow', 'Moderate snowfall'],
        75 => ['snow', 'Heavy snowfall'],
        77 => ['snow', 'Snow grains'],
        80 => ['rain', 'Slight rain showers'],
        81 => ['rain', 'Moderate rain showers'],
        82 => ['rain', 'Violent rain showers'],
        85 => ['snow', 'Slight snow showers'],
        86 => ['snow', 'Heavy snow showers'],
        95 => ['thunderstorm', 'Thunderstorm'],
        96 => ['thunderstorm', 'Thunderstorm with slight hail'],
        97 => ['thunderstorm', 'Heavy thunderstorm'],
        99 => ['thunderstorm', 'Thunderstorm with heavy hail'],
    ];

    private $apiUrl;
    private $latitude;
    private $longitude;
    private $locationName;
    private $timeoutSeconds;

    // The settings come from config.php; a test passes its own, e.g. the address of a fake API.
    public function __construct($apiUrl = null, $latitude = null, $longitude = null, $locationName = null, $timeoutSeconds = null)
    {
        $this->apiUrl = $apiUrl ?? Config::WEATHER_API_URL();
        $this->latitude = $latitude ?? Config::WEATHER_LATITUDE();
        $this->longitude = $longitude ?? Config::WEATHER_LONGITUDE();
        $this->locationName = $locationName ?? Config::WEATHER_LOCATION_NAME();
        $this->timeoutSeconds = (int) ($timeoutSeconds ?? Config::WEATHER_TIMEOUT_SECONDS());
    }

    public function getForecast()
    {
        $data = $this->fetch();
        $this->checkShape($data);

        $current = $data['current'];
        $hours = $this->hoursAfter($current['time'], $data['hourly']);

        $shown = [];
        for ($index = 0; $index < count($hours) && count($shown) < self::SHOWN_FORECASTS; $index += self::HOURS_BETWEEN_SHOWN_FORECASTS) {
            $shown[] = ['time' => $hours[$index]['time'], 'temperature_c' => $hours[$index]['temperature_c']] + $this->codeFields($hours[$index]['weather_code']);
        }

        $codesAhead = array_merge([$current['weather_code']], array_column($hours, 'weather_code'));

        return [
            'location' => $this->locationName,
            'current' => [
                'time' => $current['time'],
                'temperature_c' => $current['temperature_2m'],
                'feels_like_c' => $current['apparent_temperature'],
                'humidity_percent' => $current['relative_humidity_2m'],
                'wind_speed_ms' => $current['wind_speed_10m'],
            ] + $this->codeFields($current['weather_code']),
            'next_hours' => $shown,
            // Any hour counts, not only the four shown: a storm at 19:00 sits between 18:00 and 21:00.
            'thunderstorm_expected' => in_array('thunderstorm', array_map(fn ($code) => self::describe($code)['condition'], $codesAhead), true),
            'attribution' => self::ATTRIBUTION,
        ];
    }

    public static function describe($code)
    {
        [$condition, $description] = self::WEATHER_CODES[(int) $code] ?? ['unknown', 'Unknown weather'];
        return ['condition' => $condition, 'description' => $description];
    }

    private function fetch()
    {
        $url = $this->apiUrl . '?' . http_build_query([
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'current' => implode(',', self::CURRENT_VARIABLES),
            'hourly' => 'temperature_2m,weather_code',
            'forecast_hours' => self::FORECAST_HOURS,
            'wind_speed_unit' => 'ms',
            // Times in the factory's own time zone, so "15:00" on the card is 15:00 on the factory clock.
            'timezone' => 'auto',
        ]);

        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            // Without these, a server that accepts the connection and never answers would hold this request (and
            // a PHP worker) for as long as PHP allows.
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
        ]);
        $started = microtime(true);
        $body = curl_exec($curl);
        $milliseconds = round((microtime(true) - $started) * 1000);
        if ($body === false) {
            $this->fail("request failed after {$milliseconds}ms: " . curl_error($curl));
        }

        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        // Written for every call that was answered, whatever the answer says, so a slow or refused API shows up in
        // info.log. Not $url: its query holds the factory's coordinates, which the log has no need for.
        Logger::info("EXTERNAL Open-Meteo GET {$this->apiUrl} $status {$milliseconds}ms");
        $data = json_decode($body, true);
        if ($status !== 200) {
            // Open-Meteo explains its errors as {"error": true, "reason": "..."}; the reason goes to the log.
            $this->fail("HTTP $status: " . ($data['reason'] ?? substr($body, 0, 200)));
        }
        if (!is_array($data)) {
            $this->fail('the answer is not JSON: ' . substr($body, 0, 200));
        }
        return $data;
    }

    // A changed or broken answer must become a 503 here, not an "undefined array key" deep inside getForecast().
    private function checkShape($data)
    {
        foreach (array_merge(['time'], self::CURRENT_VARIABLES) as $variable) {
            if (!isset($data['current'][$variable])) {
                $this->fail("the answer is missing current.$variable");
            }
        }
        foreach (['time', 'temperature_2m', 'weather_code'] as $variable) {
            if (!isset($data['hourly'][$variable]) || !is_array($data['hourly'][$variable])) {
                $this->fail("the answer is missing hourly.$variable");
            }
        }
    }

    // The hourly lists start at the current hour; the forecasts are the hours after it.
    private function hoursAfter($now, $hourly)
    {
        $hours = [];
        foreach ($hourly['time'] as $index => $time) {
            if (strcmp($time, $now) > 0 && isset($hourly['temperature_2m'][$index], $hourly['weather_code'][$index])) {
                $hours[] = ['time' => $time, 'temperature_c' => $hourly['temperature_2m'][$index], 'weather_code' => $hourly['weather_code'][$index]];
            }
        }
        return $hours;
    }

    private function codeFields($code)
    {
        return ['weather_code' => $code] + self::describe($code);
    }

    // The user reads a general message; the developer reads the real reason in error.log.
    private function fail($reason)
    {
        Logger::error("Weather API (Open-Meteo) unavailable: $reason");
        throw new ExternalServiceException(self::UNAVAILABLE);
    }
}
