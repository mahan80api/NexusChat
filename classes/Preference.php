<?php
/**
 * NexusChat - User Preferences
 * Manages DND, mutes, notification settings
 */
class Preference {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Get user preferences, create defaults if missing
     */
    public function get($userId) {
        $stmt = $this->db->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
        $stmt->execute([$userId]);
        $prefs = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$prefs) {
            $this->db->prepare("INSERT INTO user_preferences (user_id) VALUES (?)")->execute([$userId]);
            return $this->get($userId);
        }
        $prefs['dnd_allow_messages_from'] = $prefs['dnd_allow_messages_from'] ? json_decode($prefs['dnd_allow_messages_from'], true) : [];
        return $prefs;
    }

    /**
     * Update preferences
     */
    public function update($userId, $data) {
        $allowed = [
            'dnd_enabled', 'dnd_until', 'dnd_allow_mentions', 'dnd_allow_messages_from',
            'dnd_allow_calls', 'dnd_show_in_status', 'notification_sound', 'notification_volume',
            'desktop_notifications', 'email_notifications', 'message_preview',
            'vibration', 'read_receipts', 'typing_indicators', 'last_seen_visibility',
            'profile_photo_visibility', 'call_visibility', 'forward_privacy',
            'auto_download_media', 'language',
        ];
        $fields = [];
        $params = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $value = $data[$f];
                if ($f === 'dnd_allow_messages_from' && is_array($value)) {
                    $value = json_encode(array_values(array_map('intval', $value)));
                } elseif (in_array($f, ['dnd_enabled', 'dnd_allow_mentions', 'dnd_allow_calls', 'dnd_show_in_status', 'desktop_notifications', 'email_notifications', 'message_preview', 'vibration', 'read_receipts', 'typing_indicators', 'forward_privacy', 'auto_download_media'])) {
                    $value = $value ? 1 : 0;
                }
                $fields[] = "$f = ?";
                $params[] = $value;
            }
        }
        if (empty($fields)) return false;
        $params[] = $userId;
        $sql = "INSERT INTO user_preferences (user_id, " . implode(', ', array_map(fn($f) => explode(' = ', $f)[0], $fields)) . ")
                VALUES (?, " . implode(', ', array_fill(0, count($fields), '?')) . ")
                ON DUPLICATE KEY UPDATE " . implode(', ', $fields);
        $insertParams = array_merge([$userId], array_slice($params, 0, -1), [$userId]);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($insertParams);
        return true;
    }

    /**
     * Check if DND is currently active
     */
    public function isDndActive($userId) {
        $p = $this->get($userId);
        if (!$p['dnd_enabled']) return false;
        if ($p['dnd_until']) {
            $until = strtotime($p['dnd_until']);
            if ($until < time()) {
                // Auto-disable expired DND
                $this->update($userId, ['dnd_enabled' => 0, 'dnd_until' => null]);
                return false;
            }
        }
        return true;
    }

    /**
     * Should we notify this user for a given chat?
     */
    public function shouldNotify($userId, $chatId, $isMention = false, $senderId = null) {
        if (!$this->isDndActive($userId)) {
            return ['notify' => true, 'silenced' => null];
        }
        $p = $this->get($userId);

        // DND is on
        // Always allow mentions
        if ($isMention && $p['dnd_allow_mentions']) {
            return ['notify' => true, 'silenced' => 'dnd_mention_allowed'];
        }
        // Always allow from specific users
        if ($senderId && in_array((int)$senderId, $p['dnd_allow_messages_from'] ?? [], true)) {
            return ['notify' => true, 'silenced' => 'dnd_allowlist'];
        }
        // Check chat mute
        if ($this->isChatMuted($userId, $chatId)) {
            return ['notify' => false, 'silenced' => 'chat_muted'];
        }
        return ['notify' => false, 'silenced' => 'dnd'];
    }

    /**
     * Mute a chat
     */
    public function muteChat($userId, $chatId, $duration = null) {
        $until = null;
        if ($duration) {
            $until = date('Y-m-d H:i:s', time() + $duration);
        }
        $stmt = $this->db->prepare("INSERT INTO chat_mutes (chat_id, user_id, muted_until)
                                    VALUES (?, ?, ?)
                                    ON DUPLICATE KEY UPDATE muted_until = VALUES(muted_until)");
        $stmt->execute([$chatId, $userId, $until]);
        return true;
    }

    /**
     * Unmute a chat
     */
    public function unmuteChat($userId, $chatId) {
        $stmt = $this->db->prepare("DELETE FROM chat_mutes WHERE user_id = ? AND chat_id = ?");
        $stmt->execute([$userId, $chatId]);
        return true;
    }

    /**
     * Check if a chat is muted for this user
     */
    public function isChatMuted($userId, $chatId) {
        $stmt = $this->db->prepare("SELECT muted_until FROM chat_mutes WHERE user_id = ? AND chat_id = ?");
        $stmt->execute([$userId, $chatId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return false;
        if ($row['muted_until']) {
            $until = strtotime($row['muted_until']);
            if ($until < time()) {
                $this->unmuteChat($userId, $chatId);
                return false;
            }
        }
        return true;
    }

    /**
     * Get all muted chats for a user
     */
    public function getMutedChats($userId) {
        $stmt = $this->db->prepare("SELECT cm.*, c.name as chat_name, c.type as chat_type,
                                           u.display_name as other_name, u.avatar as other_avatar
                                    FROM chat_mutes cm
                                    JOIN chats c ON c.id = cm.chat_id
                                    LEFT JOIN chat_members mem ON mem.chat_id = c.id AND mem.user_id != ?
                                    LEFT JOIN users u ON u.id = mem.user_id
                                    WHERE cm.user_id = ?
                                    ORDER BY cm.created_at DESC");
        $stmt->execute([$userId, $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Log a notification
     */
    public function logNotification($userId, $chatId, $messageId, $type, $delivered, $silencedReason = null) {
        $stmt = $this->db->prepare("INSERT INTO notification_log (user_id, chat_id, message_id, type, delivered, silenced_reason)
                                    VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $chatId, $messageId, $type, $delivered ? 1 : 0, $silencedReason]);
        return $this->db->lastInsertId();
    }

    /**
     * Get notification stats
     */
    public function getNotificationStats($userId, $days = 7) {
        $since = date('Y-m-d H:i:s', time() - $days * 86400);
        $stmt = $this->db->prepare("SELECT
                                        COUNT(*) as total,
                                        SUM(delivered) as delivered,
                                        SUM(CASE WHEN silenced_reason = 'dnd' THEN 1 ELSE 0 END) as dnd_silenced,
                                        SUM(CASE WHEN silenced_reason = 'chat_muted' THEN 1 ELSE 0 END) as chat_muted,
                                        SUM(CASE WHEN silenced_reason = 'dnd_mention_allowed' THEN 1 ELSE 0 END) as mention_allowed
                                    FROM notification_log
                                    WHERE user_id = ? AND created_at >= ?");
        $stmt->execute([$userId, $since]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
