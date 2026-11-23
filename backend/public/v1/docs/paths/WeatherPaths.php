<?php
use OpenApi\Attributes as OA;

// Documentation of rest/routes/WeatherRoutes.php. WeatherRoutesTest checks both statuses.
class WeatherPaths
{
    #[OA\Get(
        path: '/weather',
        tags: ['weather'],
        summary: "Current weather and the next hours at the factory's location",
        description: 'Fetched by the backend from the external Open-Meteo API (https://open-meteo.com/, no API key) with a ' .
            '5 second timeout. The location is set in config.php. Data: CC BY 4.0, Open-Meteo.com.',
        responses: [
            new OA\Response(response: 200, description: 'The weather for the dashboard card', content: new OA\JsonContent(ref: '#/components/schemas/Weather')),
            new OA\Response(
                response: 503,
                description: 'Open-Meteo is unreachable, too slow, or gave an answer that cannot be used (the reason is in the server log)',
                content: new OA\JsonContent(ref: '#/components/schemas/Error')
            )
        ]
    )]
    public function getWeather() {}
}
