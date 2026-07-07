<?php
/**
 * NexusChat - Bot Manager (helpers for bot webhooks)
 */
class BotManager {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function processCommand($bot, $command, $text, $userId) {
        $stmt = $this->db->prepare("SELECT * FROM bot_commands WHERE bot_id = ? AND command = ?");
        $stmt->execute([$bot['id'], $command]);
        $cmd = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cmd) return 'دستور یافت نشد';
        return str_replace(['{user}', '{text}'], [$userId, $text], $cmd['response']);
    }

    public function processIncoming($bot, $data) {
        // Hook for external bot webhooks
        if (isset($data['text']) && isset($data['chat_id']) && $bot['bot_user_id']) {
            $response = $this->processCommand($bot, $data['command'] ?? '', $data['text'], $data['user_id'] ?? 0);
            $this->db->prepare("INSERT INTO messages (chat_id, sender_id, type, content, created_at) VALUES (?, ?, 'text', ?, NOW())")
                ->execute([(int)$data['chat_id'], $bot['bot_user_id'], $response]);
        }
    }
}
