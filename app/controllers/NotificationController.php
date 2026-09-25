<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$userId = (int) $_SESSION['user_id'];

try {
    switch ($action) {

        // Fetch unread + recent notifications
        case 'list':
            // Unread count
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$userId]);
            $unreadCount = (int) $stmt->fetchColumn();

            // Latest 10
            $stmt = $pdo->prepare("
                SELECT id, type, title, message, link, is_read, created_at
                FROM notifications
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT 10
            ");
            $stmt->execute([$userId]);
            $items = $stmt->fetchAll();

            // Format time ago
            foreach ($items as &$n) {
                $diff = time() - strtotime($n['created_at']);
                if ($diff < 60)         $n['time_ago'] = 'Just now';
                elseif ($diff < 3600)   $n['time_ago'] = floor($diff / 60) . 'm ago';
                elseif ($diff < 86400)  $n['time_ago'] = floor($diff / 3600) . 'h ago';
                elseif ($diff < 604800) $n['time_ago'] = floor($diff / 86400) . 'd ago';
                else                    $n['time_ago'] = date('M j, Y', strtotime($n['created_at']));
                $n['is_read'] = (bool) $n['is_read'];
            }

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'unread' => $unreadCount,
                'items' => $items,
            ]);
            break;

        // Mark single as read
        case 'read':
            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false]);
                break;
            }
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $userId]);

            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            break;

        // Mark all as read
        case 'read_all':
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
            $stmt->execute([$userId]);

            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            break;

        default:
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }

} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}