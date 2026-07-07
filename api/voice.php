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
        case 'upload':
            if (!isset($_FILES['audio'])) json_response(['success' => false, 'message' => 'no_audio'], 400);
            $file = $_FILES['audio'];
            if ($file['error'] !== UPLOAD_ERR_OK) json_response(['success' => false, 'message' => 'upload_error'], 400);
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) ?: 'webm';
            if (!in_array($ext, ['webm', 'mp3', 'ogg', 'wav', 'm4a'])) $ext = 'webm';
            $dir = UPLOAD_DIR . 'voice/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $filename = 'v_' . $uid . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $path = $dir . $filename;
            if (!move_uploaded_file($file['tmp_name'], $path)) json_response(['success' => false, 'message' => 'move_failed'], 500);
            $chatId = (int)($_POST['chat_id'] ?? 0);
            $duration = (int)($_POST['duration'] ?? 0);
            $url = UPLOAD_URL . 'voice/' . $filename;
            $check = $db->prepare("SELECT 1 FROM chat_members WHERE chat_id = ? AND user_id = ?");
            $check->execute([$chatId, $uid]);
            if (!$check->fetch()) json_response(['success' => false, 'message' => 'forbidden'], 403);
            $db->prepare("INSERT INTO messages (chat_id, sender_id, type, content, media_url, media_meta, created_at)
                VALUES (?, ?, 'voice', ?, ?, ?, NOW())")
                ->execute([$chatId, $uid, (string)$duration, $url, json_encode(['duration' => $duration, 'size' => $file['size']])]);
            $msgId = (int)$db->lastInsertId();
            $db->prepare("UPDATE chats SET updated_at = NOW() WHERE id = ?")->execute([$chatId]);
            json_response(['success' => true, 'url' => $url, 'message_id' => $msgId, 'duration' => $duration]);
            break;
        case 'list':
            $chatId = (int)($_GET['chat_id'] ?? 0);
            $check = $db->prepare("SELECT 1 FROM chat_members WHERE chat_id = ? AND user_id = ?");
            $check->execute([$chatId, $uid]);
            if (!$check->fetch()) json_response(['success' => false, 'message' => 'forbidden'], 403);
            $stmt = $db->prepare("SELECT m.*, u.display_name as sender_name FROM messages m
                LEFT JOIN users u ON u.id = m.sender_id
                WHERE m.chat_id = ? AND m.type = 'voice' AND m.is_deleted = 0
                ORDER BY m.created_at DESC LIMIT 50");
            $stmt->execute([$chatId]);
            json_response(['success' => true, 'messages' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        default:
            json_response(['success' => false, 'message' => 'unknown_action'], 400);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
