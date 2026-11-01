<?php
require_once __DIR__ . '/../../config/config.php';

class BaseDao
{
    // One connection shared by every DAO, created on first use: opening a new one per query would be slow,
    // and a transaction only covers queries that run on the same connection.
    private static $connection = null;

    public static function connection()
    {
        if (self::$connection === null) {
            self::$connection = new PDO(
                'mysql:host=' . Config::DB_HOST() . ';port=' . Config::DB_PORT()
                    . ';dbname=' . Config::DB_NAME() . ';charset=utf8mb4',
                Config::DB_USER(),
                Config::DB_PASSWORD(),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        }
        return self::$connection;
    }
}
