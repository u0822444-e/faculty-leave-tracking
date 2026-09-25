<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$method = $_SERVER['REQUEST_METHOD'];

// Serve static files directly
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

// POST /login → AuthController
if (($uri === '/login' || $uri === '/login.php') && $method === 'POST') {
    require __DIR__ . '/../app/controllers/AuthController.php';
    exit;
}

if (($uri === '/login' || $uri === '/login.php' || $uri === '/') && $method === 'GET') {
    if (isset($_SESSION['user_id'])) {
        $role = $_SESSION['role'] ?? 'admin';
        if ($role === 'faculty') {
            header('Location: /faculty/dashboard');
        } elseif ($role === 'hr') {
            header('Location: /hr/dashboard');
        } else {
            header('Location: /dashboard');
        }
        exit;
    }
    require __DIR__ . '/index.php';
    exit;
}

// Dashboard (admin)
if ($uri === '/dashboard' || $uri === '/dashboard.php') {
    require __DIR__ . '/../views/pages/dashboard.php';
    exit;
}

// Users page
if ($uri === '/users') {
    require __DIR__ . '/../views/pages/users.php';
    exit;
}

// Users API
if ($uri === '/api/users') {
    require __DIR__ . '/../app/controllers/UserController.php';
    exit;
}

// Logout
if ($uri === '/logout' || $uri === '/logout.php') {
    require __DIR__ . '/logout.php';
    exit;
}

// Forgot password
if ($uri === '/forgot-password' && $method === 'GET') {
    require __DIR__ . '/../views/pages/forgot_password.php';
    exit;
}
if ($uri === '/forgot-password' && $method === 'POST') {
    require __DIR__ . '/../app/controllers/PasswordController.php';
    exit;
}

// Reset password
if ($uri === '/reset-password' && $method === 'GET') {
    require __DIR__ . '/../views/pages/reset_password.php';
    exit;
}
if ($uri === '/reset-password' && $method === 'POST') {
    require __DIR__ . '/../app/controllers/PasswordController.php';
    exit;
}

// Activity logs
if ($uri === '/activity-logs') {
    require __DIR__ . '/../views/pages/activity_logs.php';
    exit;
}
if ($uri === '/api/activity-logs') {
    require __DIR__ . '/../app/controllers/ActivityLogController.php';
    exit;
}

// Notifications API
if ($uri === '/api/notifications') {
    require __DIR__ . '/../app/controllers/NotificationController.php';
    exit;
}

// Profile
if ($uri === '/profile') {
    require __DIR__ . '/../views/pages/profile.php';
    exit;
}
if ($uri === '/api/profile') {
    require __DIR__ . '/../app/controllers/ProfileController.php';
    exit;
}

// Settings
if ($uri === '/settings') {
    require __DIR__ . '/../views/pages/settings.php';
    exit;
}
if ($uri === '/api/settings') {
    require __DIR__ . '/../app/controllers/SettingsController.php';
    exit;
}

// User detail — /users/{id}
if (preg_match('#^/users/(\d+)$#', $uri, $matches)) {
    $_GET['id'] = (int) $matches[1];
    require __DIR__ . '/../views/pages/user_detail.php';
    exit;
}

// Leaves page (admin or HR)
if ($uri === '/leaves') {
    if (($_SESSION['role'] ?? '') === 'hr') {
        require __DIR__ . '/../views/pages/hr_leaves.php';
    } else {
        require __DIR__ . '/../views/pages/leaves.php';
    }
    exit;
}

// Payroll (admin)
if ($uri === '/payroll') {
    require __DIR__ . '/../views/pages/payroll.php';
    exit;
}

// ============================================
// FACULTY ROUTES
// ============================================
if ($uri === '/faculty/dashboard') {
    require __DIR__ . '/../views/pages/faculty_dashboard.php';
    exit;
}

if ($uri === '/faculty/leaves') {
    require __DIR__ . '/../views/pages/faculty_leaves.php';
    exit;
}

if ($uri === '/faculty/profile') {
    require __DIR__ . '/../views/pages/faculty_profile.php';
    exit;
}

// Leave API (shared by admin + faculty)
if ($uri === '/api/leaves') {
    require __DIR__ . '/../app/controllers/LeaveController.php';
    exit;
}

// ============================================
// HR ROUTES
// ============================================
if ($uri === '/hr/dashboard') {
    require __DIR__ . '/../views/pages/hr_dashboard.php';
    exit;
}
if ($uri === '/hr/leaves') {
    require __DIR__ . '/../views/pages/hr_leaves.php';
    exit;
}

// ============================================
// FACULTY ROUTES
// ============================================
if ($uri === '/faculty/dashboard') {
    require __DIR__ . '/../views/pages/faculty_dashboard.php';
    exit;
}

if ($uri === '/faculty/leaves') {
    require __DIR__ . '/../views/pages/faculty_leaves.php';
    exit;
}

if ($uri === '/faculty/profile') {
    require __DIR__ . '/../views/pages/faculty_profile.php';
    exit;
}

// ============================================
// STAFF ROUTES
// ============================================
if ($uri === '/staff/dashboard') {
    require __DIR__ . '/../views/pages/staff_dashboard.php';
    exit;
}

if ($uri === '/staff/leaves') {
    require __DIR__ . '/../views/pages/staff_leaves.php';
    exit;
}

// Staff profile reuses the faculty profile page
if ($uri === '/staff/profile') {
    require __DIR__ . '/../views/pages/faculty_profile.php';
    exit;
}

// 404 fallback
http_response_code(404);
echo '<h1 style="font-family:sans-serif;padding:2rem;">404 — Page Not Found</h1>';