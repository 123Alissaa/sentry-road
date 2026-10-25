<?php
// db.php: opens the connection to MySQL. Every other script uses this file.

$host     = '127.0.0.1';
$database = 'sentry_road';
$user     = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: '';

$pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $user, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);