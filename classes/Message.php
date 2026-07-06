<?php
/**
 * NexusChat - Message Class
 */
class Message {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Send a message
     */
    public function send($chatId, $senderId, $content, $options = []) {
        $type = $options['type'] ?? 'text';
        $replyTo = $options['reply_to_id'] ?? null;
        $filePath = $options['file_path'] ?? null;
        $fileSize = $options['file_size'] ?? null;
        $mime = $options['mime_type'] ?? null;
        $isEncrypted = $options['is_encrypted'] ?? 0;
        $encryptedContent = $options['encrypted_content'] ?? null;

        $id = $this->db->insert('messages', [
            'chat_id'           => $chatId,
            'sender_id'         => $senderId,
            'reply_to_id'       => $replyTo,
            'content'           => $content,
            'encrypted_content' => $encryptedContent,
            'type'              => $type,
            'file_path'         => $filePath,
            'file_size'         => $fileSize,
            'mime_type'         => $mime,
            'is_encrypted'      => $isEncrypted,
        ]);

        // Update chat timestamp
        $this->db->query("UPDATE chats SET updated_at = NOW() WHERE id = ?", [$chatId]);

        // Reset unread counts for sender
        $this->db->query(
            "UPDATE chat_members SET unread_count = 0 WHERE chat_id = ? AND user_id = ?",
            [$chatId, $senderId]
        );

        // Increment unread for other members
        $this->db->query(
            "UPDATE chat_members SET unread_count = unread_count + 1
             WHERE chat_id = ? AND user_id != ?",
            [$chatId, $senderId]
        );

        return $this->findById($id);
    }

    public function findById($id) {
        return $this->db->fetch("SELECT * FROM messages WHERE id = ?", [$id]);
    }

    /**
     * Get messages for a chat with pagination
     */
    public function getMessages($chatId, $limit = 50, $beforeId = null) {
        $sql = "SELECT m.*, u.username, u.display_name, u.avatar
                FROM messages m
                JOIN users u ON u.id = m.sender_id
                WHERE m.chat_id = ? AND m.is_deleted = 0";
        $params = [$chatId];
        if ($beforeId) {
            $sql .= " AND m.id < ?";
            $params[] = $beforeId;
        }
        $sql .= " ORDER BY m.id DESC LIMIT " . (int)$limit;
        return array_reverse($this->db->fetchAll($sql, $params));
    }

    /**
     * Get new messages since last seen
     */
    public function getNewMessages($chatId, $afterId) {
        return $this->db->fetchAll(
            "SELECT m.*, u.username, u.display_name, u.avatar
             FROM messages m
             JOIN users u ON u.id = m.sender_id
             WHERE m.chat_id = ? AND m.id > ?
             ORDER BY m.id ASC",
            [$chatId, $afterId]
        );
    }

    public function edit($messageId, $userId, $newContent) {
        $msg = $this->findById($messageId);
        if (!$msg || $msg['sender_id'] != $userId) {
            return false;
        }
        $this->db->update('messages',
            ['content' => $newContent, 'is_edited' => 1],
            'id = :id',
            ['id' => $messageId]
        );
        return true;
    }

    public function delete($messageId, $userId) {
        $msg = $this->findById($messageId);
        if (!$msg || $msg['sender_id'] != $userId) {
            return false;
        }
        $this->db->update('messages',
            ['is_deleted' => 1, 'content' => null],
            'id = :id',
            ['id' => $messageId]
        );
        return true;
    }

    public function toggleReaction($messageId, $userId, $emoji) {
        $existing = $this->db->fetch(
            "SELECT id FROM message_reactions WHERE message_id = ? AND user_id = ? AND emoji = ?",
            [$messageId, $userId, $emoji]
        );
        if ($existing) {
            $this->db->delete('message_reactions', 'id = ?', [$existing['id']]);
            return false;
        }
        $this->db->insert('message_reactions', [
            'message_id' => $messageId,
            'user_id'    => $userId,
            'emoji'      => $emoji,
        ]);
        return true;
    }

    public function getReactions($messageId) {
        return $this->db->fetchAll(
            "SELECT emoji, user_id, COUNT(*) as count
             FROM message_reactions
             WHERE message_id = ?
             GROUP BY emoji",
            [$messageId]
        );
    }

    public function pin($messageId, $userId) {
        $msg = $this->findById($messageId);
        if (!$msg) return false;
        $this->db->update('messages',
            ['is_pinned' => 1],
            'id = :id',
            ['id' => $messageId]
        );
        $this->db->update('chats',
            ['pinned_message_id' => $messageId],
            'id = :id',
            ['id' => $msg['chat_id']]
        );
        return true;
    }
}
