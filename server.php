<?php
/*
 * Router for PHP's built-in web server, so the app runs without Apache. Run from this folder:
 *
 *   php -S localhost:8000 server.php
 *
 *   http://localhost:8000/frontend/                 the app
 *   http://localhost:8000/backend/...               the REST API (FlightPHP)
 *   http://localhost:8000/backend/public/v1/docs/   Swagger UI
 *
 * With Apache, backend/.htaccess does the same job and this file is not used.
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path === '/' || $path === '') {
    header('Location: /frontend/');
    return true;
}

if (str_starts_with($path, '/backend')) {
    $file = __DIR__ . $path;

    // A folder with an index.html (the Swagger UI page): the built-in server would not pick index.html itself
    // once a router script is in use.
    if (is_dir($file) && is_file(rtrim($file, '/') . '/index.html')) {
        if (!str_ends_with($path, '/')) {
            header("Location: $path/");
            return true;
        }
        header('Content-Type: text/html; charset=utf-8');
        readfile(rtrim($file, '/') . '/index.html');
        return true;
    }
    // Other existing files: static ones are served as they are, PHP files (swagger.php) are run directly.
    if (is_file($file) && pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
        return false;
    }
    if (is_file($file) && basename($file) !== 'index.php') {
        chdir(dirname($file));
        require $file;
        return true;
    }
    // Everything else is an API URL. FlightPHP strips the folder of SCRIPT_NAME from the URL, so the routes
    // are written as /machines, not /backend/machines.
    $_SERVER['SCRIPT_NAME'] = '/backend/index.php';
    chdir(__DIR__ . '/backend');
    require __DIR__ . '/backend/index.php';
    return true;
}

return false;
