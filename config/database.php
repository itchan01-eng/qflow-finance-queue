<?php
date_default_timezone_set('Asia/Manila');

$env = parse_ini_file(dirname(__DIR__) . '/.env');

if ($env === false) {
    die('Unable to read the .env file.');
}

// Connect to MariaDB
$host = $env['DB_HOST'];
$port = $env['DB_PORT'];
$name = $env['DB_NAME'];
$user = $env['DB_USER'];
$password = $env['DB_PASS'];

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4",
        $user,
        $password
    );

    // Show database errors while developing
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Return database results as associative arrays
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
    die('Database connection failed. Check your database settings.');
}

$pdo->exec("SET time_zone = '+08:00'");
return $pdo;
