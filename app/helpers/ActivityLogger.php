<?php

class ActivityLogger
{
    /**
     * Log an activity to the activity_logs table.
     *
     * @param PDO         $pdo         Database connection
     * @param string      $action      Short action key (e.g. "create_user")
     * @param string      $description Human-readable description
     * @param string|null $targetType  Optional: "user", "leave", "payroll", etc.
     * @param int|null    $targetId    Optional: ID of the affected record
     */
    public static function log(
        PDO $pdo,
        string $action,
        string $description,
        ?string $targetType = null,
        ?int $targetId = null
    ): void {
        try {
            $userId   = $_SESSION['user_id']  ?? null;
            $username = $_SESSION['username'] ?? null;
            $ip       = $_SERVER['REMOTE_ADDR'] ?? null;
            $ua       = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

            $stmt = $pdo->prepare("
                INSERT INTO activity_logs 
                    (user_id, username, action, target_type, target_id, description, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $username, $action, $targetType, $targetId, $description, $ip, $ua]);
        } catch (Exception $e) {
            error_log('Activity log failed: ' . $e->getMessage());
        }
    }
}