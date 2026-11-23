<?php
require_once __DIR__ . '/../utils/Logger.php';

/*
 * One line in info.log for every request, public or not:
 *
 *   [2026-12-01 14:05:12] INFO GET /production-runs 200 user=2 role=operator 35ms
 *
 * Registered in index.php with Flight::before('start', ...), so it sees every request, including the ones that
 * end in an error or match no route.
 */
class RequestLogMiddleware
{
    public function before()
    {
        // The status, the user and the time it took are only known when the answer is ready, which is after this
        // method has returned. A shutdown function runs at the very end of the request, whatever way it ended.
        $method = Flight::request()->method;
        $path = strtok(Flight::request()->url, '?');

        register_shutdown_function(function () use ($method, $path) {
            $user = Flight::get('user');
            Logger::info(sprintf(
                '%s %s %d user=%s role=%s %dms',
                $method,
                $path,
                http_response_code(),
                $user['id'] ?? '-',
                $user['role'] ?? '-',
                round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000)
            ));
        });
    }
}
