<?php
session_start();

// Protect the page
if (!isset($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit;
}

// Load the dashboard view
require_once __DIR__ . '/../views/pages/dashboard.php';