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
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hr') {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
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
            $includeInactive = !empty($_GET['include_inactive'])
                || (($_POST['include_inactive'] ?? '') === '1');

            $sql = "
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
                    e.year_level,
                    e.position,
                    e.employment_type,
                    e.basic_salary,
                    e.leave_credits,
                    e.leave_pay,
                    u.created_at
                FROM users u
                LEFT JOIN employees e ON u.employee_id = e.id
            ";

            if (!$includeInactive) {
                $sql .= " WHERE u.status != 'inactive' ";
            }

            $sql .= " ORDER BY u.id DESC ";

            $stmt = $pdo->query($sql);
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
                    'year_level' => $u['year_level'] ?? '',
                    'position' => $u['position'] ?? '',
                    'employment_type' => $u['employment_type'] ?? 'full-time',
                    'basic_salary' => $u['basic_salary'] ?? 0,
                    'leave_pay' => $u['leave_pay'] ?? 0,
                    'leave_credits' => $u['leave_credits'] ?? 0,
                    'status' => $u['status'],
                    'joined' => date('Y-m-d', strtotime($u['created_at'])),
                ];
            }, $users);

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'users' => $formatted]);
            break;

        // ============================================
        // Create new user + employee
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
            $yearLevel = trim($_POST['year_level'] ?? '');
            $position = trim($_POST['position'] ?? '');
            $empType = $_POST['employment_type'] ?? 'full-time';

            if ($position === '') {
                $position = 'N/A';
            }

            if (empty($first) || empty($last) || empty($username) || empty($password) || empty($role) || empty($category)) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Please fill in all required fields.']);
                break;
            }

            // Role ↔ Category coupling enforcement
            if ($role === 'faculty' && $category !== 'faculty') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Faculty role requires Faculty category.']);
                break;
            }
            if ($role === 'staff' && $category !== 'staff') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Staff role requires Staff category.']);
                break;
            }
            if ($role === 'hr' && $category !== 'staff') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'HR role requires Staff category.']);
                break;
            }

            $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $check->execute([$username]);
            if ($check->fetch()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Username already exists.']);
                break;
            }

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
                // Basic salary, leave_pay, and leave_credits default to 0/15.
                // Accounting will edit these later.
                $stmt = $pdo->prepare("
                    INSERT INTO employees 
                        (first_name, middle_name, last_name, email, category, sub_category, year_level, position, employment_type, basic_salary, leave_pay, leave_credits)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0.00, 0.00, 15.00)
                ");
                $stmt->execute([
                    $first,
                    $middle,
                    $last,
                    $email,
                    $category,
                    $subCat,
                    $yearLevel,
                    $position,
                    $empType,
                ]);
                $employeeId = $pdo->lastInsertId();

                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, password, role, employee_id, status, must_change_password)
                    VALUES (?, ?, ?, ?, 'active', 1)
                ");
                $stmt->execute([$username, $hashed, $role, $employeeId]);
                $newUserId = (int) $pdo->lastInsertId();

                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Could not create user: ' . $e->getMessage()]);
                break;
            }

            // ── Post-commit side effects (isolated) ──

            try {
                ActivityLogger::log(
                    $pdo,
                    'create_user',
                    "Created user account: {$username} ({$role})",
                    'user',
                    $newUserId
                );
            } catch (Throwable $e) {
                error_log('ActivityLogger failed (create_user): ' . $e->getMessage());
            }

            try {
                NotificationService::notify(
                    $pdo,
                    $newUserId,
                    'account_created',
                    'Welcome to DAMMC',
                    'Your account has been created. Please check your email for login details.',
                    '/profile'
                );
            } catch (Throwable $e) {
                error_log('Notify user failed (create_user): ' . $e->getMessage());
            }

            try {
                NotificationService::notifyAdmins(
                    $pdo,
                    'create_user',
                    "New user created: {$username}",
                    "Added as {$role} ({$category})",
                    '/users'
                );
            } catch (Throwable $e) {
                error_log('Notify admins failed (create_user): ' . $e->getMessage());
            }

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
                } catch (Throwable $e) {
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
            $yearLevel = trim($_POST['year_level'] ?? '');
            $position = trim($_POST['position'] ?? '');
            $empType = $_POST['employment_type'] ?? 'full-time';
            $status = $_POST['status'] ?? 'active';

            if ($position === '') {
                $position = 'N/A';
            }

            if ($id <= 0 || empty($first) || empty($last) || empty($username)) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Invalid input.']);
                break;
            }

            // Role ↔ Category coupling enforcement
            if ($role === 'faculty' && $category !== 'faculty') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Faculty role requires Faculty category.']);
                break;
            }
            if ($role === 'staff' && $category !== 'staff') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Staff role requires Staff category.']);
                break;
            }
            if ($role === 'hr' && $category !== 'staff') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'HR role requires Staff category.']);
                break;
            }

            $stmt = $pdo->prepare("
                SELECT 
                    u.id AS user_id, u.username, u.role, u.status, u.employee_id,
                    e.first_name, e.middle_name, e.last_name, e.email,
                    e.category, e.sub_category, e.year_level, e.position,
                    e.employment_type, e.basic_salary, e.leave_pay, e.leave_credits
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

            $check = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $check->execute([$username, $id]);
            if ($check->fetch()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Username already taken.']);
                break;
            }

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

            $passwordChanged = !empty($password);

            $pdo->beginTransaction();

            try {
                if ($employeeId !== null) {
                    // Financial fields are intentionally omitted from this UPDATE
                    $stmt = $pdo->prepare("
                        UPDATE employees 
                        SET first_name = ?, middle_name = ?, last_name = ?, email = ?, 
                            category = ?, sub_category = ?, year_level = ?, position = ?,
                            employment_type = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $first,
                        $middle,
                        $last,
                        $email,
                        $category,
                        $subCat,
                        $yearLevel,
                        $position,
                        $empType,
                        $employeeId,
                    ]);
                } else {
                    // Creating a missing employee profile for an existing user
                    // Default financials to 0
                    $stmt = $pdo->prepare("
                        INSERT INTO employees 
                            (first_name, middle_name, last_name, email, category, sub_category, year_level, position, employment_type, basic_salary, leave_pay, leave_credits)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0.00, 0.00, 15.00)
                    ");
                    $stmt->execute([
                        $first,
                        $middle,
                        $last,
                        $email,
                        $category,
                        $subCat,
                        $yearLevel,
                        $position,
                        $empType,
                    ]);
                    $employeeId = (int) $pdo->lastInsertId();

                    $stmt = $pdo->prepare("UPDATE users SET employee_id = ? WHERE id = ?");
                    $stmt->execute([$employeeId, $id]);
                }

                if ($passwordChanged) {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET username = ?, password = ?, role = ?, status = ?, must_change_password = 1 
                        WHERE id = ?
                    ");
                    $stmt->execute([$username, $hashed, $role, $status, $id]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE users SET username = ?, role = ?, status = ? WHERE id = ?
                    ");
                    $stmt->execute([$username, $role, $status, $id]);
                }

                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Could not update user: ' . $e->getMessage()]);
                break;
            }

            // ── Post-commit side effects (isolated) ──

            try {
                ActivityLogger::log($pdo, 'update_user', "Updated user account: {$username}", 'user', $id);
            } catch (Throwable $e) {
                error_log('ActivityLogger failed (update_user): ' . $e->getMessage());
            }

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
                'year_level' => ['label' => 'Year level', 'before' => $before['year_level'] ?? '', 'after' => $yearLevel],
                'position' => ['label' => 'Position', 'before' => $before['position'] ?? '', 'after' => $position],
                'employment_type' => ['label' => 'Employment type', 'before' => $before['employment_type'] ?? '', 'after' => $empType],
            ];

            foreach ($fields as $key => $f) {
                $b = (string) $f['before'];
                $a = (string) $f['after'];
                if ($b !== $a) {
                    $changes[] = "{$f['label']}: " . ($b === '' ? '—' : $b) . " → " . ($a === '' ? '—' : $a);
                }
            }

            if ($passwordChanged) {
                $changes[] = 'Password was reset';
            }

            // In-app notification to the affected user
            try {
                if (!empty($changes)) {
                    $title = 'Your account was updated';
                    $message = implode(' · ', $changes);
                    $type = 'account_updated';

                    NotificationService::notify(
                        $pdo,
                        $id,
                        $type,
                        $title,
                        $message,
                        '/profile'
                    );
                }
            } catch (Throwable $e) {
                error_log('Notify user failed (update_user): ' . $e->getMessage());
            }

            // Admin broadcast
            try {
                NotificationService::notifyAdmins(
                    $pdo,
                    'update_user',
                    "User updated: {$username}",
                    !empty($changes) ? implode(' · ', array_slice($changes, 0, 3)) : 'Profile details were saved',
                    '/users'
                );
            } catch (Throwable $e) {
                error_log('Notify admins failed (update_user): ' . $e->getMessage());
            }

            // Password-change confirmation email
            $passwordEmailSent = false;
            $passwordEmailError = null;

            if ($passwordChanged) {
                try {
                    $stmtMail = $pdo->prepare("
                        SELECT u.username, e.first_name, e.middle_name, e.last_name, e.email
                        FROM users u
                        LEFT JOIN employees e ON u.employee_id = e.id
                        WHERE u.id = ?
                        LIMIT 1
                    ");
                    $stmtMail->execute([$id]);
                    $recipient = $stmtMail->fetch();

                    $recipientEmail = trim($recipient['email'] ?? '');
                    $recipientName = trim(
                        ($recipient['first_name'] ?? '') . ' ' .
                        ($recipient['middle_name'] ?? '') . ' ' .
                        ($recipient['last_name'] ?? '')
                    );
                    $recipientName = preg_replace('/\s+/', ' ', $recipientName);
                    if ($recipientName === '') {
                        $recipientName = $recipient['username'] ?? 'User';
                    }

                    if ($recipientEmail !== '') {
                        $mailer = new MailService();
                        $passwordEmailSent = $mailer
                            ->to($recipientEmail, $recipientName)
                            ->subject('Your DAMMC password was reset')
                            ->body(EmailTemplates::passwordChanged(
                                $recipientName,
                                $recipient['username'] ?? $username,
                                date('F j, Y \\a\\t g:i A'),
                                $_SESSION['username'] ?? 'an administrator'
                            ))
                            ->send();

                        if (!$passwordEmailSent) {
                            $passwordEmailError = 'Email could not be sent.';
                        }
                    } else {
                        $passwordEmailError = 'No email address on file for this user.';
                        error_log('Password change email skipped: no email on file for user #' . $id);
                    }
                } catch (Throwable $e) {
                    $passwordEmailError = $e->getMessage();
                    error_log('Password change email failed (update_user): ' . $e->getMessage());
                }
            }

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'User updated successfully.',
                'changes' => $changes,
                'password_email_sent' => $passwordEmailSent,
                'password_email_error' => $passwordEmailError,
            ]);
            break;

        // ============================================
        // Approve Profile Edit
        // ============================================
        case 'approve_profile_edit':
            $id = intval($_POST['id'] ?? 0);

            if ($id <= 0) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Invalid user ID.']);
                break;
            }

            $stmt = $pdo->prepare("
                SELECT 
                    u.id, u.avatar, u.pending_avatar,
                    u.employee_id,
                    e.id AS emp_id,
                    e.first_name, e.middle_name, e.last_name, e.email,
                    e.pending_first_name, e.pending_middle_name, e.pending_last_name, e.pending_email
                FROM users u
                LEFT JOIN employees e ON u.employee_id = e.id
                WHERE u.id = ?
                LIMIT 1
            ");
            $stmt->execute([$id]);
            $row = $stmt->fetch();

            if (!$row) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'User not found.']);
                break;
            }

            $hasPendingName = $row['pending_first_name'] !== null || $row['pending_last_name'] !== null;
            $hasPendingEmail = $row['pending_email'] !== null;
            $hasPendingAvatar = $row['pending_avatar'] !== null;

            $pdo->beginTransaction();
            try {
                if ($row['emp_id'] !== null && ($hasPendingName || $hasPendingEmail)) {
                    $newFirst = $row['pending_first_name'] ?? $row['first_name'];
                    $newMiddle = $row['pending_middle_name'] ?? $row['middle_name'];
                    $newLast = $row['pending_last_name'] ?? $row['last_name'];
                    $newEmail = $row['pending_email'] ?? $row['email'];

                    $stmt = $pdo->prepare("
                        UPDATE employees
                        SET first_name = ?, middle_name = ?, last_name = ?, email = ?,
                            pending_first_name  = NULL,
                            pending_middle_name = NULL,
                            pending_last_name   = NULL,
                            pending_email       = NULL
                        WHERE id = ?
                    ");
                    $stmt->execute([$newFirst, $newMiddle, $newLast, $newEmail, $row['emp_id']]);
                }

                if ($hasPendingAvatar) {
                    $pendingAvatar = $row['pending_avatar'];
                    $old = $row['avatar'];

                    if ($pendingAvatar === '') {
                        $pdo->prepare("UPDATE users SET avatar = NULL, pending_avatar = NULL WHERE id = ?")
                            ->execute([$id]);
                        if (!empty($old)) {
                            $path = __DIR__ . '/../../public_html/uploads/avatars/' . $old;
                            if (is_file($path))
                                @unlink($path);
                        }
                    } else {
                        $pdo->prepare("UPDATE users SET avatar = ?, pending_avatar = NULL WHERE id = ?")
                            ->execute([$pendingAvatar, $id]);
                        if (!empty($old) && $old !== $pendingAvatar) {
                            $path = __DIR__ . '/../../public_html/uploads/avatars/' . $old;
                            if (is_file($path))
                                @unlink($path);
                        }
                    }
                }

                $pdo->prepare("UPDATE users SET profile_edit_requested = 0 WHERE id = ?")
                    ->execute([$id]);

                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Approval failed: ' . $e->getMessage()]);
                break;
            }

            try {
                ActivityLogger::log($pdo, 'approve_profile_edit', "Approved profile edit request", 'user', $id);
            } catch (Throwable $e) {
                error_log('ActivityLogger failed (approve_profile_edit): ' . $e->getMessage());
            }

            try {
                NotificationService::notify(
                    $pdo,
                    $id,
                    'profile_edit_approved',
                    'Profile Update Approved',
                    'Your profile update has been approved and applied.',
                    '/profile'
                );
            } catch (Throwable $e) {
                error_log('Notify user failed (approve_profile_edit): ' . $e->getMessage());
            }

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Profile edit approved and applied.']);
            break;

        // ============================================
        // Reject Profile Edit
        // ============================================
        case 'reject_profile_edit':
            $id = intval($_POST['id'] ?? 0);

            if ($id <= 0) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Invalid user ID.']);
                break;
            }

            $stmt = $pdo->prepare("
                SELECT u.pending_avatar, u.avatar, e.id AS emp_id
                FROM users u
                LEFT JOIN employees e ON u.employee_id = e.id
                WHERE u.id = ?
                LIMIT 1
            ");
            $stmt->execute([$id]);
            $row = $stmt->fetch();

            if (!$row) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'User not found.']);
                break;
            }

            $pdo->beginTransaction();
            try {
                if (!empty($row['pending_avatar']) && $row['pending_avatar'] !== $row['avatar']) {
                    $path = __DIR__ . '/../../public_html/uploads/avatars/' . $row['pending_avatar'];
                    if (is_file($path))
                        @unlink($path);
                }

                $pdo->prepare("UPDATE users SET pending_avatar = NULL, profile_edit_requested = 0 WHERE id = ?")
                    ->execute([$id]);

                if ($row['emp_id'] !== null) {
                    $pdo->prepare("
                        UPDATE employees
                        SET pending_first_name  = NULL,
                            pending_middle_name = NULL,
                            pending_last_name   = NULL,
                            pending_email       = NULL
                        WHERE id = ?
                    ")->execute([$row['emp_id']]);
                }

                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Rejection failed: ' . $e->getMessage()]);
                break;
            }

            try {
                ActivityLogger::log($pdo, 'reject_profile_edit', "Rejected profile edit request", 'user', $id);
            } catch (Throwable $e) {
                error_log('ActivityLogger failed (reject_profile_edit): ' . $e->getMessage());
            }

            try {
                NotificationService::notify(
                    $pdo,
                    $id,
                    'profile_edit_rejected',
                    'Profile Update Rejected',
                    'Your recent profile update was rejected by an administrator. Please contact HR if you need assistance.',
                    '/profile'
                );
            } catch (Throwable $e) {
                error_log('Notify user failed (reject_profile_edit): ' . $e->getMessage());
            }

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Profile edit rejected.']);
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

            try {
                ActivityLogger::log(
                    $pdo,
                    'archive_user',
                    "Archived user account: {$target['username']} (ID: {$id})",
                    'user',
                    $id
                );
            } catch (Throwable $e) {
                error_log('ActivityLogger failed (archive_user): ' . $e->getMessage());
            }

            try {
                NotificationService::notify(
                    $pdo,
                    $id,
                    'account_archived',
                    'Your account was deactivated',
                    'An administrator has deactivated your account. Contact HR for details.',
                    ''
                );
            } catch (Throwable $e) {
                error_log('Notify archived user failed: ' . $e->getMessage());
            }

            try {
                NotificationService::notifyAdmins(
                    $pdo,
                    'archive_user',
                    "User archived: {$target['username']}",
                    "Account deactivated by " . ($_SESSION['username'] ?? 'an admin'),
                    '/users'
                );
            } catch (Throwable $e) {
                error_log('Notify admins failed (archive_user): ' . $e->getMessage());
            }

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'User archived.']);
            break;

        // ============================================
        // Restore user
        // ============================================
        case 'restore':
            $id = intval($_POST['id'] ?? 0);

            if ($id <= 0) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Invalid user ID.']);
                break;
            }

            $stmt = $pdo->prepare("SELECT username, status FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $target = $stmt->fetch();

            if (!$target) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'User not found.']);
                break;
            }

            if ($target['status'] === 'active') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'User is already active.']);
                break;
            }

            $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
            $stmt->execute([$id]);

            try {
                ActivityLogger::log(
                    $pdo,
                    'restore_user',
                    "Restored user account: {$target['username']} (ID: {$id})",
                    'user',
                    $id
                );
            } catch (Throwable $e) {
                error_log('ActivityLogger failed (restore_user): ' . $e->getMessage());
            }

            try {
                NotificationService::notify(
                    $pdo,
                    $id,
                    'account_restored',
                    'Your account was restored',
                    'An administrator has reactivated your account. You can log in again.',
                    '/profile'
                );
            } catch (Throwable $e) {
                error_log('Notify restored user failed: ' . $e->getMessage());
            }

            try {
                NotificationService::notifyAdmins(
                    $pdo,
                    'restore_user',
                    "User restored: {$target['username']}",
                    "Account reactivated by " . ($_SESSION['username'] ?? 'an admin'),
                    '/users'
                );
            } catch (Throwable $e) {
                error_log('Notify admins failed (restore_user): ' . $e->getMessage());
            }

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'User restored.']);
            break;

        // ============================================
        // Admin-initiated password reset (email link)
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

            $appUrl = rtrim(Env::get('APP_URL', 'http://localhost'), '/');
            $resetLink = $appUrl . '/reset-password?token=' . urlencode($token);

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

                try {
                    ActivityLogger::log(
                        $pdo,
                        'send_reset',
                        "Sent password reset link to {$user['email']}",
                        'user',
                        $id
                    );
                } catch (Throwable $e) {
                    error_log('ActivityLogger failed (send_reset): ' . $e->getMessage());
                }

                try {
                    NotificationService::notify(
                        $pdo,
                        $id,
                        'password_reset_sent',
                        'Password reset requested',
                        'A password reset link was sent to your email by an administrator.',
                        ''
                    );
                } catch (Throwable $e) {
                    error_log('Notify user failed (send_reset): ' . $e->getMessage());
                }

                try {
                    NotificationService::notifyAdmins(
                        $pdo,
                        'send_reset',
                        "Reset link sent to {$user['username']}",
                        "Password reset initiated by " . ($_SESSION['username'] ?? 'an admin'),
                        '/users'
                    );
                } catch (Throwable $e) {
                    error_log('Notify admins failed (send_reset): ' . $e->getMessage());
                }

                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Reset link sent.']);
            } catch (Throwable $e) {
                error_log('Send reset failed: ' . $e->getMessage());
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Failed to send email: ' . $e->getMessage()]);
            }
            break;

        default:
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid action.']);
    }
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} catch (Throwable $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}