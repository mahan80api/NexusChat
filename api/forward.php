<?php
define('NEXUSCHAT_API', true);
require_once __DIR__ . '/../config/config.php';

header('Access-Control-Allow-Origin: ' . APP_URL);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_auth();
$uid = current_user_id();
$action = $_POST['action'] ?? '';
$db = Database::getInstance();

try {
    switch ($action) {
        case 'forward':
            $messageId = (int)($_POST['message_id'] ?? 0);
            $toChats = $_POST['to_chat_ids'] ?? [];
            $stmt = $db->prepare("SELECT * FROM messages WHERE id = ?");
            $stmt->execute([$messageId]);
            $src = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$src) json_response(['success' => false, 'message' => 'not_found'], 404);
            $count = 0;
            foreach ((array)$toChats as $cid) {
                $cid = (int)$cid;
                if (!$cid) continue;
                $check = $db->prepare("SELECT 1 FROM chat_members WHERE chat_id = ? AND user_id = ?");
                $check->execute([$cid, $uid]);
                if (!$check->fetch()) continue;
                $db->prepare("INSERT INTO messages (chat_id, sender_id, type, content, media_url, forwarded_from, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())")
                    ->execute([$cid, $uid, $src['type'], $src['content'], $src['media_url'], $messageId]);
                $db->prepare("UPDATE chats SET updated_at = NOW() WHERE id = ?")->execute([$cid]);
                $count++;
            }
            json_response(['success' => true, 'forwarded' => $count]);
            break;
        default:
            json_response(['success' => false, 'message' => 'unknown_action'], 400);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
