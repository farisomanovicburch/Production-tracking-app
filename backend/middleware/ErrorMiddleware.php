<?php
require_once __DIR__ . '/../utils/exceptions.php';
require_once __DIR__ . '/../utils/Logger.php';

/*
 * The one place that turns an exception into an answer, so no route needs a try/catch. Registered in index.php with
 * Flight::map('error', ...) and Flight::map('notFound', ...).
 *
 * What goes to error.log: every 401, every 403, every 500 and every URL that matches no route. A 400 or a 404 for
 * a missing record is the user's mistake, not the server's problem, so it would only bury the lines that matter.
 */
class ErrorMiddleware
{
    // The statuses worth a line in error.log: someone who is not logged in or not allowed, and the server's own faults.
    private const LOGGED_STATUSES = [401, 403, 500];

    /*
     *  - HttpException (thrown by a service or a middleware): its own status and message, written for the user.
     *  - anything else (a PDOException, a bug): 500 with a general message. The details go to error.log only,
     *    because SQL, file paths and stack traces tell an attacker how the backend is built.
     */
    public function handle(Throwable $exception)
    {
        if ($exception instanceof HttpException) {
            $status = $exception->getStatus();
            $message = $exception->getMessage();
            $logged = $message;
        } else {
            $status = 500;
            $message = 'Something went wrong on the server. Please try again later.';
            $logged = sprintf(
                '%s: %s in %s:%d',
                get_class($exception),
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            );
        }

        if (in_array($status, self::LOGGED_STATUSES, true)) {
            Logger::error($this->requestSummary($status) . " $logged");
        }
        $this->answer($status, $message);
    }

    // A URL that matches no route answers in the same JSON shape as every other error, naming what was asked for,
    // so a typo in the frontend shows up as a readable message instead of an HTML page. Many 404s in a row also
    // mean someone is probing the API, which is why they are logged.
    public function notFound()
    {
        $request = Flight::request();
        $message = "Endpoint {$request->method} {$this->path()} does not exist.";

        Logger::error($this->requestSummary(404) . " $message");
        $this->answer(404, $message);
    }

    // "GET /users 500 user=2 role=operator". The user is set by AuthMiddleware; before it ran (or on a public
    // route) nobody is known, which the log shows as "-".
    private function requestSummary($status)
    {
        $user = Flight::get('user');
        return sprintf(
            '%s %s %d user=%s role=%s',
            Flight::request()->method,
            $this->path(),
            $status,
            $user['id'] ?? '-',
            $user['role'] ?? '-'
        );
    }

    // Without the query string: it can carry values that do not belong in a log.
    private function path()
    {
        return strtok(Flight::request()->url, '?');
    }

    private function answer($status, $message)
    {
        Flight::response()
            ->clearBody()
            ->status($status)
            ->header('Content-Type', 'application/json')
            ->write(json_encode(['status' => $status, 'error' => $message]))
            ->send();
    }
}
