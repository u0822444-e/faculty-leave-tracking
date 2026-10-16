<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../services/MailService.php';
require_once __DIR__ . '/../helpers/EmailTemplates.php';
require_once __DIR__ . '/../helpers/ActivityLogger.php';

$action = $_POST['action'] ?? '';

// Detect whether this call came from the AJAX change-password page or a form POST
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Also treat POSTs to /change-password as JSON responses
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$isChangePasswordEndpoint = str_starts_with($requestUri, '/change-password');

if ($isChangePasswordEndpoint) {
    header('Content-Type: application/json');
}

try {

    // ============================================
    // REQUEST RESET LINK
    // ============================================
    if ($action === 'request') {
        $email = trim($_POST['email'] ?? '');

        if (empty($email)) {
            $_SESSION['error'] = 'Please enter your email address.';
            header('Location: /forgot-password');
            exit;
        }

        // Look up user by email (join employees)
        $stmt = $pdo->prepare("
            SELECT u.id, u.username, e.first_name, e.email
            FROM users u
            LEFT JOIN employees e ON u.employee_id = e.id
            WHERE e.email = ?
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Always show success message (don't reveal if email exists)
        if ($user) {
            // Generate secure token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+30 minutes'));

            $stmt = $pdo->prepare("
                UPDATE users 
                SET reset_token = ?, reset_token_expires_at = ? 
                WHERE id = ?
            ");
            $stmt->execute([$token, $expires, $user['id']]);

            $appUrl = rtrim(Env::get('APP_URL', 'http://localhost'), '/');
	    $resetLink = $appUrl . '/reset-password?token=' . urlencode($token);

            // Debug log
            error_log('RESET LINK: ' . $resetLink);

            // Send email
            try {
                $mailer = new MailService();
                $mailer
                    ->to($email, $user['first_name'] ?: $user['username'])
                    ->subject('Password Reset Request — DAMMC')
                    ->body(EmailTemplates::passwordReset(
                        $user['first_name'] ?: $user['username'],
                        $resetLink
                    ))
                    ->send();
            } catch (Throwable $e) {
                error_log('Reset email failed: ' . $e->getMessage());
            }
        }

        $_SESSION['success'] = 'If that email is registered, you will receive a reset link shortly.';
        header('Location: /forgot-password');
        exit;
    }

    // ============================================
    // RESET PASSWORD WITH TOKEN
    // ============================================
    if ($action === 'reset') {
        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (empty($token) || empty($password) || empty($confirm)) {
            $_SESSION['error'] = 'Please fill in all fields.';
            header('Location: /reset-password?token=' . urlencode($token));
            exit;
        }

        if ($password !== $confirm) {
            $_SESSION['error'] = 'Passwords do not match.';
            header('Location: /reset-password?token=' . urlencode($token));
            exit;
        }

        $errors = [];
        if (strlen($password) < 8)
            $errors[] = 'at least 8 characters';
        if (!preg_match('/[A-Z]/', $password))
            $errors[] = 'an uppercase letter';
        if (!preg_match('/[a-z]/', $password))
            $errors[] = 'a lowercase letter';
        if (!preg_match('/[0-9]/', $password))
            $errors[] = 'a number';
        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'"\\|,.<>\/?]/', $password))
            $errors[] = 'a special character';

        if (!empty($errors)) {
            $_SESSION['error'] = 'Password must contain ' . implode(', ', $errors) . '.';
            header('Location: /reset-password?token=' . urlencode($token));
            exit;
        }

        // Validate token
        $stmt = $pdo->prepare("
            SELECT id FROM users 
            WHERE reset_token = ? 
              AND reset_token_expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if (!$user) {
            $_SESSION['error'] = 'This reset link is invalid or has expired.';
            header('Location: /forgot-password');
            exit;
        }

        // Update password + clear token
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            UPDATE users 
            SET password = ?, reset_token = NULL, reset_token_expires_at = NULL 
            WHERE id = ?
        ");
        $stmt->execute([$hashed, $user['id']]);

        $_SESSION['success'] = 'Password reset successfully. You can now sign in.';
        header('Location: /login');
        exit;
    }

    // ============================================
    // CHANGE PASSWORD (welcome flow — AJAX, JSON response)
    // ============================================
    if ($action === 'change_password') {
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
            exit;
        }

        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($current === '' || $new === '' || $confirm === '') {
            echo json_encode(['success' => false, 'error' => 'Please fill in all fields.']);
            exit;
        }
        if ($new !== $confirm) {
            echo json_encode(['success' => false, 'error' => 'New passwords do not match.']);
            exit;
        }
        if (strlen($new) < 8) {
            echo json_encode(['success' => false, 'error' => 'Password must be at least 8 characters.']);
            exit;
        }
        if ($new === $current) {
            echo json_encode(['success' => false, 'error' => 'New password must be different from the current one.']);
            exit;
        }

        $userId = (int) $_SESSION['user_id'];
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $hash = $stmt->fetchColumn();

        if (!$hash || !password_verify($current, $hash)) {
            echo json_encode(['success' => false, 'error' => 'Current password is incorrect.']);
            exit;
        }

        $newHash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            UPDATE users 
            SET password = ?, must_change_password = 0 
            WHERE id = ?
        ");
        $stmt->execute([$newHash, $userId]);

        if (class_exists('ActivityLogger')) {
            ActivityLogger::log($pdo, 'change_password', 'Changed account password', 'user', $userId);
        }

        echo json_encode(['success' => true, 'message' => 'Password updated.']);
        exit;
    }

    // ============================================
    // SKIP PASSWORD CHANGE (welcome flow — AJAX, JSON response)
    // ============================================
    if ($action === 'skip_password_change') {
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
            exit;
        }

        $userId = (int) $_SESSION['user_id'];
        $stmt = $pdo->prepare("UPDATE users SET must_change_password = 0 WHERE id = ?");
        $stmt->execute([$userId]);

        echo json_encode(['success' => true]);
        exit;
    }

    // ============================================
    // Fallback — unknown action
    // ============================================
    if ($isChangePasswordEndpoint) {
        echo json_encode(['success' => false, 'error' => 'Unknown action.']);
    } else {
        header('Location: /forgot-password');
    }
    exit;

} catch (PDOException $e) {
    error_log('Password controller error: ' . $e->getMessage());

    if ($isChangePasswordEndpoint) {
        echo json_encode(['success' => false, 'error' => 'Database error. Please try again.']);
    } else {
        $_SESSION['error'] = 'Something went wrong. Please try again.';
        header('Location: /forgot-password');
    }
    exit;

} catch (Throwable $e) {
    error_log('Password controller error (generic): ' . $e->getMessage());

    if ($isChangePasswordEndpoint) {
        echo json_encode(['success' => false, 'error' => 'Server error. Please try again.']);
    } else {
        $_SESSION['error'] = 'Something went wrong. Please try again.';
        header('Location: /forgot-password');
    }
    exit;
}
