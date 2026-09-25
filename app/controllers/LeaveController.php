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
$role   = $_SESSION['role'] ?? '';

// Resolve employee_id for the current user
$stmt = $pdo->prepare("SELECT employee_id FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$userId]);
$row = $stmt->fetch();
$myEmployeeId = $row['employee_id'] !== null ? (int) $row['employee_id'] : null;

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
        // Create leave (faculty files for self)
        // ============================================
        case 'create':
            if ($myEmployeeId === null) {
                echo json_encode(['success' => false, 'error' => 'Your account has no employee record.']);
                break;
            }

            // Part-time employees cannot file leave
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

            $type   = $_POST['leave_type'] ?? '';
            $start  = $_POST['start_date'] ?? '';
            $end    = $_POST['end_date'] ?? '';
            $reason = trim($_POST['reason'] ?? '');

            $allowedTypes = ['vacation', 'sick', 'maternity', 'paternity', 'terminal', 'other'];
            if (!in_array($type, $allowedTypes, true)) {
                echo json_encode(['success' => false, 'error' => 'Invalid leave type.']);
                break;
            }
            if (!$start || !$end || !$reason) {
                echo json_encode(['success' => false, 'error' => 'Please fill in all required fields.']);
                break;
            }

            $s = new DateTime($start);
            $e = new DateTime($end);
            $today = new DateTime('today');

            if ($s < $today) {
                echo json_encode(['success' => false, 'error' => 'Leave start date cannot be in the past.']);
                break;
            }
            if ($e < $s) {
                echo json_encode(['success' => false, 'error' => 'End date cannot be before start date.']);
                break;
            }

            $days = $s->diff($e)->days + 1;

            // ---- Total credit pool check ----
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
                    'error' => "Not enough credits. You have {$remaining} day(s) remaining."
                ]);
                break;
            }

            // ---- Per-type caps ----
            $typeCaps = [
                'vacation' => 7.5,
                'sick'     => 7.5,
            ];

            if (isset($typeCaps[$type])) {
                $stmt = $pdo->prepare("
                    SELECT COALESCE(SUM(days_count), 0) 
                    FROM leave_requests 
                    WHERE employee_id = ? 
                      AND status = 'approved' 
                      AND leave_type = ?
                ");
                $stmt->execute([$myEmployeeId, $type]);
                $usedOfType = (float) $stmt->fetchColumn();

                $typeRemaining = max(0, $typeCaps[$type] - $usedOfType);

                if ($days > $typeRemaining) {
                    $typeLabel = ucfirst($type);
                    echo json_encode([
                        'success' => false,
                        'error' => "{$typeLabel} leave is capped at {$typeCaps[$type]} day(s). You have {$typeRemaining} day(s) remaining for this type."
                    ]);
                    break;
                }
            }

            // ---- Overlap check ----
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM leave_requests
                WHERE employee_id = ?
                  AND status IN ('pending', 'approved')
                  AND NOT (end_date < ? OR start_date > ?)
            ");
            $stmt->execute([$myEmployeeId, $start, $end]);
            if ((int)$stmt->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'error' => 'You already have a leave request overlapping these dates.']);
                break;
            }

            $stmt = $pdo->prepare("
                INSERT INTO leave_requests 
                    (employee_id, leave_type, start_date, end_date, days_count, reason, status)
                VALUES (?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$myEmployeeId, $type, $start, $end, $days, $reason]);
            $newId = (int) $pdo->lastInsertId();

            if (class_exists('ActivityLogger')) {
                ActivityLogger::log($pdo, 'file_leave', "Filed {$type} leave ({$days} day(s))", 'leave_request', $newId);
            }

            if (class_exists('NotificationService')) {
                $facultyName = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
                if ($facultyName === '') $facultyName = $_SESSION['username'] ?? 'A faculty member';

                $typeLabel = ucfirst($type);
                $range = date('M j', strtotime($start))
                       . ($start !== $end ? ' – ' . date('M j, Y', strtotime($end)) : '');

                NotificationService::notifyAdmins(
                    $pdo,
                    'leave_filed',
                    "New leave request",
                    "{$facultyName} filed {$typeLabel} leave ({$range}, {$days} day(s))",
                    '/leaves'
                );
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
                if ($myEmployeeId === null || (int)$leave['employee_id'] !== $myEmployeeId) {
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
                if ($facultyName === '') $facultyName = $_SESSION['username'] ?? 'A faculty member';

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
        // Approve / Reject (admin/hr only)
        // ============================================
        case 'decide':
            if (!in_array($role, ['admin', 'hr'], true)) {
                echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
                break;
            }

            $id       = (int) ($_POST['id'] ?? 0);
            $decision = $_POST['decision'] ?? '';
            $remarks  = trim($_POST['remarks'] ?? '');

            if ($id <= 0 || !in_array($decision, ['approved', 'rejected'], true)) {
                echo json_encode(['success' => false, 'error' => 'Invalid input.']);
                break;
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

            $stmt = $pdo->prepare("
                UPDATE leave_requests 
                SET status = ?, approved_by = ?, approved_at = NOW(), remarks = ?
                WHERE id = ?
            ");
            $stmt->execute([$decision, $userId, $remarks, $id]);

            if (class_exists('ActivityLogger')) {
                ActivityLogger::log($pdo, 'decide_leave', "Leave #{$id} {$decision}", 'leave_request', $id);
            }

            if (class_exists('NotificationService')) {
                $employeeId = (int) $leave['employee_id'];
                $typeLabel  = ucfirst($leave['leave_type']);

                $range = date('M j', strtotime($leave['start_date']))
                       . ($leave['start_date'] !== $leave['end_date']
                            ? ' – ' . date('M j, Y', strtotime($leave['end_date']))
                            : '');

                $title = $decision === 'approved'
                    ? "Your {$typeLabel} leave was approved"
                    : "Your {$typeLabel} leave was rejected";

                $message = "{$range} · {$leave['days_count']} day(s)";
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

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}