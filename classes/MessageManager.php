<?php
/**
 * NexusChat - Message Manager (extension)
 */
require_once __DIR__ . '/Database.php';

class MessageManager {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Send a message as a bot (used for command responses)
     */
    public function sendBotMessage($botId, $chatId, $content, $replyTo = null) {
        $type = $content['type'] ?? 'text';
        $text = $content['text'] ?? '';
        $extra = $content;
        unset($extra['text'], $extra['type']);

        $stmt = $this->db->prepare("INSERT INTO messages
            (chat_id, sender_id, bot_id, type, content, metadata, reply_to_id, created_at)
            VALUES (?, (SELECT owner_id FROM bots WHERE id=?), ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $chatId, $botId, $botId, $type, $text,
            json_encode($extra, JSON_UNESCAPED_UNICODE), $replyTo,
        ]);
        $id = $this->db->lastInsertId();
        return ['id' => $id, 'bot_id' => $botId, 'type' => $type, 'content' => $text, 'chat_id' => $chatId];
    }
}
