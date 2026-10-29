<?php
// app/controllers/HrOptionsController.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hr') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit;
}

$action = $_POST['action'] ?? '';

try {
    if ($action === 'add_leave_type') {
        $name = trim($_POST['name'] ?? '');
        if ($name) {
            // Check if it already exists
            $check = $pdo->prepare("SELECT id FROM leave_types WHERE name = ?");
            $check->execute([$name]);
            if ($check->fetch()) {
                echo json_encode(['success' => false, 'error' => 'This leave type already exists.']); exit;
            }
            $pdo->prepare("INSERT INTO leave_types (name, is_active) VALUES (?, 1)")->execute([$name]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Name required']);
        }
    } 
    elseif ($action === 'toggle_leave_type_status') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE leave_types SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
    }
    elseif ($action === 'add_position') {
        $title = trim($_POST['title'] ?? '');
        $cat = $_POST['category'] ?? 'staff';
        if ($title) {
            $check = $pdo->prepare("SELECT id FROM positions WHERE title = ?");
            $check->execute([$title]);
            if ($check->fetch()) {
                echo json_encode(['success' => false, 'error' => 'This position already exists.']); exit;
            }
            $pdo->prepare("INSERT INTO positions (title, category, is_active) VALUES (?, ?, 1)")->execute([$title, $cat]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Title required']);
        }
    }
    elseif ($action === 'toggle_position_status') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE positions SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}