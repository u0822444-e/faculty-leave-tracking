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

// ============================================
// AUTH
// ============================================
if (($uri === '/login' || $uri === '/login.php') && $method === 'POST') {
    require __DIR__ . '/../app/controllers/AuthController.php';
    exit;
}

if (($uri === '/login' || $uri === '/login.php' || $uri === '/') && $method === 'GET') {
    if (isset($_SESSION['user_id'])) {
        $role = $_SESSION['role'] ?? '';
        if ($role === 'faculty') {
            header('Location: /faculty/dashboard');
        } elseif ($role === 'staff') {
            header('Location: /staff/dashboard');
        } elseif ($role === 'hr') {
            header('Location: /hr/dashboard');
        } else {
            header('Location: /logout');
        }
        exit;
    }
    require __DIR__ . '/index.php';
    exit;
}

// Logout
if ($uri === '/logout' || $uri === '/logout.php') {
    require __DIR__ . '/logout.php';
    exit;
}

// Change password (welcome flow)
if ($uri === '/change-password' && $method === 'GET') {
    require __DIR__ . '/../views/pages/change_password.php';
    exit;
}
if ($uri === '/change-password' && $method === 'POST') {
    require __DIR__ . '/../app/controllers/PasswordController.php';
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

// ============================================
// PROFILE (everyone)
// ============================================
if ($uri === '/profile') {
    require __DIR__ . '/../views/pages/profile.php';
    exit;
}
if ($uri === '/api/profile') {
    require __DIR__ . '/../app/controllers/ProfileController.php';
    exit;
}
if ($uri === '/api/avatar') {
    require __DIR__ . '/../app/controllers/AvatarController.php';
    exit;
}

// Notifications (everyone)
if ($uri === '/api/notifications') {
    require __DIR__ . '/../app/controllers/NotificationController.php';
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

// ============================================
// HR ROUTES
// ============================================
if ($uri === '/hr/dashboard') {
    require __DIR__ . '/../views/pages/hr_dashboard.php';
    exit;
}

// HR reviews leave requests
if ($uri === '/hr/leaves' || $uri === '/leaves') {
    require __DIR__ . '/../views/pages/hr_leaves.php';
    exit;
}

// HR manages users — LIST page only
if ($uri === '/hr/users' || $uri === '/faculty' || $uri === '/users') {
    require __DIR__ . '/../views/pages/users.php';
    exit;
}

// HR user detail — must come BEFORE or independent of the list route (regex won't clash)
if (preg_match('#^/(?:hr/users|faculty|users)/(\d+)$#', $uri, $matches)) {
    $_GET['id'] = (int) $matches[1];
    require __DIR__ . '/../views/pages/user_detail.php';
    exit;
}

// HR users API
if ($uri === '/api/hr/users' || $uri === '/api/faculty' || $uri === '/api/users') {
    require __DIR__ . '/../app/controllers/UserController.php';
    exit;
}

// HR payroll
if ($uri === '/hr/payroll' || $uri === '/payroll') {
    require __DIR__ . '/../views/pages/payroll.php';
    exit;
}

// HR settings
if ($uri === '/hr/settings' || $uri === '/settings') {
    require __DIR__ . '/../views/pages/settings.php';
    exit;
}
if ($uri === '/api/settings') {
    require __DIR__ . '/../app/controllers/SettingsController.php';
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
if ($uri === '/staff/profile') {
    require __DIR__ . '/../views/pages/faculty_profile.php';
    exit;
}

// Leave monitoring (leadership)
if ($uri === '/leave-monitoring') {
    require __DIR__ . '/../views/pages/leave_monitoring.php';
    exit;
}

// Leave API (shared)
if ($uri === '/api/leaves') {
    require __DIR__ . '/../app/controllers/LeaveController.php';
    exit;
}

if ($uri === '/accounting/dashboard') {
    require __DIR__ . '/../views/pages/accounting_dashboard.php';
    exit;
}
if ($uri === '/accounting/payroll') {
    require __DIR__ . '/../views/pages/accounting_payroll.php';
    exit;
}
if ($uri === '/accounting/leaves') {
    require __DIR__ . '/../views/pages/staff_leaves.php';
    exit;
}

// ============================================
// ACCOUNTING PAYROLL API
// ============================================
if ($uri === '/api/accounting/payroll') {
    require __DIR__ . '/../app/controllers/AccountingPayrollController.php';
    exit;
}

// 404 fallback
http_response_code(404);
echo '<h1 style="font-family:sans-serif;padding:2rem;">404 — Page Not Found</h1>';