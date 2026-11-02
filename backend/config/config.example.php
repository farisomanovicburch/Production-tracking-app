<?php
/*
 * Template for config.php: copy this file to config.php (same folder) and put your real values there.
 * config.php is in .gitignore because it holds secrets (the database password now, the JWT secret and
 * API keys later) and this repository is public. This file is committed so everyone knows which settings exist.
 */
class Config
{
    public static function DB_HOST()     { return self::getEnv('DB_HOST', '127.0.0.1'); }
    public static function DB_PORT()     { return self::getEnv('DB_PORT', 3306); }
    public static function DB_NAME()     { return self::getEnv('DB_NAME', 'production_tracker'); }
    public static function DB_USER()     { return self::getEnv('DB_USER', 'root'); }
    public static function DB_PASSWORD() { return self::getEnv('DB_PASSWORD', ''); }

    // A hosting server (Milestone 5) sets environment variables, which win over the values in this file.
    private static function getEnv($name, $default)
    {
        $value = getenv($name);
        return ($value !== false && trim($value) !== '') ? $value : $default;
    }
}
