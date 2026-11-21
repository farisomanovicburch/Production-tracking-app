<?php
/*
 * Template for config.php: copy this file to config.php (same folder) and put your real values there.
 * config.php is in .gitignore because it holds secrets (the database password now, the JWT secret later)
 * and this repository is public. This file is committed so everyone knows which settings exist.
 */
class Config
{
    public static function DB_HOST()     { return self::getEnv('DB_HOST', '127.0.0.1'); }
    public static function DB_PORT()     { return self::getEnv('DB_PORT', 3306); }
    public static function DB_NAME()     { return self::getEnv('DB_NAME', 'production_tracker'); }
    public static function DB_USER()     { return self::getEnv('DB_USER', 'root'); }
    public static function DB_PASSWORD() { return self::getEnv('DB_PASSWORD', ''); }

    // External weather API (Open-Meteo, free, no key). The location is the factory's: the dashboard shows its weather.
    public static function WEATHER_API_URL()         { return self::getEnv('WEATHER_API_URL', 'https://api.open-meteo.com/v1/forecast'); }
    public static function WEATHER_LATITUDE()        { return self::getEnv('WEATHER_LATITUDE', 43.8563); }
    public static function WEATHER_LONGITUDE()       { return self::getEnv('WEATHER_LONGITUDE', 18.4131); }
    public static function WEATHER_LOCATION_NAME()   { return self::getEnv('WEATHER_LOCATION_NAME', 'Sarajevo'); }
    public static function WEATHER_TIMEOUT_SECONDS() { return self::getEnv('WEATHER_TIMEOUT_SECONDS', 5); }

    // A hosting server (Milestone 5) sets environment variables, which win over the values in this file.
    private static function getEnv($name, $default)
    {
        $value = getenv($name);
        return ($value !== false && trim($value) !== '') ? $value : $default;
    }
}
