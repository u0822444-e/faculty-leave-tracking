<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../helpers/ActivityLogger.php';

// ============================================
// GET /login  → redirect signed-in users to their home
// POST /login → authenticate
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_SESSION['user_id'])) {
        $role = $_SESSION['role'] ?? 'admin';

        switch ($role) {
            case 'admin':
                header('Location: /dashboard');
                exit;
            case 'hr':
                header('Location: /hr/dashboard');
                exit;
            case 'faculty':
                header('Location: /faculty/dashboard');
                exit;
            case 'staff':
                header('Location: /staff/dashboard');
                exit;
            default:
                // Unknown role — clear session and show login
                session_unset();
                session_destroy();
                session_start();
                header('Location: /login');
                exit;
        }
    }

    // Not signed in — show login page
    require __DIR__ . '/../../index.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /login');
    exit;
}

// ============================================
// POST — authenticate
// ============================================
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    $_SESSION['error'] = 'Please fill in all fields.';
    header('Location: /login');
    exit;
}

$stmt = $pdo->prepare('
    SELECT 
        u.id, 
        u.username, 
        u.password, 
        u.role,
        u.status,
        e.first_name,
        e.last_name
    FROM users u
    LEFT JOIN employees e ON u.employee_id = e.id
    WHERE u.username = ?
    LIMIT 1
');
$stmt->execute([$username]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password'])) {

    // Block archived accounts
    if (($user['status'] ?? 'active') !== 'active') {
        $_SESSION['error'] = 'Your account has been deactivated. Please contact the administrator.';
        header('Location: /login');
        exit;
    }

    $_SESSION['user_id']    = $user['id'];
    $_SESSION['username']   = $user['username'];
    $_SESSION['role']       = $user['role'];
    $_SESSION['first_name'] = $user['first_name'] ?? $user['username'];
    $_SESSION['last_name']  = $user['last_name'] ?? '';

    // Log BEFORE redirect
    ActivityLogger::log($pdo, 'login', 'Signed in to the system');

    // Role-based redirect
    switch ($user['role']) {
        case 'admin':
            header('Location: /dashboard');
            exit;

        case 'hr':
            header('Location: /hr/dashboard');
            exit;

        case 'faculty':
            header('Location: /faculty/dashboard');
            exit;

        case 'staff':
            header('Location: /staff/dashboard');
            exit;

        default:
            session_unset();
            session_destroy();
            session_start();
            $_SESSION['error'] = 'Unknown role. Please contact the administrator.';
            header('Location: /login');
            exit;
    }
}

$_SESSION['error'] = 'Invalid username or password.';
header('Location: /login');
exit;