<?php
/*
 * Writes one line per event: info.log for what happened, error.log for what went wrong.
 *
 *   [2026-11-12 10:15:03] INFO LOGIN user=2 role=operator
 *
 * A log line says who did what (user id, URL, status) and nothing more. Passwords and tokens are never logged:
 * callers must not pass them in, and redact() is the safety net for the day one slips through.
 */
class Logger
{
    private static ?string $directory = null;

    public static function info($message)
    {
        self::write('info.log', 'INFO', $message);
    }

    public static function error($message)
    {
        self::write('error.log', 'ERROR', $message);
    }

    /*
     * Where the logs go: the folder set with setDirectory(), else the LOG_DIRECTORY environment variable (how a test
     * points a server in another process at its own folder), else backend/logs, which .gitignore keeps out of the
     * repository.
     */
    public static function directory()
    {
        return self::$directory ?? (getenv('LOG_DIRECTORY') ?: dirname(__DIR__) . DIRECTORY_SEPARATOR . 'logs');
    }

    /** Tests point the logger at a folder of their own; null goes back to backend/logs. */
    public static function setDirectory($directory)
    {
        self::$directory = $directory;
    }

    private static function write($file, $level, $message)
    {
        $line = '[' . date('Y-m-d H:i:s') . "] $level " . self::oneLine(self::redact((string) $message)) . PHP_EOL;
        $folder = self::directory();

        // The folder is not in the repository (nothing in it is tracked), so a fresh clone has none.
        // A failed write is only reported to PHP's own log: a full disk must not turn a good request into a 500.
        if ((!is_dir($folder) && !@mkdir($folder, 0775, true) && !is_dir($folder))
            || @file_put_contents($folder . DIRECTORY_SEPARATOR . $file, $line, FILE_APPEND | LOCK_EX) === false) {
            error_log("Logger could not write to $folder/$file: " . trim($line));
        }
    }

    /*
     * A message can hold text the user typed (the email of a failed login). A line break in it would start a second
     * line that looks like a genuine entry, so every line break becomes a space.
     */
    private static function oneLine($message)
    {
        return preg_replace('/\R/', ' ', $message);
    }

    /*
     * The order matters: "Authorization: Bearer x" must be caught as a key and its value first, or the bearer
     * rule would leave the word "Authorization:" pointing at nothing. Limits: a password that contains spaces is
     * hidden only up to its first space.
     */
    private static function redact($message)
    {
        $message = preg_replace(
            '/([\w-]*(?:password|passwd|token|secret|authorization|jwt)[\w-]*["\']?\s*[:=]\s*["\']?)(?:Bearer\s+)?[^\s"\',&}]+/i',
            '$1[hidden]',
            $message
        );
        $message = preg_replace('/Bearer\s+[\w.~+\/=-]+/i', 'Bearer [hidden]', $message);
        return preg_replace('/eyJ[\w-]+\.[\w-]+\.[\w-]+/', '[hidden]', $message);
    }
}
