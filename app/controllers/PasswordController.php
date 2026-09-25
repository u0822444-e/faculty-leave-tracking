<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../services/MailService.php';
require_once __DIR__ . '/../helpers/EmailTemplates.php';

$action = $_POST['action'] ?? '';

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

            // Build dynamic reset link (works on any host/port)
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8002';
            $resetLink = $scheme . '://' . $host . '/reset-password?token=' . urlencode($token);

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
            } catch (Exception $e) {
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

    header('Location: /forgot-password');
    exit;

} catch (PDOException $e) {
    error_log('Password controller error: ' . $e->getMessage());
    $_SESSION['error'] = 'Something went wrong. Please try again.';
    header('Location: /forgot-password');
    exit;
}