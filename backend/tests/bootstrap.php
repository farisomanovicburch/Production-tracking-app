<?php
// Runs once before the whole test suite: rebuilds the database from the .sql file,
// so every run starts from the same tables and sample data, whatever the previous run left behind.

$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) {
    fwrite(STDERR, "backend/config/config.php is missing: copy config.example.php to config.php and set your MySQL password.\n");
    exit(1);
}
require_once $configFile;

// No dbname in the DSN: the database may not exist yet, the script creates it.
$server = new PDO(
    'mysql:host=' . Config::DB_HOST() . ';port=' . Config::DB_PORT() . ';charset=utf8mb4',
    Config::DB_USER(),
    Config::DB_PASSWORD(),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$statements = $server->query(file_get_contents(__DIR__ . '/../../database/production_tracker.sql'));
// The whole script goes to MySQL as one query, but PHP only hears about a failing statement when it
// steps to that statement's result. Without this loop, a broken CREATE TABLE in the middle passes silently.
do {
} while ($statements->nextRowset());
