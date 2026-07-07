<?php
/**
 * NexusChat - Message Class
 * Handles all message operations including forward, reply, reactions
 */
class Message {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Send a new message
     */
    public function send($chatId, $senderId, $content, $options = []) {
        $type        = $options['type']              ?? 'text';
        $replyToId   = $options['reply_to_id']        ?? null;
        $filePath    = $options['file_path']          ?? null;
        $fileSize    = $options['file_size']          ?? null;
        $mime        = $options['mime_type']          ?? null;
        $isEncrypted = $options['is_encrypted']       ?? 0;
        $encContent  = $options['encrypted_content']  ?? null;
        $forwardedFromId       = $options['forwarded_from_id']       ?? null;
        $forwardedFromChatId   = $options['forwarded_from_chat_id']   ?? null;
        $forwardedFromSenderId = $options['forwarded_from_sender_id'] ?? null;

        $stmt = $this->db->prepare("
            INSERT INTO messages
            (chat_id, sender_id, reply_to_id, forwarded_from_id, forwarded_from_chat_id, forwarded_from_sender_id, content, encrypted_content, type, file_path, file_size, mime_type, is_encrypted)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $chatId, $senderId, $replyToId,
            $forwardedFromId, $forwardedFromChatId, $forwardedFromSenderId,
            $content, $encContent, $type, $filePath, $fileSize, $mime, $isEncrypted
        ]);

        $id = $this->db->lastInsertId();

        // Update chat timestamp
        $this->db->prepare("UPDATE chats SET updated_at = NOW() WHERE id = ?")->execute([$chatId]);

        return $this->findById($id);
    }

    /**
     * Forward an existing message to another chat
     * Preserves original content, type, and file
     */
    public function forward($messageId, $fromChatId, $toChatId, $userId) {
        $original = $this->findById($messageId);
        if (!$original) {
            throw new Exception('پیام اصلی یافت نشد');
        }

        // Find original sender info
        $originalSender = (new User())->findById($original['sender_id']);
        $originalChat   = (new Chat())->findById($fromChatId);

        return $this->send($toChatId, $userId, $original['content'], [
            'type'                      => $original['type'],
            'file_path'                 => $original['file_path'],
            'file_size'                 => $original['file_size'],
            'mime_type'                 => $original['mime_type'],
            'is_encrypted'              => $original['is_encrypted'],
            'encrypted_content'         => $original['encrypted_content'],
            'forwarded_from_id'         => $messageId,
            'forwarded_from_chat_id'    => $fromChatId,
            'forwarded_from_sender_id'  => $original['sender_id'],
        ]);
    }

    /**
     * Get messages in a chat
     */
    public function getMessages($chatId, $limit = 50, $beforeId = null) {
        $sql = "
            SELECT m.*,
                   u.username AS sender_username,
                   u.display_name AS sender_display_name,
                   u.avatar AS sender_avatar
            FROM messages m
            JOIN users u ON u.id = m.sender_id
            WHERE m.chat_id = ? AND m.is_deleted = 0
        ";
        $params = [$chatId];
        if ($beforeId) {
            $sql .= " AND m.id < ?";
            $params[] = $beforeId;
        }
        $sql .= " ORDER BY m.id DESC LIMIT $limit";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Hydrate forward info
        foreach ($messages as &$m) {
            $m['forward_info'] = $this->getForwardedInfo($m);
            unset($m['encrypted_content']); // never send to client unless decrypted
        }
        unset($m);

        return array_reverse($messages);
    }

    /**
     * Get new messages since a specific ID (for polling)
     */
    public function getNewMessages($chatId, $lastId) {
        $stmt = $this->db->prepare("
            SELECT m.*,
                   u.username AS sender_username,
                   u.display_name AS sender_display_name,
                   u.avatar AS sender_avatar
            FROM messages m
            JOIN users u ON u.id = m.sender_id
            WHERE m.chat_id = ? AND m.id > ? AND m.is_deleted = 0
            ORDER BY m.id ASC
        ");
        $stmt->execute([$chatId, $lastId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($messages as &$m) {
            $m['forward_info'] = $this->getForwardedInfo($m);
        }
        unset($m);
        return $messages;
    }

    /**
     * Build forwarded-from info payload
     */
    public function getForwardedInfo($message) {
        if (empty($message['forwarded_from_id'])) return null;

        $sender = null;
        if (!empty($message['forwarded_from_sender_id'])) {
            $u = (new User())->findById($message['forwarded_from_sender_id']);
            if ($u) {
                $sender = [
                    'id'           => $u['id'],
                    'display_name' => $u['display_name'],
                    'username'     => $u['username'],
                    'avatar'       => $u['avatar'],
                ];
            }
        }

        return [
            'message_id' => $message['forwarded_from_id'],
            'chat_id'    => $message['forwarded_from_chat_id'],
            'sender'     => $sender,
        ];
    }

    /**
     * Find message by ID
     */
    public function findById($id) {
        $stmt = $this->db->prepare("SELECT * FROM messages WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Edit message content
     */
    public function edit($messageId, $userId, $newContent) {
        $msg = $this->findById($messageId);
        if (!$msg) return false;
        if ($msg['sender_id'] != $userId) {
            throw new Exception('شما نمی‌توانید این پیام را ویرایش کنید');
        }
        $stmt = $this->db->prepare("UPDATE messages SET content = ?, is_edited = 1 WHERE id = ?");
        return $stmt->execute([$newContent, $messageId]);
    }

    /**
     * Soft delete message
     */
    public function delete($messageId, $userId) {
        $msg = $this->findById($messageId);
        if (!$msg) return false;
        if ($msg['sender_id'] != $userId) {
            throw new Exception('شما نمی‌توانید این پیام را حذف کنید');
        }
        $stmt = $this->db->prepare("UPDATE messages SET is_deleted = 1, content = NULL, file_path = NULL WHERE id = ?");
        return $stmt->execute([$messageId]);
    }

    /**
     * Toggle reaction (add if missing, remove if exists)
     */
    public function toggleReaction($messageId, $userId, $emoji) {
        $stmt = $this->db->prepare("SELECT id FROM message_reactions WHERE message_id = ? AND user_id = ? AND emoji = ?");
        $stmt->execute([$messageId, $userId, $emoji]);
        if ($existing = $stmt->fetch()) {
            $this->db->prepare("DELETE FROM message_reactions WHERE id = ?")->execute([$existing['id']]);
            return false;
        } else {
            $this->db->prepare("INSERT INTO message_reactions (message_id, user_id, emoji) VALUES (?, ?, ?)")
                     ->execute([$messageId, $userId, $emoji]);
            return true;
        }
    }

    /**
     * Get reactions for a message (grouped)
     */
    public function getReactions($messageId) {
        $stmt = $this->db->prepare("
            SELECT emoji, user_id, COUNT(*) as cnt
            FROM message_reactions
            WHERE message_id = ?
            GROUP BY emoji, user_id
        ");
        $stmt->execute([$messageId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($rows as $r) {
            $e = $r['emoji'];
            if (!isset($grouped[$e])) {
                $grouped[$e] = ['emoji' => $e, 'count' => 0, 'user_ids' => []];
            }
            $grouped[$e]['count']++;
            $grouped[$e]['user_ids'][] = (int)$r['user_id'];
        }
        return array_values($grouped);
    }

    /**
     * Pin a message
     */
    public function pin($messageId, $userId) {
        $msg = $this->findById($messageId);
        if (!$msg) return false;
        $newState = $msg['is_pinned'] ? 0 : 1;
        $stmt = $this->db->prepare("UPDATE messages SET is_pinned = ? WHERE id = ?");
        $stmt->execute([$newState, $messageId]);
        return true;
    }

    /**
     * Search messages by content within a user's chats
     */
    public function search($userId, $query, $limit = 30) {
        $q = '%' . $query . '%';
        $stmt = $this->db->prepare("
            SELECT m.*, c.name AS chat_name, c.type AS chat_type,
                   u.display_name AS sender_display_name
            FROM messages m
            JOIN chats c ON c.id = m.chat_id
            JOIN chat_members cm ON cm.chat_id = c.id AND cm.user_id = ?
            JOIN users u ON u.id = m.sender_id
            WHERE m.is_deleted = 0
              AND m.content LIKE ?
            ORDER BY m.created_at DESC
            LIMIT $limit
        ");
        $stmt->execute([$userId, $q]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
