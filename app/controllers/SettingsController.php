<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../helpers/ActivityLogger.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    switch ($action) {

        // Save a whole category of settings
        case 'save':
            $category = $_POST['category'] ?? '';

            if (empty($category)) {
                echo json_encode(['success' => false, 'error' => 'Missing category.']);
                break;
            }

            // Expected: $_POST['settings'] = [ 'key' => 'value', ... ]
            $settings = $_POST['settings'] ?? [];

            if (empty($settings)) {
                echo json_encode(['success' => false, 'error' => 'No settings to save.']);
                break;
            }

            $pdo->beginTransaction();

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO settings (setting_key, setting_value, category)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                ");

                foreach ($settings as $key => $value) {
                    $stmt->execute([$key, $value, $category]);
                }

                $pdo->commit();

                ActivityLogger::log(
                    $pdo,
                    'update_settings',
                    "Updated {$category} settings",
                    'settings',
                    null
                );

                echo json_encode(['success' => true, 'message' => 'Settings saved successfully.']);
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;

        // Get a single setting
        case 'get':
            $key = $_POST['key'] ?? '';
            if (empty($key)) {
                echo json_encode(['success' => false]);
                break;
            }
            $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            echo json_encode(['success' => true, 'value' => $stmt->fetchColumn()]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}