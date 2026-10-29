<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../helpers/ActivityLogger.php';
require_once __DIR__ . '/../helpers/NotificationService.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$userId = (int) $_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';

if (!function_exists('fmt_days')) {
    function fmt_days($n)
    {
        return rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
    }
}

// Resolve employee_id for the current user
$stmt = $pdo->prepare("SELECT employee_id FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$userId]);
$row = $stmt->fetch();
$myEmployeeId = $row['employee_id'] !== null ? (int) $row['employee_id'] : null;

// Leave-type caps
$TYPE_CAPS = [
    'vacation' => 7.5,
    'sick' => 7.5,
];

try {
    switch ($action) {

        // ============================================
        // List leaves (faculty: only mine)
        // ============================================
        case 'list':
            if ($myEmployeeId === null && !in_array($role, ['admin', 'hr'], true)) {
                echo json_encode(['success' => true, 'leaves' => []]);
                break;
            }

            if (in_array($role, ['admin', 'hr'], true)) {
                $stmt = $pdo->query("
                    SELECT lr.*, e.first_name, e.last_name
                    FROM leave_requests lr
                    LEFT JOIN employees e ON lr.employee_id = e.id
                    ORDER BY lr.created_at DESC
                ");
            } else {
                $stmt = $pdo->prepare("
                    SELECT lr.*
                    FROM leave_requests lr
                    WHERE lr.employee_id = ?
                    ORDER BY lr.created_at DESC
                ");
                $stmt->execute([$myEmployeeId]);
            }
            echo json_encode(['success' => true, 'leaves' => $stmt->fetchAll()]);
            break;

        // ============================================
        // Employee leave history + remaining credits
        // ============================================
        case 'employee_history':
            if (!in_array($role, ['admin', 'hr'], true)) {
                echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
                break;
            }

            $employeeId = (int) ($_POST['employee_id'] ?? $_GET['employee_id'] ?? 0);
            if ($employeeId <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid employee ID.']);
                break;
            }

            $stmt = $pdo->prepare("
                SELECT id, first_name, middle_name, last_name, email,
                       category, sub_category, year_level, position,
                       employment_type, basic_salary, leave_credits
                FROM employees WHERE id = ? LIMIT 1
            ");
            $stmt->execute([$employeeId]);
            $employee = $stmt->fetch();

            if (!$employee) {
                echo json_encode(['success' => false, 'error' => 'Employee not found.']);
                break;
            }

            $totalCredits = (float) $employee['leave_credits'];

            $stmt = $pdo->prepare("
                SELECT lr.*, u.username AS approver_username
                FROM leave_requests lr
                LEFT JOIN users u ON lr.approved_by = u.id
                WHERE lr.employee_id = ?
                ORDER BY lr.created_at DESC
            ");
            $stmt->execute([$employeeId]);
            $history = $stmt->fetchAll();

            $stmt = $pdo->prepare("
                SELECT status, leave_type,
                       COUNT(*) AS count,
                       COALESCE(SUM(days_count), 0) AS days
                FROM leave_requests
                WHERE employee_id = ?
                GROUP BY status, leave_type
            ");
            $stmt->execute([$employeeId]);
            $usageRows = $stmt->fetchAll();

            $usage = [
                'approved' => ['total_days' => 0, 'by_type' => []],
                'pending' => ['total_days' => 0, 'by_type' => []],
                'rejected' => ['total_days' => 0, 'by_type' => []],
                'cancelled' => ['total_days' => 0, 'by_type' => []],
            ];
            foreach ($usageRows as $r) {
                $s = $r['status'];
                if (!isset($usage[$s]))
                    continue;
                $usage[$s]['total_days'] += (float) $r['days'];
                $usage[$s]['by_type'][$r['leave_type']] = (float) $r['days'];
            }

            $allTypes = ['vacation', 'sick', 'maternity', 'paternity', 'terminal', 'other'];
            $remainingByType = [];
            foreach ($allTypes as $t) {
                $cap = $TYPE_CAPS[$t] ?? null;
                $used = $usage['approved']['by_type'][$t] ?? 0;

                $remainingByType[$t] = [
                    'cap' => $cap,
                    'used' => $used,
                    'remaining' => $cap !== null ? max(0, $cap - $used) : null,
                ];
            }

            $totalUsedApproved = $usage['approved']['total_days'];
            $totalRemainingOverall = max(0, $totalCredits - $totalUsedApproved);

            echo json_encode([
                'success' => true,
                'employee' => [
                    'id' => (int) $employee['id'],
                    'first_name' => $employee['first_name'],
                    'middle_name' => $employee['middle_name'],
                    'last_name' => $employee['last_name'],
                    'full_name' => trim(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? '')),
                    'email' => $employee['email'],
                    'category' => $employee['category'],
                    'sub_category' => $employee['sub_category'],
                    'year_level' => $employee['year_level'],
                    'position' => $employee['position'],
                    'employment_type' => $employee['employment_type'],
                    'basic_salary' => (float) $employee['basic_salary'],
                    'leave_credits' => $totalCredits,
                ],
                'credits' => [
                    'total' => $totalCredits,
                    'used' => $totalUsedApproved,
                    'remaining' => $totalRemainingOverall,
                    'by_type' => $remainingByType,
                ],
                'usage' => $usage,
                'history' => array_map(function ($l) {
                    return [
                        'id' => (int) $l['id'],
                        'type' => $l['leave_type'],
                        'start' => $l['start_date'],
                        'end' => $l['end_date'],
                        'days' => (float) $l['days_count'],
                        'reason' => $l['reason'],
                        'status' => $l['status'],
                        'pay_status' => $l['pay_status'] ?? null,
                        'remarks' => $l['remarks'] ?? '',
                        'created' => $l['created_at'],
                        'approver' => $l['approver_username'] ?? '',
                        'approved' => $l['approved_at'] ?? '',
                        'attachment' => !empty($l['attachment_path']) ? [
                            'path' => $l['attachment_path'],
                            'name' => $l['attachment_name'],
                            'size' => (int) ($l['attachment_size'] ?? 0),
                            'mime' => $l['attachment_mime'],
                        ] : null,
                    ];
                }, $history),
            ]);
            break;

        // ============================================
        // Create leave (faculty files for self) — with attachment
        // ============================================
        case 'create':
            if ($myEmployeeId === null) {
                echo json_encode(['success' => false, 'error' => 'Your account has no employee record.']);
                break;
            }

            $stmt = $pdo->prepare("SELECT employment_type FROM employees WHERE id = ? LIMIT 1");
            $stmt->execute([$myEmployeeId]);
            $empType = $stmt->fetchColumn();

            if ($empType === 'part-time') {
                echo json_encode([
                    'success' => false,
                    'error' => 'Part-time employees are not eligible to file leave requests.'
                ]);
                break;
            }

            $type = $_POST['leave_type'] ?? '';
            $start = $_POST['start_date'] ?? '';
            $end = $_POST['end_date'] ?? '';
            $reason = trim($_POST['reason'] ?? '');

            // Check if the leave type exists in the database
            $stmtCheck = $pdo->prepare("SELECT id FROM leave_types WHERE LOWER(name) = LOWER(?) AND is_active = 1 LIMIT 1");
            $stmtCheck->execute([$type]);
            if (!$stmtCheck->fetch()) {
                echo json_encode(['success' => false, 'error' => 'Invalid or archived leave type.']);
                break;
            }
            if (!$start || !$end || !$reason) {
                echo json_encode(['success' => false, 'error' => 'Please fill in all required fields.']);
                break;
            }

            $s = new DateTime($start);
            $e = new DateTime($end);
            $today = new DateTime('today');

            if ($s < $today && $type !== 'sick') {
                echo json_encode(['success' => false, 'error' => 'Only sick leaves can be filed with a past start date.']);
                break;
            }
            if ($e < $s) {
                echo json_encode(['success' => false, 'error' => 'End date cannot be before start date.']);
                break;
            }

            $isHalfDay = !empty($_POST['is_half_day']) && ($start === $end);

            $days = $isHalfDay
                ? 0.5
                : ($s->diff($e)->days + 1);

            $stmt = $pdo->prepare("SELECT leave_credits FROM employees WHERE id = ? LIMIT 1");
            $stmt->execute([$myEmployeeId]);
            $totalCredits = (float) $stmt->fetchColumn();

            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(days_count), 0) 
                FROM leave_requests 
                WHERE employee_id = ? AND status = 'approved'
            ");
            $stmt->execute([$myEmployeeId]);
            $used = (float) $stmt->fetchColumn();

            $remaining = max(0, $totalCredits - $used);
            if ($days > $remaining) {
                echo json_encode([
                    'success' => false,
                    'error' => "Not enough credits. You have " . fmt_days($remaining) . " day(s) remaining."
                ]);
                break;
            }

            if (isset($TYPE_CAPS[$type])) {
                $stmt = $pdo->prepare("
                    SELECT COALESCE(SUM(days_count), 0) 
                    FROM leave_requests 
                    WHERE employee_id = ? 
                      AND status = 'approved' 
                      AND leave_type = ?
                ");
                $stmt->execute([$myEmployeeId, $type]);
                $usedOfType = (float) $stmt->fetchColumn();

                $typeRemaining = max(0, $TYPE_CAPS[$type] - $usedOfType);

                if ($days > $typeRemaining) {
                    $typeLabel = ucfirst($type);
                    echo json_encode([
                        'success' => false,
                        'error' => "{$typeLabel} leave is capped at "
                            . fmt_days($TYPE_CAPS[$type])
                            . " day(s). You have "
                            . fmt_days($typeRemaining)
                            . " day(s) remaining for this type."
                    ]);
                    break;
                }
            }

            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM leave_requests
                WHERE employee_id = ?
                  AND status IN ('pending', 'approved')
                  AND NOT (end_date < ? OR start_date > ?)
            ");
            $stmt->execute([$myEmployeeId, $start, $end]);
            if ((int) $stmt->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'error' => 'You already have a leave request overlapping these dates.']);
                break;
            }

            // ============================================
            // Handle optional attachment upload
            // ============================================
            $attachmentPath = null;
            $attachmentName = null;
            $attachmentSize = null;
            $attachmentMime = null;

            if (!empty($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
                $file = $_FILES['attachment'];

                if ($file['error'] !== UPLOAD_ERR_OK) {
                    echo json_encode(['success' => false, 'error' => 'File upload failed. Please try again.']);
                    break;
                }

                $maxSize = 5 * 1024 * 1024;
                if ($file['size'] > $maxSize) {
                    echo json_encode(['success' => false, 'error' => 'File is too large. Maximum size is 5 MB.']);
                    break;
                }

                $allowedMimes = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                    'application/pdf' => 'pdf',
                ];

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $detectedMime = $finfo->file($file['tmp_name']);

                if (!isset($allowedMimes[$detectedMime])) {
                    echo json_encode(['success' => false, 'error' => 'Invalid file type. Allowed: JPG, PNG, WEBP, PDF.']);
                    break;
                }

                $ext = $allowedMimes[$detectedMime];

                $uploadDir = __DIR__ . '/../../public_html/uploads/leaves/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
                $safeBase = substr($safeBase ?: 'file', 0, 60);
                $filename = date('Ymd_His') . '_' . $myEmployeeId . '_' . bin2hex(random_bytes(4)) . '_' . $safeBase . '.' . $ext;

                $dest = $uploadDir . $filename;

                if (!move_uploaded_file($file['tmp_name'], $dest)) {
                    echo json_encode(['success' => false, 'error' => 'Could not save the uploaded file.']);
                    break;
                }

                $attachmentPath = 'uploads/leaves/' . $filename;
                $attachmentName = substr($file['name'], 0, 255);
                $attachmentSize = (int) $file['size'];
                $attachmentMime = $detectedMime;
            }

            // Insert leave with attachment metadata
            $stmt = $pdo->prepare("
                INSERT INTO leave_requests 
                    (employee_id, leave_type, start_date, end_date, days_count, reason, status,
                     attachment_path, attachment_name, attachment_size, attachment_mime)
                VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?)
            ");
            $stmt->execute([
                $myEmployeeId,
                $type,
                $start,
                $end,
                $days,
                $reason,
                $attachmentPath,
                $attachmentName,
                $attachmentSize,
                $attachmentMime
            ]);
            $newId = (int) $pdo->lastInsertId();

            if (class_exists('ActivityLogger')) {
                ActivityLogger::log(
                    $pdo,
                    'file_leave',
                    "Filed {$type} leave (" . fmt_days($days) . " day(s))",
                    'leave_request',
                    $newId
                );
            }

            if (class_exists('NotificationService')) {
                $facultyName = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
                if ($facultyName === '')
                    $facultyName = $_SESSION['username'] ?? 'An employee';

                $typeLabel = ucfirst($type);
                $range = date('M j', strtotime($start))
                    . ($start !== $end ? ' – ' . date('M j, Y', strtotime($end)) : '');
                $halfLabel = $isHalfDay ? ' — half day' : '';
                $attachLabel = $attachmentPath ? ' · 📎 attachment' : '';

                $message = "{$facultyName} filed {$typeLabel} leave ({$range}, " . fmt_days($days) . " day(s){$halfLabel}{$attachLabel})";

                // 1. Notify the Academic Dean for endorsement
                $stmtDeans = $pdo->prepare("SELECT u.id FROM users u JOIN employees e ON u.employee_id = e.id WHERE e.position = 'Academic Dean' AND u.role = 'faculty' AND u.status = 'active'");
                $stmtDeans->execute();
                while ($dean = $stmtDeans->fetch()) {
                    NotificationService::notify(
                        $pdo,
                        $dean['id'],
                        'leave_filed',
                        "New leave request for endorsement",
                        $message,
                        '/leave-monitoring'
                    );
                }

                // 2. Notify HR (so they know it's coming)
                $stmtHRs = $pdo->prepare("SELECT id FROM users WHERE role = 'hr' AND status = 'active'");
                $stmtHRs->execute();
                while ($hr = $stmtHRs->fetch()) {
                    NotificationService::notify(
                        $pdo,
                        $hr['id'],
                        'leave_filed',
                        "New leave request (pending Dean)",
                        $message,
                        '/hr/leaves'
                    );
                }
            }

            echo json_encode(['success' => true, 'message' => 'Leave filed.']);
            break;

        // ============================================
        // Cancel leave
        // ============================================
        case 'cancel':
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid request ID.']);
                break;
            }

            $stmt = $pdo->prepare("SELECT * FROM leave_requests WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $leave = $stmt->fetch();

            if (!$leave) {
                echo json_encode(['success' => false, 'error' => 'Leave request not found.']);
                break;
            }

            if ($role !== 'admin' && $role !== 'hr') {
                if ($myEmployeeId === null || (int) $leave['employee_id'] !== $myEmployeeId) {
                    echo json_encode(['success' => false, 'error' => 'You can only cancel your own requests.']);
                    break;
                }
                if ($leave['status'] !== 'pending') {
                    echo json_encode(['success' => false, 'error' => 'Only pending requests can be cancelled.']);
                    break;
                }
            }

            $stmt = $pdo->prepare("UPDATE leave_requests SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$id]);

            if (class_exists('ActivityLogger')) {
                ActivityLogger::log($pdo, 'cancel_leave', "Cancelled leave request (ID: {$id})", 'leave_request', $id);
            }

            if (class_exists('NotificationService')) {
                $facultyName = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
                if ($facultyName === '')
                    $facultyName = $_SESSION['username'] ?? 'A faculty member';

                NotificationService::notifyAdmins(
                    $pdo,
                    'leave_cancelled',
                    "Leave request cancelled",
                    "{$facultyName} cancelled a {$leave['leave_type']} leave request",
                    '/leaves'
                );
            }

            echo json_encode(['success' => true, 'message' => 'Leave cancelled.']);
            break;

        // ============================================
        // Dean Endorses Leave
        // ============================================
        case 'endorse_dean':
            if (($_SESSION['position'] ?? '') !== 'Academic Dean') {
                echo json_encode(['success' => false, 'error' => 'Only the Academic Dean can endorse requests.']);
                break;
            }

            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid request ID.']);
                break;
            }

            $stmt = $pdo->prepare("SELECT status, dean_approved_at FROM leave_requests WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $leave = $stmt->fetch();

            if (!$leave) {
                echo json_encode(['success' => false, 'error' => 'Leave request not found.']);
                break;
            }

            if ($leave['status'] !== 'pending') {
                echo json_encode(['success' => false, 'error' => 'Only pending requests can be endorsed.']);
                break;
            }

            if ($leave['dean_approved_at'] !== null) {
                echo json_encode(['success' => false, 'error' => 'This request has already been endorsed.']);
                break;
            }

            $stmt = $pdo->prepare("
                UPDATE leave_requests 
                SET dean_approved_by = ?, dean_approved_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$userId, $id]);

            if (class_exists('ActivityLogger')) {
                ActivityLogger::log($pdo, 'endorse_leave', "Endorsed leave request #{$id} to HR", 'leave_request', $id);
            }

            if (class_exists('NotificationService')) {
                NotificationService::notifyAdmins(
                    $pdo,
                    'leave_endorsed',
                    "Leave endorsed",
                    "A leave request (#{$id}) has been endorsed and is ready for HR review.",
                    '/leaves'
                );
            }

            echo json_encode(['success' => true, 'message' => 'Leave request endorsed.']);
            break;

        // ============================================
        // Approve / Reject (admin/hr only)
        // ============================================
        case 'decide':
            if (!in_array($role, ['admin', 'hr'], true)) {
                echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
                break;
            }

            $id = (int) ($_POST['id'] ?? 0);
            $decision = $_POST['decision'] ?? '';
            $remarks = trim($_POST['remarks'] ?? '');
            $payStatus = $_POST['pay_status'] ?? '';

            if ($id <= 0 || !in_array($decision, ['approved', 'rejected'], true)) {
                echo json_encode(['success' => false, 'error' => 'Invalid input.']);
                break;
            }

            // NEW: Require remarks for rejection
            if ($decision === 'rejected' && empty($remarks)) {
                echo json_encode(['success' => false, 'error' => 'You must provide a reason/remarks when rejecting a leave request.']);
                break;
            }

            if ($decision === 'approved') {
                if (!in_array($payStatus, ['with_pay', 'without_pay'], true)) {
                    echo json_encode(['success' => false, 'error' => 'Please select a pay status (with pay or without pay).']);
                    break;
                }
            } else {
                $payStatus = null;
            }

            $stmt = $pdo->prepare("
                SELECT lr.*, e.first_name, e.last_name
                FROM leave_requests lr
                LEFT JOIN employees e ON lr.employee_id = e.id
                WHERE lr.id = ? LIMIT 1
            ");
            $stmt->execute([$id]);
            $leave = $stmt->fetch();

            if (!$leave) {
                echo json_encode(['success' => false, 'error' => 'Leave request not found.']);
                break;
            }

            if ($leave['status'] !== 'pending') {
                echo json_encode(['success' => false, 'error' => 'This request has already been decided.']);
                break;
            }

            if ($leave['dean_approved_at'] === null) {
                echo json_encode(['success' => false, 'error' => 'This request must be endorsed by the Academic Dean before HR can decide on it.']);
                break;
            }

            $stmt = $pdo->prepare("
                UPDATE leave_requests 
                SET status = ?, approved_by = ?, approved_at = NOW(), 
                    remarks = ?, pay_status = ?
                WHERE id = ?
            ");
            $stmt->execute([$decision, $userId, $remarks, $payStatus, $id]);

            if (class_exists('ActivityLogger')) {
                ActivityLogger::log($pdo, 'decide_leave', "Leave #{$id} {$decision}", 'leave_request', $id);
            }

            if (class_exists('NotificationService')) {
                $employeeId = (int) $leave['employee_id'];
                $typeLabel = ucfirst($leave['leave_type']);

                $range = date('M j', strtotime($leave['start_date']))
                    . ($leave['start_date'] !== $leave['end_date']
                        ? ' – ' . date('M j, Y', strtotime($leave['end_date']))
                        : '');

                $title = $decision === 'approved'
                    ? "Your {$typeLabel} leave was approved"
                    : "Your {$typeLabel} leave was rejected";

                $message = "{$range} · " . fmt_days($leave['days_count']) . " day(s)";

                if ($decision === 'approved' && $payStatus) {
                    $message .= ' · ' . ($payStatus === 'with_pay' ? 'With pay' : 'Without pay');
                }
                if ($remarks !== '') {
                    $message .= ' — "' . $remarks . '"';
                }

                NotificationService::notifyEmployee(
                    $pdo,
                    $employeeId,
                    $decision === 'approved' ? 'leave_approved' : 'leave_rejected',
                    $title,
                    $message,
                    '/faculty/leaves'
                );
            }

            if (class_exists('NotificationService')) {
                $adminName = $_SESSION['username'] ?? 'An admin';
                NotificationService::notifyAdmins(
                    $pdo,
                    'leave_decided',
                    "Leave {$decision}",
                    "{$adminName} {$decision} a leave request (#{$id})",
                    '/leaves'
                );
            }

            echo json_encode(['success' => true, 'message' => 'Leave ' . $decision . '.']);
            break;

        case 'undo_decision':
            if (!in_array($role, ['admin', 'hr'], true)) {
                echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
                break;
            }
            $id = (int) ($_POST['id'] ?? 0);

            $stmt = $pdo->prepare("SELECT status FROM leave_requests WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $leave = $stmt->fetch();

            if (!$leave || $leave['status'] === 'pending') {
                echo json_encode(['success' => false, 'error' => 'Invalid request or already pending.']);
                break;
            }

            $pdo->prepare("UPDATE leave_requests SET status = 'pending', approved_by = NULL, approved_at = NULL, remarks = NULL, pay_status = NULL WHERE id = ?")->execute([$id]);

            // Optional: Add ActivityLog here
            echo json_encode(['success' => true, 'message' => 'Leave reverted to pending.']);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}