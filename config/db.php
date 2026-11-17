<?php
declare(strict_types=1);

error_reporting(E_ALL);

$localConfigPath = __DIR__ . '/db.local.php';
if (is_file($localConfigPath)) {
    $dbConfig = require $localConfigPath;
} else {
    $dbConfig = [
        'host' => getenv('UNIVERSITY_SPORTS_DB_HOST'),
        'name' => getenv('UNIVERSITY_SPORTS_DB_NAME'),
        'user' => getenv('UNIVERSITY_SPORTS_DB_USER'),
        'password' => getenv('UNIVERSITY_SPORTS_DB_PASSWORD'),
    ];
}

if (!is_array($dbConfig)
    || !is_string($dbConfig['host'] ?? null)
    || $dbConfig['host'] === ''
    || !is_string($dbConfig['name'] ?? null)
    || !preg_match('/\A[a-zA-Z0-9_]+\z/', $dbConfig['name'])
    || !is_string($dbConfig['user'] ?? null)
    || $dbConfig['user'] === ''
    || !is_string($dbConfig['password'] ?? null)) {
    error_log('Database configuration is missing or invalid.');
    http_response_code(500);
    exit('Database configuration is not available.');
}

$dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    http_response_code(500);
    exit('Database connection failed. Check the local database configuration.');
}
