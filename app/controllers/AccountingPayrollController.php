<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../helpers/ActivityLogger.php';

header('Content-Type: application/json');

// Ensure user is logged in and is an Accounting Staff
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

// Verify Accounting Section
$stmt = $pdo->prepare("
    SELECT e.sub_category FROM users u
    LEFT JOIN employees e ON u.employee_id = e.id
    WHERE u.id = ? LIMIT 1
");
$stmt->execute([$userId]);
if (($stmt->fetchColumn() ?: '') !== 'Accounting Section') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized department']);
    exit;
}

try {
    // ============================================
    // UPDATE SALARY
    // ============================================
    if ($action === 'update_salary') {
        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        $salary = (float) ($_POST['basic_salary'] ?? 0);

        if ($employeeId <= 0 || $salary < 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid input.']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE employees SET basic_salary = ? WHERE id = ?");
        $stmt->execute([$salary, $employeeId]);

        if (class_exists('ActivityLogger')) {
            ActivityLogger::log($pdo, 'update_salary', "Updated monthly salary to ₱" . number_format($salary, 2), 'employee', $employeeId);
        }

        echo json_encode(['success' => true, 'message' => 'Salary updated.']);
        exit;
    }

    // ============================================
    // SETTLE PAYROLL
    // ============================================
    if ($action === 'settle_payroll') {
        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $amount = (float) ($_POST['amount'] ?? 0);
        $remarks = trim($_POST['remarks'] ?? '');

        if ($employeeId <= 0 || !in_array($status, ['settled', 'outstanding'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid input.']);
            exit;
        }

        // Check if a record already exists
        $stmt = $pdo->prepare("SELECT id FROM payroll_settlements WHERE employee_id = ? LIMIT 1");
        $stmt->execute([$employeeId]);
        $settlementId = $stmt->fetchColumn();

        if ($settlementId) {
            $stmt = $pdo->prepare("
                UPDATE payroll_settlements 
                SET status = ?, amount = ?, remarks = ?, settled_at = IF(? = 'settled', NOW(), NULL)
                WHERE id = ?
            ");
            $stmt->execute([$status, $amount, $remarks, $status, $settlementId]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO payroll_settlements (employee_id, status, amount, remarks, settled_at)
                VALUES (?, ?, ?, ?, IF(? = 'settled', NOW(), NULL))
            ");
            $stmt->execute([$employeeId, $status, $amount, $remarks, $status]);
        }

        if (class_exists('ActivityLogger')) {
            ActivityLogger::log($pdo, 'settle_payroll', "Marked payroll as {$status} (₱" . number_format($amount, 2) . ")", 'employee', $employeeId);
        }

        echo json_encode(['success' => true, 'message' => 'Payroll updated.']);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action.']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}