<?php
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../app/helpers/Env.php';

Env::load(__DIR__ . '/../.env');

$host     = Env::get('DB_HOST', '127.0.0.1');
$dbname   = Env::get('DB_NAME', 'faculty_db');
$username = Env::get('DB_USER', 'root');
$password = Env::get('DB_PASS', '');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Show detailed error only in local dev
    if (Env::get('APP_DEBUG', 'false') === 'true') {
        die('Database connection failed: ' . $e->getMessage());
    }
    die('Database connection failed. Please contact the administrator.');
}