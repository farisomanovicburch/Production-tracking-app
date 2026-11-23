<?php
// The factory's weather from Open-Meteo, for the dashboard card. Answers 503 when Open-Meteo cannot be reached or
// answers nonsense; the other endpoints do not depend on it.
Flight::route('GET /weather', function () {
    Flight::json(Flight::weatherService()->getForecast());
});
