<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    header('Location: /dashboard');
    exit;
}

require_once __DIR__ . '/../views/pages/login.php';