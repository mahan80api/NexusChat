<?php
/**
 * AuditLog — security and significant user events
 */
declare(strict_types=1);

class AuditLog
{
    public static function record(string $event, ?int $userId, array $meta = []): void
    {
        try {
            Database::getInstance()->query(
                "INSERT INTO audit_log (event, user_id, meta, ip, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())",
                [
                    $event,
                    $userId,
                    $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
                    $_SERVER['REMOTE_ADDR']     ?? null,
                    $_SERVER['HTTP_USER_AGENT'] ?? null,
                ]
            );
        } catch (Throwable $e) {
            error_log('[audit] ' . $e->getMessage());
        }
    }

    public static function recent(int $limit = 100, ?int $userId = null): array
    {
        $sql = "SELECT * FROM audit_log";
        $args = [];
        if ($userId) { $sql .= " WHERE user_id = ?"; $args[] = $userId; }
        $sql .= " ORDER BY id DESC LIMIT ?";
        $args[] = $limit;
        return Database::getInstance()->fetchAll($sql, $args);
    }
}
