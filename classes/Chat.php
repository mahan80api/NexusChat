<?php
/**
 * NexusChat - Chat Class (private, group, channel)
 */
class Chat {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Get or create a private chat between two users
     */
    public function getOrCreatePrivate($userId, $otherUserId) {
        $chat = $this->db->fetch(
            "SELECT c.* FROM chats c
             JOIN chat_members m1 ON m1.chat_id = c.id AND m1.user_id = ?
             JOIN chat_members m2 ON m2.chat_id = c.id AND m2.user_id = ?
             WHERE c.type = 'private'
             LIMIT 1",
            [$userId, $otherUserId]
        );
        if ($chat) return $chat;

        $this->db->beginTransaction();
        try {
            $chatId = $this->db->insert('chats', [
                'type'       => 'private',
                'created_by' => $userId,
            ]);
            $this->db->insert('chat_members', [
                'chat_id' => $chatId, 'user_id' => $userId, 'role' => 'member'
            ]);
            $this->db->insert('chat_members', [
                'chat_id' => $chatId, 'user_id' => $otherUserId, 'role' => 'member'
            ]);
            $this->db->commit();
            return $this->findById($chatId);
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Create a group chat
     */
    public function createGroup($creatorId, $name, $description, $members = []) {
        if (empty($name)) {
            throw new Exception('نام گروه نمی‌تواند خالی باشد');
        }
        $this->db->beginTransaction();
        try {
            $chatId = $this->db->insert('chats', [
                'type'        => 'group',
                'name'        => $name,
                'description' => $description,
                'created_by'  => $creatorId,
            ]);
            $this->db->insert('chat_members', [
                'chat_id' => $chatId, 'user_id' => $creatorId, 'role' => 'owner'
            ]);
            foreach (array_unique($members) as $userId) {
                if ($userId != $creatorId) {
                    $this->db->insert('chat_members', [
                        'chat_id' => $chatId, 'user_id' => $userId, 'role' => 'member'
                    ]);
                }
            }
            $this->db->commit();
            return $this->findById($chatId);
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Create a channel
     */
    public function createChannel($creatorId, $name, $description, $isPublic = true) {
        $this->db->beginTransaction();
        try {
            $chatId = $this->db->insert('chats', [
                'type'        => 'channel',
                'name'        => $name,
                'description' => $description,
                'created_by'  => $creatorId,
            ]);
            $this->db->insert('chat_members', [
                'chat_id' => $chatId, 'user_id' => $creatorId, 'role' => 'owner'
            ]);
            $this->db->commit();
            return $this->findById($chatId);
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function findById($id) {
        return $this->db->fetch("SELECT * FROM chats WHERE id = ?", [$id]);
    }

    /**
     * Get all chats for a user with last message preview
     */
    public function getUserChats($userId) {
        return $this->db->fetchAll(
            "SELECT c.*,
                    cm.unread_count, cm.is_pinned, cm.is_muted,
                    (SELECT content FROM messages WHERE chat_id = c.id ORDER BY id DESC LIMIT 1) as last_message,
                    (SELECT type FROM messages WHERE chat_id = c.id ORDER BY id DESC LIMIT 1) as last_message_type,
                    (SELECT created_at FROM messages WHERE chat_id = c.id ORDER BY id DESC LIMIT 1) as last_message_time,
                    (SELECT sender_id FROM messages WHERE chat_id = c.id ORDER BY id DESC LIMIT 1) as last_sender
             FROM chats c
             JOIN chat_members cm ON cm.chat_id = c.id
             WHERE cm.user_id = ?
             ORDER BY cm.is_pinned DESC, c.updated_at DESC",
            [$userId]
        );
    }

    public function getMembers($chatId) {
        return $this->db->fetchAll(
            "SELECT u.id, u.username, u.display_name, u.avatar, u.is_online, u.last_seen, cm.role, cm.joined_at
             FROM chat_members cm
             JOIN users u ON u.id = cm.user_id
             WHERE cm.chat_id = ?
             ORDER BY cm.role ASC, u.display_name ASC",
            [$chatId]
        );
    }

    public function isMember($chatId, $userId) {
        return (bool) $this->db->fetch(
            "SELECT 1 FROM chat_members WHERE chat_id = ? AND user_id = ?",
            [$chatId, $userId]
        );
    }

    public function addMember($chatId, $userId, $role = 'member') {
        try {
            $this->db->insert('chat_members', [
                'chat_id' => $chatId, 'user_id' => $userId, 'role' => $role
            ]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function removeMember($chatId, $userId) {
        return $this->db->delete('chat_members',
            'chat_id = ? AND user_id = ?',
            [$chatId, $userId]
        );
    }

    public function markAsRead($chatId, $userId, $lastMessageId) {
        return $this->db->update('chat_members',
            ['unread_count' => 0, 'last_read_message_id' => $lastMessageId],
            'chat_id = :chat_id AND user_id = :user_id',
            ['chat_id' => $chatId, 'user_id' => $userId]
        );
    }

    public function togglePin($chatId, $userId) {
        $current = $this->db->fetch(
            "SELECT is_pinned FROM chat_members WHERE chat_id = ? AND user_id = ?",
            [$chatId, $userId]
        );
        $new = $current && $current['is_pinned'] ? 0 : 1;
        $this->db->update('chat_members',
            ['is_pinned' => $new],
            'chat_id = :c AND user_id = :u',
            ['c' => $chatId, 'u' => $userId]
        );
        return (bool)$new;
    }

    public function toggleMute($chatId, $userId) {
        $current = $this->db->fetch(
            "SELECT is_muted FROM chat_members WHERE chat_id = ? AND user_id = ?",
            [$chatId, $userId]
        );
        $new = $current && $current['is_muted'] ? 0 : 1;
        $this->db->update('chat_members',
            ['is_muted' => $new],
            'chat_id = :c AND user_id = :u',
            ['c' => $chatId, 'u' => $userId]
        );
        return (bool)$new;
    }
}
