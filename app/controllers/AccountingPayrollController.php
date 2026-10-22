<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../helpers/ActivityLogger.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');
$userId = (int) $_SESSION['user_id'];

// Verify accounting staff
$stmt = $pdo->prepare("
    SELECT e.sub_category
    FROM users u
    LEFT JOIN employees e ON u.employee_id = e.id
    WHERE u.id = ?
    LIMIT 1
");
$stmt->execute([$userId]);
if (($stmt->fetchColumn() ?: '') !== 'Accounting Section') {
    http_response_code(403);
    echo json_encode(['error' => 'Not authorized. Accounting staff only.']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    if ($action === 'settle_payroll') {
        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $amount = (float) ($_POST['amount'] ?? 0);
        $remarks = trim($_POST['remarks'] ?? '');

        if ($employeeId <= 0 || !in_array($status, ['settled', 'outstanding'], true) || $amount < 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid input.']);
            exit;
        }

        // Verify employee is faculty or staff
        $stmt = $pdo->prepare("SELECT id FROM employees WHERE id = ? AND category IN ('faculty', 'staff') LIMIT 1");
        $stmt->execute([$employeeId]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Employee not found.']);
            exit;
        }

        // One row per employee — upsert
        $stmt = $pdo->prepare("
            INSERT INTO payroll_settlements 
                (employee_id, status, amount, remarks, settled_at, updated_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                amount = VALUES(amount),
                remarks = VALUES(remarks),
                settled_at = VALUES(settled_at),
                updated_by = VALUES(updated_by),
                updated_at = NOW()
        ");
        $stmt->execute([
            $employeeId,
            $status,
            $amount,
            $remarks,
            $status === 'settled' ? date('Y-m-d H:i:s') : null,
            $userId,
        ]);

        try {
            ActivityLogger::log(
                $pdo,
                'settle_payroll',
                "Marked payroll as {$status} for employee #{$employeeId} (₱" . number_format($amount, 2) . ")",
                'payroll',
                $employeeId
            );
        } catch (Throwable $e) {
            error_log($e->getMessage());
        }

        echo json_encode(['success' => true, 'message' => 'Settlement saved.']);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action.']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}