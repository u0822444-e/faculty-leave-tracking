<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../services/MailService.php';
require_once __DIR__ . '/../helpers/EmailTemplates.php';
require_once __DIR__ . '/../helpers/ActivityLogger.php';
require_once __DIR__ . '/../helpers/NotificationService.php';

// Auth guard
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$actorId = (int) $_SESSION['user_id'];

try {
    switch ($action) {

        // ============================================
        // Read all users
        // ============================================
        case 'list':
            $stmt = $pdo->query("
                SELECT 
                    u.id AS user_id,
                    u.username,
                    u.role,
                    u.status,
                    e.id AS employee_id,
                    e.first_name,
                    e.middle_name,
                    e.last_name,
                    e.email,
                    e.category,
                    e.sub_category,
                    e.employment_type,
                    e.basic_salary,
                    e.leave_credits,
                    u.created_at
                FROM users u
                LEFT JOIN employees e ON u.employee_id = e.id
                ORDER BY u.id DESC
            ");
            $users = $stmt->fetchAll();

            $formatted = array_map(function ($u) {
                $first = $u['first_name'] ?? '';
                $last = $u['last_name'] ?? '';
                $fullName = trim("$first $last") ?: $u['username'];
                $initials = strtoupper(substr($first ?: $u['username'], 0, 1) . substr($last, 0, 1));

                return [
                    'id' => (int) $u['user_id'],
                    'employee_id' => $u['employee_id'] ? (int) $u['employee_id'] : null,
                    'first_name' => $u['first_name'] ?? '',
                    'middle_name' => $u['middle_name'] ?? '',
                    'last_name' => $u['last_name'] ?? '',
                    'full_name' => $fullName,
                    'initials' => $initials,
                    'username' => $u['username'],
                    'email' => $u['email'] ?? '—',
                    'role' => $u['role'],
                    'category' => $u['category'] ?? 'staff',
                    'sub_category' => $u['sub_category'] ?? '',
                    'employment_type' => $u['employment_type'] ?? 'full-time',
                    'basic_salary' => $u['basic_salary'] ?? 0,
                    'leave_credits' => $u['leave_credits'] ?? 0,
                    'status' => $u['status'],
                    'joined' => date('Y-m-d', strtotime($u['created_at'])),
                ];
            }, $users);

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'users' => $formatted]);
            break;

        // ============================================
        // Create new user + employee + send welcome email
        // ============================================
        case 'create':
            $first = trim($_POST['first_name'] ?? '');
            $middle = trim($_POST['middle_name'] ?? '');
            $last = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? '';
            $category = $_POST['category'] ?? '';
            $subCat = trim($_POST['sub_category'] ?? '');
            $empType = $_POST['employment_type'] ?? 'full-time';
            $salary = floatval($_POST['basic_salary'] ?? 0);
            $creditsRaw = $_POST['leave_credits'] ?? '';
            $credits = ($creditsRaw === '' || $creditsRaw === null)
                ? 15.0
                : floatval($creditsRaw);

            if ($empType === 'part-time') {
                $credits = 0;
            }

            // Basic validation
            if (empty($first) || empty($last) || empty($username) || empty($password) || empty($role) || empty($category)) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Please fill in all required fields.']);
                break;
            }

            // Check duplicate username
            $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $check->execute([$username]);
            if ($check->fetch()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Username already exists.']);
                break;
            }

            // Check duplicate email
            if (!empty($email)) {
                $check = $pdo->prepare("
                    SELECT id FROM employees 
                    WHERE LOWER(email) = LOWER(?) AND email != ''
                    LIMIT 1
                ");
                $check->execute([$email]);
                if ($check->fetch()) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => 'Email already in use by another user.']);
                    break;
                }
            }

            // Check duplicate full name
            $check = $pdo->prepare("
                SELECT id FROM employees 
                WHERE LOWER(first_name) = LOWER(?) 
                  AND LOWER(last_name) = LOWER(?)
                LIMIT 1
            ");
            $check->execute([$first, $last]);
            if ($check->fetch()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'A user with this name already exists.']);
                break;
            }

            $pdo->beginTransaction();

            try {
                // Insert employee
                $stmt = $pdo->prepare("
                    INSERT INTO employees 
                        (first_name, middle_name, last_name, email, category, sub_category, employment_type, basic_salary, leave_credits)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$first, $middle, $last, $email, $category, $subCat, $empType, $salary, $credits]);
                $employeeId = $pdo->lastInsertId();

                // Insert user
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, password, role, employee_id, status)
                    VALUES (?, ?, ?, ?, 'active')
                ");
                $stmt->execute([$username, $hashed, $role, $employeeId]);
                $newUserId = (int) $pdo->lastInsertId();

                $pdo->commit();

                ActivityLogger::log(
                    $pdo,
                    'create_user',
                    "Created user account: {$username} ({$role})",
                    'user',
                    $newUserId
                );

                // Notify the new user (they'll see it when they first log in)
                NotificationService::notify(
                    $pdo,
                    $newUserId,
                    'account_created',
                    'Welcome to DAMMC',
                    'Your account has been created. Please check your email for login details.',
                    '/profile'
                );

                // Notify other admins
                NotificationService::notifyAdmins(
                    $pdo,
                    'create_user',
                    "New user created: {$username}",
                    "Added as {$role} ({$category})",
                    '/users'
                );

                // Send welcome email (non-blocking)
                $emailSent = false;
                $emailError = null;

                if (!empty($email)) {
                    try {
                        $mailer = new MailService();
                        $emailSent = $mailer
                            ->to($email, "$first $last")
                            ->subject('Welcome to DAMMC Faculty Leave & Payroll System')
                            ->body(EmailTemplates::welcome("$first $last", $username, $password))
                            ->send();

                        if (!$emailSent) {
                            $emailError = 'Email could not be sent.';
                        }
                    } catch (Exception $e) {
                        $emailError = $e->getMessage();
                        error_log('Welcome email failed: ' . $e->getMessage());
                    }
                }

                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'User created successfully.',
                    'email_sent' => $emailSent,
                    'email_error' => $emailError,
                ]);
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;

        // ============================================
        // Update user + employee
        // ============================================
        case 'update':
            $id = intval($_POST['id'] ?? 0);
            $first = trim($_POST['first_name'] ?? '');
            $middle = trim($_POST['middle_name'] ?? '');
            $last = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? '';
            $category = $_POST['category'] ?? '';
            $subCat = trim($_POST['sub_category'] ?? '');
            $empType = $_POST['employment_type'] ?? 'full-time';
            $salary = floatval($_POST['basic_salary'] ?? 0);
            $status = $_POST['status'] ?? 'active';

            $creditsRaw = $_POST['leave_credits'] ?? '';
            $credits = ($creditsRaw === '' || $creditsRaw === null)
                ? 15.0
                : floatval($creditsRaw);

            // Part-time employees can't accrue leave credits
            if ($empType === 'part-time') {
                $credits = 0;
            }

            if ($id <= 0 || empty($first) || empty($last) || empty($username)) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Invalid input.']);
                break;
            }

            // Fetch current user + employee snapshot for diff
            $stmt = $pdo->prepare("
                SELECT 
                    u.id AS user_id, u.username, u.role, u.status, u.employee_id,
                    e.first_name, e.middle_name, e.last_name, e.email,
                    e.category, e.sub_category, e.employment_type,
                    e.basic_salary, e.leave_credits
                FROM users u
                LEFT JOIN employees e ON u.employee_id = e.id
                WHERE u.id = ?
                LIMIT 1
            ");
            $stmt->execute([$id]);
            $before = $stmt->fetch();

            if (!$before) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'User not found.']);
                break;
            }

            $employeeId = $before['employee_id'] !== null ? (int) $before['employee_id'] : null;

            // Check duplicate username (excluding self)
            $check = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $check->execute([$username, $id]);
            if ($check->fetch()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Username already taken.']);
                break;
            }

            // Check duplicate email — excluding self
            if (!empty($email)) {
                if ($employeeId !== null) {
                    $check = $pdo->prepare("
                        SELECT id FROM employees 
                        WHERE LOWER(email) = LOWER(?) 
                          AND email != '' 
                          AND id != ?
                        LIMIT 1
                    ");
                    $check->execute([$email, $employeeId]);
                } else {
                    $check = $pdo->prepare("
                        SELECT id FROM employees 
                        WHERE LOWER(email) = LOWER(?) 
                          AND email != ''
                        LIMIT 1
                    ");
                    $check->execute([$email]);
                }
                if ($check->fetch()) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => 'Email already in use by another user.']);
                    break;
                }
            }

            // Check duplicate name — excluding self
            if ($employeeId !== null) {
                $check = $pdo->prepare("
                    SELECT id FROM employees 
                    WHERE LOWER(first_name) = LOWER(?) 
                      AND LOWER(last_name) = LOWER(?)
                      AND id != ?
                    LIMIT 1
                ");
                $check->execute([$first, $last, $employeeId]);
            } else {
                $check = $pdo->prepare("
                    SELECT id FROM employees 
                    WHERE LOWER(first_name) = LOWER(?) 
                      AND LOWER(last_name) = LOWER(?)
                    LIMIT 1
                ");
                $check->execute([$first, $last]);
            }
            if ($check->fetch()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'A user with this name already exists.']);
                break;
            }

            $pdo->beginTransaction();

            try {
                if ($employeeId !== null) {
                    $stmt = $pdo->prepare("
                        UPDATE employees 
                        SET first_name = ?, middle_name = ?, last_name = ?, email = ?, 
                            category = ?, sub_category = ?, employment_type = ?, 
                            basic_salary = ?, leave_credits = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$first, $middle, $last, $email, $category, $subCat, $empType, $salary, $credits, $employeeId]);
                } else {
                    // No employee row — create one and link it
                    $stmt = $pdo->prepare("
                        INSERT INTO employees 
                            (first_name, middle_name, last_name, email, category, sub_category, employment_type, basic_salary, leave_credits)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$first, $middle, $last, $email, $category, $subCat, $empType, $salary, $credits]);
                    $employeeId = (int) $pdo->lastInsertId();

                    $stmt = $pdo->prepare("UPDATE users SET employee_id = ? WHERE id = ?");
                    $stmt->execute([$employeeId, $id]);
                }

                // Update user
                if (!empty($password)) {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        UPDATE users SET username = ?, password = ?, role = ?, status = ? WHERE id = ?
                    ");
                    $stmt->execute([$username, $hashed, $role, $status, $id]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE users SET username = ?, role = ?, status = ? WHERE id = ?
                    ");
                    $stmt->execute([$username, $role, $status, $id]);
                }

                $pdo->commit();

                ActivityLogger::log($pdo, 'update_user', "Updated user account: {$username}", 'user', $id);

                // ============================================
                // Build a diff summary
                // ============================================
                $changes = [];

                $fields = [
                    'first_name' => ['label' => 'First name', 'before' => $before['first_name'] ?? '', 'after' => $first],
                    'middle_name' => ['label' => 'Middle name', 'before' => $before['middle_name'] ?? '', 'after' => $middle],
                    'last_name' => ['label' => 'Last name', 'before' => $before['last_name'] ?? '', 'after' => $last],
                    'email' => ['label' => 'Email', 'before' => $before['email'] ?? '', 'after' => $email],
                    'username' => ['label' => 'Username', 'before' => $before['username'] ?? '', 'after' => $username],
                    'role' => ['label' => 'Role', 'before' => $before['role'] ?? '', 'after' => $role],
                    'status' => ['label' => 'Status', 'before' => $before['status'] ?? '', 'after' => $status],
                    'category' => ['label' => 'Category', 'before' => $before['category'] ?? '', 'after' => $category],
                    'sub_category' => ['label' => 'Sub-category', 'before' => $before['sub_category'] ?? '', 'after' => $subCat],
                    'employment_type' => ['label' => 'Employment type', 'before' => $before['employment_type'] ?? '', 'after' => $empType],
                ];

                foreach ($fields as $key => $f) {
                    $b = (string) $f['before'];
                    $a = (string) $f['after'];
                    if ($b !== $a) {
                        $changes[] = "{$f['label']}: " . ($b === '' ? '—' : $b) . " → " . ($a === '' ? '—' : $a);
                    }
                }

                // Numeric fields with formatting
                $beforeSalary = (float) ($before['basic_salary'] ?? 0);
                if (abs($beforeSalary - $salary) > 0.001) {
                    $changes[] = 'Basic salary: ₱' . number_format($beforeSalary, 2) . ' → ₱' . number_format($salary, 2);
                }

                $beforeCredits = (float) ($before['leave_credits'] ?? 0);
                if (abs($beforeCredits - $credits) > 0.001) {
                    $changes[] = 'Leave credits: ' . number_format($beforeCredits, 2) . ' → ' . number_format($credits, 2);
                }

                if (!empty($password)) {
                    $changes[] = 'Password was reset';
                }

                // ============================================
                // Notify the affected user
                // ============================================
                if (!empty($changes)) {
                    // Special-case: credits-only change gets a louder title
                    $creditsOnly =
                        count($changes) === 1
                        && str_starts_with($changes[0], 'Leave credits:');

                    if ($creditsOnly) {
                        $title = 'Your leave credits were updated';
                        $message = $changes[0] . ' by ' . ($_SESSION['username'] ?? 'an admin');
                        $type = 'leave_credits_updated';
                    } else {
                        $title = 'Your account was updated';
                        $message = implode(' · ', $changes);
                        $type = 'account_updated';
                    }

                    NotificationService::notify(
                        $pdo,
                        $id,
                        $type,
                        $title,
                        $message,
                        '/profile'
                    );
                }

                // Notify other admins
                NotificationService::notifyAdmins(
                    $pdo,
                    'update_user',
                    "User updated: {$username}",
                    !empty($changes) ? implode(' · ', array_slice($changes, 0, 3)) : 'Profile details were saved',
                    '/users'
                );

                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'User updated successfully.',
                    'changes' => $changes,
                ]);
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;

        // ============================================
        // Archive user (soft delete)
        // ============================================
        case 'archive':
            $id = intval($_POST['id'] ?? 0);

            if ($id <= 0) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Invalid user ID.']);
                break;
            }

            if ($id === $actorId) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'You cannot archive your own account.']);
                break;
            }

            // Fetch the target so we can notify
            $stmt = $pdo->prepare("SELECT username, status FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $target = $stmt->fetch();

            if (!$target) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'User not found.']);
                break;
            }

            $stmt = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
            $stmt->execute([$id]);

            ActivityLogger::log(
                $pdo,
                'archive_user',
                "Archived user account: {$target['username']} (ID: {$id})",
                'user',
                $id
            );

            // Notify the archived user — they won't see it until/unless restored, but it documents the event
            NotificationService::notify(
                $pdo,
                $id,
                'account_archived',
                'Your account was deactivated',
                'An administrator has deactivated your account. Contact HR for details.',
                ''
            );

            // Notify other admins
            NotificationService::notifyAdmins(
                $pdo,
                'archive_user',
                "User archived: {$target['username']}",
                "Account deactivated by " . ($_SESSION['username'] ?? 'an admin'),
                '/users'
            );

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'User archived.']);
            break;

        // ============================================
        // Admin-initiated password reset
        // ============================================
        case 'send_reset':
            $id = intval($_POST['id'] ?? 0);

            if ($id <= 0) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Invalid user ID.']);
                break;
            }

            $stmt = $pdo->prepare("
                SELECT u.id, u.username, e.first_name, e.email
                FROM users u
                LEFT JOIN employees e ON u.employee_id = e.id
                WHERE u.id = ?
                LIMIT 1
            ");
            $stmt->execute([$id]);
            $user = $stmt->fetch();

            if (!$user || empty($user['email'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'No email address on file for this user.']);
                break;
            }

            $token = bin2hex(random_bytes(32));
            $stmt = $pdo->prepare("
                UPDATE users 
                SET reset_token = ?, 
                    reset_token_expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR)
                WHERE id = ?
            ");
            $stmt->execute([$token, $user['id']]);

            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8002';
            $resetLink = $scheme . '://' . $host . '/reset-password?token=' . urlencode($token);

            try {
                $mailer = new MailService();
                $mailer
                    ->to($user['email'], $user['first_name'] ?: $user['username'])
                    ->subject('Password Reset Request — DAMMC')
                    ->body(EmailTemplates::passwordReset(
                        $user['first_name'] ?: $user['username'],
                        $resetLink
                    ))
                    ->send();

                ActivityLogger::log(
                    $pdo,
                    'send_reset',
                    "Sent password reset link to {$user['email']}",
                    'user',
                    $id
                );

                // Notify the target user
                NotificationService::notify(
                    $pdo,
                    $id,
                    'password_reset_sent',
                    'Password reset requested',
                    'A password reset link was sent to your email by an administrator.',
                    ''
                );

                // Notify other admins
                NotificationService::notifyAdmins(
                    $pdo,
                    'send_reset',
                    "Reset link sent to {$user['username']}",
                    "Password reset initiated by " . ($_SESSION['username'] ?? 'an admin'),
                    '/users'
                );

                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Reset link sent.']);
            } catch (Exception $e) {
                error_log('Send reset failed: ' . $e->getMessage());
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Failed to send email.']);
            }
            break;

        default:
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid action.']);
    }
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}