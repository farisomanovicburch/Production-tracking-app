<?php
/*
 * A one-file app that has nothing but ErrorMiddleware, RequestLogMiddleware and a few routes that answer or fail on
 * purpose, run with php -S by ErrorLogTest and RequestLogTest. The real app has no route that answers 401 or 403
 * yet, and none that has a logged-in user, so this is the only way to see the middleware handle them.
 *
 *   /unauthorized  /forbidden  /forbidden-as-operator  /invalid  /missing  /crash
 */
require __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../middleware/ErrorMiddleware.php';
require_once __DIR__ . '/../../middleware/RequestLogMiddleware.php';

// php -S would otherwise take the requested path as the script name, and FlightPHP would cut it off the URL.
$_SERVER['SCRIPT_NAME'] = '/index.php';

$requestLogMiddleware = new RequestLogMiddleware();
Flight::before('start', function () use ($requestLogMiddleware) {
    $requestLogMiddleware->before();
});
$errorMiddleware = new ErrorMiddleware();
Flight::map('error', [$errorMiddleware, 'handle']);
Flight::map('notFound', [$errorMiddleware, 'notFound']);

Flight::route('GET /unauthorized', function () {
    throw new UnauthorizedException('Login required.');
});
Flight::route('GET /forbidden', function () {
    throw new ForbiddenException('Only an admin may do this.');
});
// What AuthMiddleware will have stored by the time a route runs.
Flight::route('GET /forbidden-as-operator', function () {
    Flight::set('user', ['id' => 2, 'role' => 'operator']);
    throw new ForbiddenException('Only an admin may do this.');
});
Flight::route('GET /invalid', function () {
    throw new ValidationException('Quantity must be above 0.');
});
Flight::route('GET /missing', function () {
    throw new NotFoundException('Run not found.');
});
Flight::route('GET /crash', function () {
    throw new RuntimeException('boom');
});

Flight::start();
