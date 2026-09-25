<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/helpers/ActivityLogger.php';
ActivityLogger::log($pdo, 'logout', 'Signed out of the system');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
session_unset();
session_destroy();
header('Location: /login');
exit;