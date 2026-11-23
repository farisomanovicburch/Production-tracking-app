<?php
/*
 * Generates the OpenAPI document (JSON) from the PHP 8 attributes in doc_setup.php (API info, shared schemas)
 * and paths/*.php (one class per routes file). The Swagger UI page (index.html) reads it.
 */
// A single notice printed before the JSON would make the whole document unreadable for Swagger UI.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('display_errors', '0');

require __DIR__ . '/../../../vendor/autoload.php';

// Try it out sends its requests to this URL. Taking it from the current request makes it right for php -S,
// Apache and the deployed server alike; a hard-coded localhost URL is the usual cause of "Failed to fetch".
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$path = preg_replace('#public/v1/docs/swagger\.php$#', '', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
define('API_BASE_URL', $scheme . '://' . $_SERVER['HTTP_HOST'] . $path);

// swagger-php reads the attributes through reflection, which only sees classes that are already loaded.
$files = array_merge([__DIR__ . '/doc_setup.php'], glob(__DIR__ . '/paths/*.php'));
foreach ($files as $file) {
    require_once $file;
}

$openapi = (new \OpenApi\Generator())->generate($files);

header('Content-Type: application/json');
echo $openapi->toJson();
