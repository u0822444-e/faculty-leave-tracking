<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../helpers/ActivityLogger.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';
$userId = (int) $_SESSION['user_id'];

try {
    switch ($action) {

        // Update personal information
        case 'update_info':
            $first = trim($_POST['first_name'] ?? '');
            $middle = trim($_POST['middle_name'] ?? '');
            $last = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');

            if (empty($first) || empty($last)) {
                echo json_encode(['success' => false, 'error' => 'First and last name are required.']);
                break;
            }

            // Get employee_id
            $stmt = $pdo->prepare("SELECT employee_id FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $row = $stmt->fetch();
            $employeeId = $row['employee_id'] ?? null;

            if ($employeeId) {
                $stmt = $pdo->prepare("
                    UPDATE employees 
                    SET first_name = ?, middle_name = ?, last_name = ?, email = ?
                    WHERE id = ?
                ");
                $stmt->execute([$first, $middle, $last, $email, $employeeId]);
            }

            ActivityLogger::log(
                $pdo,
                'update_profile',
                "Updated own profile information",
                'user',
                $userId
            );

            echo json_encode(['success' => true, 'message' => 'Profile updated successfully.']);
            break;

        // Change password
        case 'update_password':
            $current = $_POST['current_password'] ?? '';
            $new = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            if (empty($current) || empty($new) || empty($confirm)) {
                echo json_encode(['success' => false, 'error' => 'Please fill in all fields.']);
                break;
            }

            if ($new !== $confirm) {
                echo json_encode(['success' => false, 'error' => 'New passwords do not match.']);
                break;
            }

            // Strong password validation
            $errors = [];
            if (strlen($new) < 8)
                $errors[] = 'at least 8 characters';
            if (!preg_match('/[A-Z]/', $new))
                $errors[] = 'an uppercase letter';
            if (!preg_match('/[a-z]/', $new))
                $errors[] = 'a lowercase letter';
            if (!preg_match('/[0-9]/', $new))
                $errors[] = 'a number';
            if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'"\\|,.<>\/?]/', $new))
                $errors[] = 'a special character';

            if (!empty($errors)) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Password must contain ' . implode(', ', $errors) . '.'
                ]);
                break;
            }

            // Verify current password
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($current, $user['password'])) {
                echo json_encode(['success' => false, 'error' => 'Current password is incorrect.']);
                break;
            }

            // Update password
            $hashed = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed, $userId]);

            ActivityLogger::log(
                $pdo,
                'change_password',
                "Changed own password",
                'user',
                $userId
            );

            echo json_encode(['success' => true, 'message' => 'Password updated successfully.']);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}