<?php
/**
 * NexusChat - Chat Class
 */
class Chat {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Find chat by id
     */
    public function findById($id) {
        $stmt = $this->db->prepare("SELECT * FROM chats WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Check if user is member of chat
     */
    public function isMember($chatId, $userId) {
        $stmt = $this->db->prepare("SELECT 1 FROM chat_members WHERE chat_id = ? AND user_id = ?");
        $stmt->execute([$chatId, $userId]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Get list of chats for a user
     */
    public function getChats($userId) {
        $stmt = $this->db->prepare("
            SELECT
                c.*,
                cm.unread_count,
                (
                    SELECT m.content FROM messages m
                    WHERE m.chat_id = c.id AND m.is_deleted = 0
                    ORDER BY m.id DESC LIMIT 1
                ) AS last_message,
                (
                    SELECT m.type FROM messages m
                    WHERE m.chat_id = c.id AND m.is_deleted = 0
                    ORDER BY m.id DESC LIMIT 1
                ) AS last_message_type,
                (
                    SELECT m.created_at FROM messages m
                    WHERE m.chat_id = c.id AND m.is_deleted = 0
                    ORDER BY m.id DESC LIMIT 1
                ) AS last_message_at,
                CASE
                    WHEN c.type = 'private' THEN (
                        SELECT json_object(
                            'id', u.id,
                            'username', u.username,
                            'display_name', u.display_name,
                            'avatar', u.avatar,
                            'is_online', u.is_online,
                            'last_seen', u.last_seen,
                            'status_text', u.status_text
                        )
                        FROM chat_members cm2
                        JOIN users u ON cm2.user_id = u.id
                        WHERE cm2.chat_id = c.id AND cm2.user_id != ? LIMIT 1
                    )
                    ELSE NULL
                END AS other_user_json
            FROM chats c
            JOIN chat_members cm ON cm.chat_id = c.id
            WHERE cm.user_id = ?
            ORDER BY COALESCE(
                (SELECT m.created_at FROM messages m WHERE m.chat_id = c.id ORDER BY m.id DESC LIMIT 1),
                c.created_at
            ) DESC
        ");
        $stmt->execute([$userId, $userId]);
        $chats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($chats as &$c) {
            if (!empty($c['other_user_json'])) {
                $decoded = json_decode($c['other_user_json'], true);
                $c['other_user'] = is_array($decoded) ? $decoded : null;
            } else {
                $c['other_user'] = null;
            }
            unset($c['other_user_json']);
            $c['last_message_ago'] = !empty($c['last_message_at'])
                ? $this->timeAgo($c['last_message_at'])
                : '';
            $c['unread_count'] = (int)$c['unread_count'];

            // Add preview based on last message type
            if (!empty($c['last_message_type'])) {
                $typeMap = [
                    'image' => '🖼️ تصویر',
                    'video' => '🎬 ویدیو',
                    'voice' => '🎤 پیام صوتی',
                    'file'  => '📄 فایل',
                    'location' => '📍 موقعیت',
                    'contact'  => '👤 مخاطب',
                    'sticker'  => '😀 استیکر',
                    'poll'     => '📊 نظرسنجی',
                ];
                if (isset($typeMap[$c['last_message_type']])) {
                    $c['last_message'] = $typeMap[$c['last_message_type']];
                }
            }
        }
        return $chats;
    }

    /**
     * Get all members of a chat
     */
    public function getMembers($chatId) {
        $stmt = $this->db->prepare("
            SELECT u.id, u.username, u.display_name, u.avatar, u.is_online, cm.role
            FROM chat_members cm
            JOIN users u ON cm.user_id = u.id
            WHERE cm.chat_id = ?
        ");
        $stmt->execute([$chatId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Search chats by name or member username
     */
    public function search($userId, $query) {
        $stmt = $this->db->prepare("
            SELECT DISTINCT
                c.*,
                CASE
                    WHEN c.type = 'private' THEN (
                        SELECT json_object(
                            'id', u.id, 'username', u.username,
                            'display_name', u.display_name, 'avatar', u.avatar
                        )
                        FROM chat_members cm2
                        JOIN users u ON cm2.user_id = u.id
                        WHERE cm2.chat_id = c.id AND cm2.user_id != ? LIMIT 1
                    )
                    ELSE NULL
                END AS other_user_json
            FROM chats c
            JOIN chat_members cm ON cm.chat_id = c.id
            LEFT JOIN chat_members cm2 ON cm2.chat_id = c.id AND cm2.user_id != ?
            LEFT JOIN users u ON u.id = cm2.user_id
            WHERE cm.user_id = ?
              AND (c.name LIKE ? OR u.username LIKE ? OR u.display_name LIKE ?)
            ORDER BY c.updated_at DESC
            LIMIT 30
        ");
        $like = '%' . $query . '%';
        $stmt->execute([$userId, $userId, $userId, $like, $like, $like]);
        $chats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($chats as &$c) {
            if (!empty($c['other_user_json'])) {
                $c['other_user'] = json_decode($c['other_user_json'], true);
            } else {
                $c['other_user'] = null;
            }
            unset($c['other_user_json']);
        }
        return $chats;
    }

    /**
     * Create private chat between two users (or return existing)
     */
    public function createPrivate($userId, $otherUserId) {
        if ($userId == $otherUserId) throw new Exception('نمی‌توانید با خود چت کنید');
        $stmt = $this->db->prepare("
            SELECT c.id FROM chats c
            JOIN chat_members m1 ON m1.chat_id = c.id AND m1.user_id = ?
            JOIN chat_members m2 ON m2.chat_id = c.id AND m2.user_id = ?
            WHERE c.type = 'private'
            LIMIT 1
        ");
        $stmt->execute([$userId, $otherUserId]);
        $existing = $stmt->fetchColumn();
        if ($existing) return $this->findById($existing);

        $this->db->prepare("INSERT INTO chats (type, created_by) VALUES ('private', ?)")
                 ->execute([$userId]);
        $chatId = $this->db->lastInsertId();
        $this->db->prepare("INSERT INTO chat_members (chat_id, user_id) VALUES (?, ?), (?, ?)")
                 ->execute([$chatId, $userId, $chatId, $otherUserId]);
        return $this->findById($chatId);
    }

    /**
     * Create group chat
     */
    public function createGroup($userId, $name, $description = null, $memberIds = []) {
        $this->db->prepare("INSERT INTO chats (type, name, description, created_by) VALUES ('group', ?, ?, ?)")
                 ->execute([$name, $description, $userId]);
        $chatId = $this->db->lastInsertId();
        $this->db->prepare("INSERT INTO chat_members (chat_id, user_id, role) VALUES (?, ?, 'owner')")
                 ->execute([$chatId, $userId]);
        foreach ($memberIds as $mid) {
            $this->db->prepare("INSERT IGNORE INTO chat_members (chat_id, user_id) VALUES (?, ?)")
                     ->execute([$chatId, (int)$mid]);
        }
        return $this->findById($chatId);
    }

    /**
     * Create channel
     */
    public function createChannel($userId, $name, $description = null) {
        $this->db->prepare("INSERT INTO chats (type, name, description, created_by) VALUES ('channel', ?, ?, ?)")
                 ->execute([$name, $description, $userId]);
        $chatId = $this->db->lastInsertId();
        $this->db->prepare("INSERT INTO chat_members (chat_id, user_id, role) VALUES (?, ?, 'owner')")
                 ->execute([$chatId, $userId]);
        return $this->findById($chatId);
    }

    /**
     * Mark chat as read
     */
    public function markAsRead($chatId, $userId, $lastMessageId) {
        $this->db->prepare("UPDATE chat_members SET unread_count = 0, last_read_message_id = ?
                            WHERE chat_id = ? AND user_id = ?")
                 ->execute([$lastMessageId, $chatId, $userId]);
        return true;
    }

    /**
     * Add member to chat
     */
    public function addMember($chatId, $userId) {
        $this->db->prepare("INSERT IGNORE INTO chat_members (chat_id, user_id) VALUES (?, ?)")
                 ->execute([$chatId, $userId]);
        return true;
    }

    /**
     * Remove member from chat
     */
    public function removeMember($chatId, $userId) {
        $this->db->prepare("DELETE FROM chat_members WHERE chat_id = ? AND user_id = ?")
                 ->execute([$chatId, $userId]);
        return true;
    }

    /**
     * Human-readable time ago
     */
    private function timeAgo($datetime) {
        $now = new DateTime();
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);
        if ($diff->y > 0) return $diff->y . ' سال پیش';
        if ($diff->m > 0) return $diff->m . ' ماه پیش';
        if ($diff->d > 0) return $diff->d . ' روز پیش';
        if ($diff->h > 0) return $diff->h . ' ساعت پیش';
        if ($diff->i > 0) return $diff->i . ' دقیقه پیش';
        return 'هم اکنون';
    }
}
