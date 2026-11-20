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

require_once __DIR__ . '/rest/services/UserService.php';
require_once __DIR__ . '/rest/services/MachineService.php';
require_once __DIR__ . '/rest/services/MachineParameterService.php';
require_once __DIR__ . '/rest/services/MachineProductService.php';
require_once __DIR__ . '/rest/services/ProductService.php';
require_once __DIR__ . '/rest/services/MaterialService.php';
require_once __DIR__ . '/rest/services/RecipeService.php';
require_once __DIR__ . '/rest/services/RecipeItemService.php';
require_once __DIR__ . '/rest/services/RecipeProductService.php';

// Each service is created once, on first use; a route gets it with Flight::userService() instead of `new`.
Flight::register('userService', 'UserService');
Flight::register('machineService', 'MachineService');
Flight::register('machineParameterService', 'MachineParameterService');
Flight::register('machineProductService', 'MachineProductService');
Flight::register('productService', 'ProductService');
Flight::register('materialService', 'MaterialService');
Flight::register('recipeService', 'RecipeService');
Flight::register('recipeItemService', 'RecipeItemService');
Flight::register('recipeProductService', 'RecipeProductService');

/*
 * The one place that turns an exception into an answer, so no route needs a try/catch.
 *  - HttpException (thrown by a service): its own status and message, written for the user.
 *  - anything else (a PDOException, a bug): 500 with a general message. The details go to the server log only,
 *    because SQL, file paths and stack traces tell an attacker how the backend is built.
 */
Flight::map('error', function (Throwable $exception) {
    if ($exception instanceof HttpException) {
        $status = $exception->getStatus();
        $message = $exception->getMessage();
    } else {
        $status = 500;
        $message = 'Something went wrong on the server. Please try again later.';
        error_log((string) $exception);
    }
    Flight::response()
        ->clearBody()
        ->status($status)
        ->header('Content-Type', 'application/json')
        ->write(json_encode(['status' => $status, 'error' => $message]))
        ->send();
});

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

require_once __DIR__ . '/rest/routes/UserRoutes.php';
require_once __DIR__ . '/rest/routes/MachineRoutes.php';
require_once __DIR__ . '/rest/routes/MachineParameterRoutes.php';
require_once __DIR__ . '/rest/routes/MachineProductRoutes.php';
require_once __DIR__ . '/rest/routes/ProductRoutes.php';
require_once __DIR__ . '/rest/routes/MaterialRoutes.php';
require_once __DIR__ . '/rest/routes/RecipeRoutes.php';
require_once __DIR__ . '/rest/routes/RecipeItemRoutes.php';
require_once __DIR__ . '/rest/routes/RecipeProductRoutes.php';

Flight::start();
