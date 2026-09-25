<?php

class NotificationService
{
    public static function notify(
        PDO $pdo,
        int $userId,
        string $type,
        string $title,
        string $message = '',
        string $link = ''
    ): void {
        try {
            $actorId = $_SESSION['user_id'] ?? null;
            $actorUsername = $_SESSION['username'] ?? null;

            $stmt = $pdo->prepare("
                INSERT INTO notifications 
                    (user_id, actor_id, actor_username, type, title, message, link)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $actorId, $actorUsername, $type, $title, $message, $link]);
        } catch (Exception $e) {
            error_log('Notification failed: ' . $e->getMessage());
        }
    }

    public static function notifyUser(
        PDO $pdo,
        int $userId,
        string $type,
        string $title,
        string $message = '',
        string $link = ''
    ): void {
        self::notify($pdo, $userId, $type, $title, $message, $link);
    }

    public static function notifyAdmins(
        PDO $pdo,
        string $type,
        string $title,
        string $message = '',
        string $link = ''
    ): void {
        try {
            $actorId = $_SESSION['user_id'] ?? null;

            $stmt = $pdo->prepare("
                SELECT id FROM users 
                WHERE role IN ('admin', 'hr') 
                AND status = 'active'
                AND id != ?
            ");
            $stmt->execute([$actorId]);
            $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($admins as $adminId) {
                self::notify($pdo, (int) $adminId, $type, $title, $message, $link);
            }
        } catch (Exception $e) {
            error_log('Notify admins failed: ' . $e->getMessage());
        }
    }

    public static function notifyEmployee(
        PDO $pdo,
        int $employeeId,
        string $type,
        string $title,
        string $message = '',
        string $link = ''
    ): bool {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE employee_id = ? LIMIT 1");
            $stmt->execute([$employeeId]);
            $userId = $stmt->fetchColumn();

            if ($userId === false) {
                return false;
            }

            self::notify($pdo, (int) $userId, $type, $title, $message, $link);
            return true;
        } catch (Exception $e) {
            error_log('Notify employee failed: ' . $e->getMessage());
            return false;
        }
    }
}