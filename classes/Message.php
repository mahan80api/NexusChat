<?php
/**
 * NexusChat - Message Class
 */
class Message {
    private $db;
    private $userId;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->userId = current_user_id();
    }

    /**
     * Send a new message
     */
    public function send($chatId, $senderId, $content, $options = []) {
        $type              = $options['type'] ?? 'text';
        $replyTo           = $options['reply_to_id'] ?? null;
        $filePath          = $options['file_path'] ?? null;
        $fileSize          = $options['file_size'] ?? null;
        $mime              = $options['mime_type'] ?? null;
        $isEncrypted       = $options['is_encrypted'] ?? 0;
        $encryptedContent  = $options['encrypted_content'] ?? null;
        $duration          = $options['duration'] ?? null;

        $stmt = $this->db->prepare("
            INSERT INTO messages
            (chat_id, sender_id, reply_to_id, content, type, file_path, file_size, mime_type, is_encrypted, encrypted_content, duration)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $chatId, $senderId, $replyTo, $content, $type, $filePath, $fileSize, $mime, $isEncrypted, $encryptedContent, $duration
        ]);
        $messageId = $this->db->lastInsertId();

        $this->db->prepare("UPDATE chats SET updated_at = NOW() WHERE id = ?")->execute([$chatId]);
        $this->db->prepare("UPDATE chat_members SET unread_count = unread_count + 1
                            WHERE chat_id = ? AND user_id != ?")
                  ->execute([$chatId, $senderId]);

        return $this->findById($messageId);
    }

    /**
     * Get messages in chat (paginated)
     */
    public function getMessages($chatId, $limit = 50, $beforeId = null) {
        $sql = "SELECT m.*, u.username, u.display_name, u.avatar
                FROM messages m
                JOIN users u ON m.sender_id = u.id
                WHERE m.chat_id = ?";
        $params = [$chatId];
        if ($beforeId) {
            $sql .= " AND m.id < ?";
            $params[] = $beforeId;
        }
        $sql .= " ORDER BY m.id DESC LIMIT " . (int)$limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $messages = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

        foreach ($messages as &$m) {
            $m['forward_info'] = $this->getForwardInfo($m);
        }
        return $messages;
    }

    /**
     * Find message by id
     */
    public function findById($id) {
        $stmt = $this->db->prepare("SELECT m.*, u.username, u.display_name, u.avatar
                                    FROM messages m
                                    JOIN users u ON m.sender_id = u.id
                                    WHERE m.id = ?");
        $stmt->execute([$id]);
        $m = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($m) $m['forward_info'] = $this->getForwardInfo($m);
        return $m;
    }

    /**
     * Get forward info if message was forwarded
     */
    public function getForwardInfo($message) {
        if (empty($message['forwarded_from_id'])) return null;
        $orig = $this->findById($message['forwarded_from_id']);
        if (!$orig) return null;
        $sender = (new User())->getPublicProfile($orig['sender_id']);
        return ['sender' => $sender, 'message' => $orig];
    }

    /**
     * Edit message
     */
    public function edit($messageId, $userId, $newContent) {
        $stmt = $this->db->prepare("UPDATE messages SET content = ?, is_edited = 1
                                    WHERE id = ? AND sender_id = ? AND is_deleted = 0");
        $stmt->execute([$newContent, $messageId, $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Delete message (soft delete)
     */
    public function delete($messageId, $userId) {
        $stmt = $this->db->prepare("UPDATE messages SET is_deleted = 1, content = NULL, file_path = NULL
                                    WHERE id = ? AND sender_id = ?");
        $stmt->execute([$messageId, $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Toggle reaction (add if not exists, remove if exists)
     */
    public function toggleReaction($messageId, $userId, $emoji) {
        $stmt = $this->db->prepare("SELECT id FROM message_reactions
                                    WHERE message_id = ? AND user_id = ? AND emoji = ?");
        $stmt->execute([$messageId, $userId, $emoji]);
        if ($stmt->fetch()) {
            $this->db->prepare("DELETE FROM message_reactions
                                WHERE message_id = ? AND user_id = ? AND emoji = ?")
                     ->execute([$messageId, $userId, $emoji]);
            return false;
        }
        $this->db->prepare("INSERT INTO message_reactions (message_id, user_id, emoji) VALUES (?, ?, ?)")
                 ->execute([$messageId, $userId, $emoji]);
        return true;
    }

    /**
     * Get aggregated reactions for a message
     */
    public function getReactions($messageId) {
        $stmt = $this->db->prepare("SELECT emoji, GROUP_CONCAT(user_id) AS user_ids, COUNT(*) AS count
                                    FROM message_reactions
                                    WHERE message_id = ?
                                    GROUP BY emoji");
        $stmt->execute([$messageId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['user_ids'] = $r['user_ids'] ? array_map('intval', explode(',', $r['user_ids'])) : [];
            $r['count'] = (int)$r['count'];
        }
        return $rows;
    }

    /**
     * Pin/unpin message
     */
    public function pin($messageId, $userId) {
        $msg = $this->findById($messageId);
        if (!$msg) return false;
        $chat = new Chat();
        if (!$chat->isMember($msg['chat_id'], $userId)) return false;
        $newState = $msg['is_pinned'] ? 0 : 1;
        $this->db->prepare("UPDATE messages SET is_pinned = ? WHERE id = ?")
                 ->execute([$newState, $messageId]);
        if ($newState) {
            $this->db->prepare("UPDATE chats SET pinned_message_id = ? WHERE id = ?")
                     ->execute([$messageId, $msg['chat_id']]);
        } else {
            $this->db->prepare("UPDATE chats SET pinned_message_id = NULL WHERE id = ? AND pinned_message_id = ?")
                     ->execute([$msg['chat_id'], $messageId]);
        }
        return true;
    }

    /**
     * Forward a message to one or more chats
     */
    public function forward($messageId, $fromChatId, $toChatId, $userId) {
        $msg = $this->findById($messageId);
        if (!$msg) throw new Exception('پیام یافت نشد');
        $content = $msg['is_deleted'] ? null : $msg['content'];
        return $this->send($toChatId, $userId, $content, [
            'type'                   => $msg['type'],
            'file_path'              => $msg['file_path'],
            'file_size'              => $msg['file_size'],
            'mime_type'              => $msg['mime_type'],
            'is_encrypted'           => $msg['is_encrypted'],
            'encrypted_content'      => $msg['encrypted_content'],
            'duration'               => $msg['duration'],
            'forwarded_from_id'      => $messageId,
            'forwarded_from_chat_id' => $fromChatId,
            'forwarded_from_sender_id' => $msg['sender_id'],
        ]);
    }

    /**
     * Advanced search across messages with filters
     * Returns ['items' => [...], 'total' => N]
     */
    public function search($userId, $query, $filters = []) {
        $chatId   = $filters['chat_id']   ?? null;
        $type     = $filters['type']      ?? null;
        $sender   = $filters['sender_id'] ?? null;
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo   = $filters['date_to']   ?? null;
        $limit    = (int)($filters['limit'] ?? 50);
        $offset   = (int)($filters['offset'] ?? 0);

        // Build WHERE for messages visible to user
        $where = ["m.is_deleted = 0"];
        $params = [];

        $where[] = "m.chat_id IN (SELECT chat_id FROM chat_members WHERE user_id = ?)";
        $params[] = $userId;

        if ($chatId) {
            $where[] = "m.chat_id = ?";
            $params[] = $chatId;
        }
        if ($type) {
            $where[] = "m.type = ?";
            $params[] = $type;
        }
        if ($sender) {
            $where[] = "m.sender_id = ?";
            $params[] = $sender;
        }
        if ($dateFrom) {
            $where[] = "m.created_at >= ?";
            $params[] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo) {
            $where[] = "m.created_at <= ?";
            $params[] = $dateTo . ' 23:59:59';
        }
        if (mb_strlen($query) >= 2) {
            $where[] = "m.content LIKE ?";
            $params[] = '%' . $query . '%';
        }

        $whereStr = implode(' AND ', $where);

        // Total count
        $countSql = "SELECT COUNT(*) FROM messages m WHERE $whereStr";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Page items
        $sql = "SELECT m.*, u.username, u.display_name, u.avatar,
                       c.type AS chat_type, c.name AS chat_name
                FROM messages m
                JOIN users u ON m.sender_id = u.id
                JOIN chats c ON m.chat_id = c.id
                WHERE $whereStr
                ORDER BY m.id DESC
                LIMIT $limit OFFSET $offset";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Add forward info and highlight
        foreach ($items as &$m) {
            $m['forward_info'] = $this->getForwardInfo($m);
            if (mb_strlen($query) >= 2 && $m['content']) {
                $m['highlighted'] = $this->highlight($m['content'], $query);
            } else {
                $m['highlighted'] = $m['content'];
            }
        }

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Highlight matched term with <mark> tags
     */
    private function highlight($text, $query) {
        if (mb_strlen($query) < 2) return htmlspecialchars($text);
        $safe = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $pattern = '/' . preg_quote(htmlspecialchars($query, ENT_QUOTES, 'UTF-8'), '/') . '/iu';
        return preg_replace($pattern, '<mark>$0</mark>', $safe);
    }

    /**
     * Toggle saved (bookmark) state
     */
    public function toggleSave($messageId, $userId) {
        $stmt = $this->db->prepare("SELECT id FROM saved_messages WHERE user_id = ? AND message_id = ?");
        $stmt->execute([$userId, $messageId]);
        if ($stmt->fetch()) {
            $this->db->prepare("DELETE FROM saved_messages WHERE user_id = ? AND message_id = ?")
                     ->execute([$userId, $messageId]);
            return false;
        }
        $this->db->prepare("INSERT INTO saved_messages (user_id, message_id) VALUES (?, ?)")
                 ->execute([$userId, $messageId]);
        return true;
    }

    /**
     * Get user's saved messages
     */
    public function getSaved($userId, $limit = 50, $offset = 0) {
        $sql = "SELECT m.*, u.username, u.display_name, u.avatar, sm.saved_at
                FROM saved_messages sm
                JOIN messages m ON sm.message_id = m.id
                JOIN users u ON m.sender_id = u.id
                WHERE sm.user_id = ?
                ORDER BY sm.saved_at DESC
                LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
