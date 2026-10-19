<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../helpers/ActivityLogger.php';
require_once __DIR__ . '/../services/MailService.php';
require_once __DIR__ . '/../helpers/EmailTemplates.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';
$userId = (int) $_SESSION['user_id'];
$role   = $_SESSION['role'] ?? '';

try {
    switch ($action) {

        // ============================================
        // Update personal information
        //   admin  → apply immediately
        //   others → store as pending, set flag
        // ============================================
        case 'update_info':
            $isPrivileged = ($role === 'admin');

            if (!$isPrivileged) {
                $stmt = $pdo->prepare("SELECT profile_edit_requested FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                if ((int) $stmt->fetchColumn() === 1) {
                    echo json_encode([
                        'success' => false,
                        'error'   => 'You already have a pending profile change. Please wait for admin approval.'
                    ]);
                    break;
                }
            }

            $first  = trim($_POST['first_name'] ?? '');
            $middle = trim($_POST['middle_name'] ?? '');
            $last   = trim($_POST['last_name'] ?? '');
            $email  = trim($_POST['email'] ?? '');

            if ($first === '' || $last === '') {
                echo json_encode(['success' => false, 'error' => 'First and last name are required.']);
                break;
            }

            $stmt = $pdo->prepare("SELECT employee_id FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $row = $stmt->fetch();
            $employeeId = isset($row['employee_id']) ? (int) $row['employee_id'] : 0;

            if ($employeeId <= 0) {
                echo json_encode(['success' => false, 'error' => 'No employee record linked to this account.']);
                break;
            }

            if ($isPrivileged) {
                // ADMIN — apply immediately
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("
                        UPDATE employees
                        SET first_name = ?, middle_name = ?, last_name = ?, email = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$first, $middle, $last, $email, $employeeId]);

                    $_SESSION['first_name'] = $first;
                    $_SESSION['last_name']  = $last;

                    $pdo->commit();
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    throw $e;
                }

                ActivityLogger::log(
                    $pdo,
                    'update_profile',
                    "Admin updated own profile information (applied immediately)",
                    'user',
                    $userId
                );

                echo json_encode([
                    'success' => true,
                    'applied' => true,
                    'message' => 'Profile updated successfully.',
                ]);
                break;
            }

            // FACULTY / STAFF / HR — store as pending
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("
                    UPDATE employees
                    SET pending_first_name  = ?,
                        pending_middle_name = ?,
                        pending_last_name   = ?,
                        pending_email       = ?
                    WHERE id = ?
                ");
                $stmt->execute([$first, $middle, $last, $email, $employeeId]);

                $stmt = $pdo->prepare("UPDATE users SET profile_edit_requested = 1 WHERE id = ?");
                $stmt->execute([$userId]);

                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }

            ActivityLogger::log(
                $pdo,
                'request_profile_edit',
                "Submitted profile change request for admin approval",
                'user',
                $userId
            );

            echo json_encode([
                'success' => true,
                'applied' => false,
                'message' => 'Profile change submitted for admin approval.',
            ]);
            break;

        // ============================================
        // Change password (self-service) — unchanged
        // ============================================
        case 'update_password':
            $current = $_POST['current_password'] ?? '';
            $new     = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            if (empty($current) || empty($new) || empty($confirm)) {
                echo json_encode(['success' => false, 'error' => 'Please fill in all fields.']);
                break;
            }
            if ($new !== $confirm) {
                echo json_encode(['success' => false, 'error' => 'New passwords do not match.']);
                break;
            }

            $errors = [];
            if (strlen($new) < 8) $errors[] = 'at least 8 characters';
            if (!preg_match('/[A-Z]/', $new)) $errors[] = 'an uppercase letter';
            if (!preg_match('/[a-z]/', $new)) $errors[] = 'a lowercase letter';
            if (!preg_match('/[0-9]/', $new)) $errors[] = 'a number';
            if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'"\\|,.<>\/?]/', $new)) $errors[] = 'a special character';

            if (!empty($errors)) {
                echo json_encode([
                    'success' => false,
                    'error'   => 'Password must contain ' . implode(', ', $errors) . '.',
                ]);
                break;
            }

            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($current, $user['password'])) {
                echo json_encode(['success' => false, 'error' => 'Current password is incorrect.']);
                break;
            }

            $hashed = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed, $userId]);

            ActivityLogger::log($pdo, 'change_password', "Changed own password", 'user', $userId);

            $emailSent = false;
            $emailError = null;
            try {
                $stmt = $pdo->prepare("
                    SELECT u.username, e.first_name, e.last_name, e.email
                    FROM users u
                    LEFT JOIN employees e ON u.employee_id = e.id
                    WHERE u.id = ?
                    LIMIT 1
                ");
                $stmt->execute([$userId]);
                $info = $stmt->fetch();

                if ($info && !empty($info['email'])) {
                    $displayName = trim(($info['first_name'] ?? '') . ' ' . ($info['last_name'] ?? ''))
                        ?: $info['username'];

                    $mailer = new MailService();
                    $emailSent = $mailer
                        ->to($info['email'], $displayName)
                        ->subject('Your password was changed — DAMMC')
                        ->body(EmailTemplates::passwordChanged(
                            $displayName,
                            $info['username'],
                            date('F j, Y \a\t g:i A')
                        ))
                        ->send();
                    if (!$emailSent) $emailError = 'Email could not be sent.';
                } else {
                    $emailError = 'No email address on file.';
                }
            } catch (Throwable $e) {
                $emailError = $e->getMessage();
                error_log('Password-change email failed: ' . $e->getMessage());
            }

            echo json_encode([
                'success'     => true,
                'message'     => 'Password updated successfully.',
                'email_sent'  => $emailSent,
                'email_error' => $emailError,
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action.']);
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}