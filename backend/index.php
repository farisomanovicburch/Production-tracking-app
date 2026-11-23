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

require_once __DIR__ . '/middleware/ErrorMiddleware.php';
require_once __DIR__ . '/rest/services/UserService.php';
require_once __DIR__ . '/rest/services/MachineService.php';
require_once __DIR__ . '/rest/services/MachineParameterService.php';
require_once __DIR__ . '/rest/services/MachineProductService.php';
require_once __DIR__ . '/rest/services/ProductService.php';
require_once __DIR__ . '/rest/services/MaterialService.php';
require_once __DIR__ . '/rest/services/RecipeService.php';
require_once __DIR__ . '/rest/services/RecipeItemService.php';
require_once __DIR__ . '/rest/services/RecipeProductService.php';
require_once __DIR__ . '/rest/services/ProductionRunService.php';
require_once __DIR__ . '/rest/services/RunParameterValueService.php';
require_once __DIR__ . '/rest/services/MaterialUsageService.php';
require_once __DIR__ . '/rest/services/ConversionRunService.php';
require_once __DIR__ . '/rest/services/ConversionRunParameterValueService.php';
require_once __DIR__ . '/rest/services/MaintenanceRecordService.php';
require_once __DIR__ . '/rest/services/WeatherService.php';

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
Flight::register('productionRunService', 'ProductionRunService');
Flight::register('runParameterValueService', 'RunParameterValueService');
Flight::register('materialUsageService', 'MaterialUsageService');
Flight::register('conversionRunService', 'ConversionRunService');
Flight::register('conversionRunParameterValueService', 'ConversionRunParameterValueService');
Flight::register('maintenanceRecordService', 'MaintenanceRecordService');
Flight::register('weatherService', 'WeatherService');

// Every exception and every unknown URL ends in ErrorMiddleware: one JSON format, and error.log for what matters.
$errorMiddleware = new ErrorMiddleware();
Flight::map('error', [$errorMiddleware, 'handle']);
Flight::map('notFound', [$errorMiddleware, 'notFound']);

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
require_once __DIR__ . '/rest/routes/ProductionRunRoutes.php';
require_once __DIR__ . '/rest/routes/RunParameterValueRoutes.php';
require_once __DIR__ . '/rest/routes/MaterialUsageRoutes.php';
require_once __DIR__ . '/rest/routes/ConversionRunRoutes.php';
require_once __DIR__ . '/rest/routes/ConversionRunParameterValueRoutes.php';
require_once __DIR__ . '/rest/routes/MaintenanceRecordRoutes.php';
require_once __DIR__ . '/rest/routes/WeatherRoutes.php';

Flight::start();
