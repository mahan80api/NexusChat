<?php
/**
 * NexusChat - Push Notification Manager
 * Handles subscriptions, preferences, and dispatching
 */
require_once __DIR__ . '/../vendor/autoload.php'; // Minishlink\WebPush

class PushManager {
    private $db;
    private $webPush;
    private $vapidPublic;
    private $vapidPrivate;
    private $vapidSubject;

    public function __construct() {
        $this->db = Database::getInstance();
        $config = require __DIR__ . '/../config/vapid.php';
        $this->vapidPublic  = $config['publicKey'];
        $this->vapidPrivate = $config['privateKey'];
        $this->vapidSubject = $config['subject'];

        if (class_exists('Minishlink\\WebPush\\WebPush')) {
            $auth = [
                'VAPID' => [
                    'subject'    => $this->vapidSubject,
                    'publicKey'  => $this->vapidPublic,
                    'privateKey' => $this->vapidPrivate,
                ],
            ];
            $this->webPush = new \Minishlink\WebPush\WebPush($auth);
            $this->webPush->setReuseVAPIDHeaders(true);
            $this->webPush->setDefaultOptions([
                'TTL' => 86400,
                'urgency' => 'normal',
            ]);
        }
    }

    public function getPublicKey(): string { return $this->vapidPublic; }

    /**
     * Register a new push subscription
     */
    public function subscribe($userId, $endpoint, $keys, $userAgent = '', $deviceName = '') {
        if (empty($keys['p256dh']) || empty($keys['auth'])) {
            throw new Exception('Invalid subscription keys');
        }
        $stmt = $this->db->prepare("
            INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth, user_agent, device_name, last_used_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE p256dh = VALUES(p256dh), auth = VALUES(auth),
                                    is_active = 1, last_used_at = NOW()
        ");
        $stmt->execute([$userId, $endpoint, $keys['p256dh'], $keys['auth'], $userAgent, $deviceName ?: $this->detectDevice($userAgent)]);
        $this->ensurePreferences($userId);
        return ['success' => true, 'subscription_id' => $this->db->lastInsertId()];
    }

    /**
     * Unsubscribe by endpoint
     */
    public function unsubscribe($userId, $endpoint) {
        $stmt = $this->db->prepare("UPDATE push_subscriptions SET is_active = 0 WHERE user_id = ? AND endpoint = ?");
        $stmt->execute([$userId, $endpoint]);
        return ['success' => true];
    }

    /**
     * List all active devices for a user
     */
    public function listDevices($userId) {
        $stmt = $this->db->prepare("SELECT id, device_name, user_agent, last_used_at, created_at
                                    FROM push_subscriptions
                                    WHERE user_id = ? AND is_active = 1
                                    ORDER BY last_used_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function removeDevice($userId, $deviceId) {
        $stmt = $this->db->prepare("DELETE FROM push_subscriptions WHERE id = ? AND user_id = ?");
        $stmt->execute([$deviceId, $userId]);
        return ['success' => true];
    }

    /**
     * Get notification preferences
     */
    public function getPreferences($userId): array {
        $this->ensurePreferences($userId);
        $stmt = $this->db->prepare("SELECT * FROM notification_preferences WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updatePreferences($userId, $prefs) {
        $allowed = [
            'enabled','sound_enabled','vibration_enabled','desktop_enabled','mobile_enabled','email_enabled',
            'notify_new_message','notify_mention','notify_reply','notify_reaction','notify_call',
            'notify_poll','notify_story','notify_group_message','show_preview','show_sender','show_content',
            'quiet_hours_enabled','quiet_hours_start','quiet_hours_end','notify_mention_in_quiet',
        ];
        $sets = [];
        $params = [];
        foreach ($prefs as $k => $v) {
            if (!in_array($k, $allowed)) continue;
            $sets[] = "`$k` = ?";
            $params[] = is_bool($v) ? (int)$v : $v;
        }
        if (!$sets) return ['success' => true];
        $this->ensurePreferences($userId);
        $params[] = $userId;
        $sql = "UPDATE notification_preferences SET " . implode(', ', $sets) . " WHERE user_id = ?";
        $this->db->prepare($sql)->execute($params);
        return ['success' => true];
    }

    private function ensurePreferences($userId) {
        $this->db->prepare("INSERT IGNORE INTO notification_preferences (user_id) VALUES (?)")->execute([$userId]);
    }

    /**
     * Mute a chat temporarily
     */
    public function muteChat($userId, $chatId, $duration = null, $mode = 'muted') {
        if ($duration) {
            $until = date('Y-m-d H:i:s', time() + $this->parseDuration($duration));
        } else {
            $until = '2099-12-31 23:59:59';
        }
        $this->db->prepare("INSERT INTO chat_notification_overrides (chat_id, user_id, mode) VALUES (?, ?, ?)
                            ON DUPLICATE KEY UPDATE mode = VALUES(mode)")->execute([$chatId, $userId, $mode]);
        return ['success' => true, 'muted_until' => $until];
    }

    public function unmuteChat($userId, $chatId) {
        $this->db->prepare("DELETE FROM chat_notification_overrides WHERE chat_id = ? AND user_id = ?")->execute([$chatId, $userId]);
        return ['success' => true];
    }

    public function getChatOverrides($userId) {
        $stmt = $this->db->prepare("SELECT chat_id, mode, custom_sound FROM chat_notification_overrides WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Send push notification to a user
     * $payload: type, title, body, data (chat_id, message_id, etc.), icon, url
     */
    public function sendToUser($userId, $payload) {
        $prefs = $this->getPreferences($userId);

        // Master switch
        if (!$prefs['enabled']) return ['success' => false, 'reason' => 'disabled'];

        // Per-event switch
        $type = $payload['type'] ?? 'new_message';
        $typeKey = 'notify_' . $type;
        if (isset($prefs[$typeKey]) && !$prefs[$typeKey]) {
            return ['success' => false, 'reason' => 'event_disabled'];
        }

        // Chat override
        $chatId = $payload['data']['chat_id'] ?? null;
        if ($chatId) {
            $override = $this->db->prepare("SELECT mode FROM chat_notification_overrides WHERE chat_id = ? AND user_id = ?");
            $override->execute([$chatId, $userId]);
            $mode = $override->fetchColumn() ?: 'default';
            if ($mode === 'muted' || $mode === 'disabled') return ['success' => false, 'reason' => 'chat_muted'];
            if ($mode === 'mentions' && empty($payload['data']['is_mention'])) {
                return ['success' => false, 'reason' => 'only_mentions'];
            }
        }

        // Quiet hours
        if ($prefs['quiet_hours_enabled'] && !$this->isMentionAllowedDuringQuiet($payload, $prefs)) {
            if ($this->inQuietHours($prefs)) return ['success' => false, 'reason' => 'quiet_hours'];
        }

        // Privacy
        $title = $payload['title'];
        $body  = $payload['body'] ?? '';
        if (!$prefs['show_sender']) $title = 'پیام جدید';
        if (!$prefs['show_preview'] || !$prefs['show_content']) $body = 'پیام جدید دارید';

        // Send via WebPush
        $subsStmt = $this->db->prepare("SELECT endpoint, p256dh, auth FROM push_subscriptions WHERE user_id = ? AND is_active = 1");
        $subsStmt->execute([$userId]);
        $subs = $subsStmt->fetchAll(PDO::FETCH_ASSOC);

        $sent = 0;
        $errors = [];
        if (!$this->webPush || !$subs) {
            $this->logNotification($userId, $payload, $subs ? 'pending' : 'failed', 'No webpush lib or no subs');
            return ['success' => true, 'sent' => 0, 'reason' => $subs ? 'no_lib' : 'no_subs'];
        }

        $payloadJson = json_encode([
            'title' => $title,
            'body'  => $body,
            'icon'  => $payload['icon'] ?? '/assets/icon-192.png',
            'badge' => '/assets/badge-72.png',
            'data'  => $payload['data'] ?? [],
            'actions' => $payload['actions'] ?? [
                ['action' => 'open', 'title' => 'باز کردن'],
                ['action' => 'mute', 'title' => 'بی‌صدا کردن'],
            ],
            'tag' => $payload['tag'] ?? ($chatId ? 'chat_' . $chatId : 'default'),
            'requireInteraction' => $type === 'call',
            'vibrate' => $prefs['vibration_enabled'] ? [200, 100, 200] : [],
            'silent' => !$prefs['sound_enabled'],
        ], JSON_UNESCAPED_UNICODE);

        foreach ($subs as $sub) {
            $pushSub = \Minishlink\WebPush\Subscription::create([
                'endpoint' => $sub['endpoint'],
                'publicKey' => $sub['p256dh'],
                'authToken' => $sub['auth'],
            ]);
            try {
                $this->webPush->queueNotification($pushSub, $payloadJson);
                $sent++;
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        // Flush
        foreach ($this->webPush->flush() as $report) {
            $endpoint = $report->getEndpoint();
            if (!$report->isSuccess()) {
                // Remove invalid subscription
                if (in_array($report->getStatusCode(), [404, 410])) {
                    $this->db->prepare("UPDATE push_subscriptions SET is_active = 0 WHERE endpoint = ?")->execute([$endpoint]);
                }
            }
        }

        $this->logNotification($userId, $payload, $sent > 0 ? 'sent' : 'failed', $errors ? implode('; ', $errors) : null);
        return ['success' => true, 'sent' => $sent, 'errors' => $errors];
    }

    public function getNotificationLog($userId, $limit = 50) {
        $stmt = $this->db->prepare("SELECT id, type, title, body, status, sent_at, clicked_at, created_at, data
                                    FROM notification_log
                                    WHERE user_id = ?
                                    ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markClicked($userId, $notificationId) {
        $this->db->prepare("UPDATE notification_log SET status = 'clicked', clicked_at = NOW() WHERE id = ? AND user_id = ?")
            ->execute([$notificationId, $userId]);
    }

    public function getStats($userId) {
        $stmt = $this->db->prepare("SELECT
                                        COUNT(*) as total,
                                        SUM(status = 'sent') as sent,
                                        SUM(status = 'clicked') as clicked,
                                        SUM(status = 'dismissed') as dismissed,
                                        SUM(status = 'failed') as failed
                                    FROM notification_log
                                    WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ===== Helpers =====
    private function inQuietHours($prefs): bool {
        $now = date('H:i:s');
        $start = $prefs['quiet_hours_start'];
        $end = $prefs['quiet_hours_end'];
        if ($start <= $end) return $now >= $start && $now <= $end;
        return $now >= $start || $now <= $end;
    }

    private function isMentionAllowedDuringQuiet($payload, $prefs): bool {
        return $prefs['notify_mention_in_quiet'] && !empty($payload['data']['is_mention']);
    }

    private function logNotification($userId, $payload, $status, $error = null) {
        $this->db->prepare("INSERT INTO notification_log (user_id, type, title, body, data, status, error, sent_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([
                $userId,
                $payload['type'] ?? 'new_message',
                $payload['title'],
                $payload['body'] ?? '',
                json_encode($payload['data'] ?? []),
                $status,
                $error,
                $status === 'sent' ? date('Y-m-d H:i:s') : null,
            ]);
    }

    private function detectDevice($ua): string {
        if (stripos($ua, 'iPhone') !== false) return '📱 iPhone';
        if (stripos($ua, 'Android') !== false) return '📱 Android';
        if (stripos($ua, 'Mac') !== false) return '💻 Mac';
        if (stripos($ua, 'Windows') !== false) return '💻 Windows';
        if (stripos($ua, 'Linux') !== false) return '💻 Linux';
        return '🌐 Unknown';
    }

    private function parseDuration($d) {
        return match(true) {
            $d === '1h'  => 3600,
            $d === '8h'  => 8 * 3600,
            $d === '1d'  => 86400,
            $d === '1w'  => 7 * 86400,
            str_ends_with($d, 'h') => (int)$d * 3600,
            str_ends_with($d, 'd') => (int)$d * 86400,
            default => 0,
        };
    }
}
