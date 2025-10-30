<?php
// /C:/Users/melvi/OneDrive/Desktop/Schduler Project (zero to hero)/database.php
// Simple PDO MySQL connection. Adjust environment variables or the defaults below.

$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_PORT = getenv('DB_PORT') ?: '3306';
$DB_NAME = getenv('DB_NAME') ?: 'main';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '';

function getPDO(): PDO
{
    static $pdo = null;
    global $DB_HOST, $DB_PORT, $DB_NAME, $DB_USER, $DB_PASS;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4";

    try {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            // PDO::ATTR_PERSISTENT => true, // enable if you want persistent connections
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];

        $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        // In production, log the error instead of exposing it.
        throw new RuntimeException('Database connection failed: ' . $e->getMessage());
    }
}

/*
Usage:
$pdo = getPDO();
$stmt = $pdo->query('SELECT NOW() AS now');
$result = $stmt->fetch();
print_r($result);
*/