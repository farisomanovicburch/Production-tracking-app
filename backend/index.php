<?php
/*
 * Entry point of the REST API: every request ends up here (backend/.htaccess with Apache, server.php with php -S).
 *
 *   HTTP request -> rest/routes -> rest/services -> rest/dao -> MySQL
 */
require __DIR__ . '/vendor/autoload.php';

if (!file_exists(__DIR__ . '/config/config.php')) {
    http_response_code(500);
    exit('Missing backend/config/config.php: copy config.example.php to config.php and set your database password.');
}

// A URL that matches no route answers in the same JSON shape as every other error, naming what was asked for,
// so a typo in the frontend shows up as a readable message instead of an HTML page.
Flight::map('notFound', function () {
    $request = Flight::request();
    $path = strtok($request->url, '?');
    Flight::response()
        ->clearBody()
        ->status(404)
        ->header('Content-Type', 'application/json')
        ->write(json_encode(['status' => 404, 'error' => "Endpoint {$request->method} $path does not exist."]))
        ->send();
});

Flight::route('GET /', function () {
    Flight::json(['app' => 'Production Tracker API', 'message' => 'The backend is running.', 'docs' => 'public/v1/docs/']);
});

Flight::start();
