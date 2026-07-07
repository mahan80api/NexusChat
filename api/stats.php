<?php
define('NEXUSCHAT_API', true);
require_once __DIR__ . '/../config/config.php';

header('Access-Control-Allow-Origin: ' . APP_URL);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_auth();
$uid = current_user_id();
$action = $_GET['action'] ?? '';
$db = Database::getInstance();

try {
    switch ($action) {
        case 'me':
            $stmt = $db->prepare("SELECT
                (SELECT COUNT(*) FROM messages WHERE sender_id = ? AND is_deleted = 0) as sent,
                (SELECT COUNT(*) FROM messages WHERE sender_id = ? AND is_deleted = 0 AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)) as sent_week,
                (SELECT COUNT(*) FROM messages WHERE sender_id = ? AND is_deleted = 0 AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)) as sent_today,
                (SELECT COUNT(*) FROM chat_members WHERE user_id = ?) as chats_count,
                (SELECT COUNT(*) FROM chat_members cm JOIN chats c ON c.id = cm.chat_id WHERE cm.user_id = ? AND cm.unread_count > 0) as unread_chats,
                (SELECT MAX(created_at) FROM messages WHERE sender_id = ?) as last_message_at,
                (SELECT COUNT(DISTINCT chat_id) FROM messages WHERE sender_id = ?) as active_chats");
            $stmt->execute([$uid, $uid, $uid, $uid, $uid, $uid, $uid]);
            json_response(['success' => true, 'stats' => $stmt->fetch(PDO::FETCH_ASSOC)]);
            break;

        case 'chat':
            $chatId = (int)($_GET['chat_id'] ?? 0);
            $stmt = $db->prepare("SELECT 1 FROM chat_members WHERE chat_id = ? AND user_id = ?");
            $stmt->execute([$chatId, $uid]);
            if (!$stmt->fetch()) json_response(['success' => false, 'message' => 'forbidden'], 403);
            $stmt = $db->prepare("SELECT
                COUNT(*) as total,
                COUNT(DISTINCT sender_id) as participants,
                SUM(CASE WHEN type = 'text' THEN 1 ELSE 0 END) as text,
                SUM(CASE WHEN type = 'image' THEN 1 ELSE 0 END) as images,
                SUM(CASE WHEN type = 'video' THEN 1 ELSE 0 END) as videos,
                SUM(CASE WHEN type = 'voice' THEN 1 ELSE 0 END) as voices,
                SUM(CASE WHEN type = 'file' THEN 1 ELSE 0 END) as files,
                MIN(created_at) as first_msg,
                MAX(created_at) as last_msg
                FROM messages WHERE chat_id = ? AND is_deleted = 0");
            $stmt->execute([$chatId]);
            json_response(['success' => true, 'stats' => $stmt->fetch(PDO::FETCH_ASSOC)]);
            break;

        case 'activity':
            $days = min(90, max(1, (int)($_GET['days'] ?? 30)));
            $stmt = $db->prepare("SELECT DATE(created_at) as date, COUNT(*) as count
                FROM messages WHERE sender_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? DAY) AND is_deleted = 0
                GROUP BY DATE(created_at) ORDER BY date ASC");
            $stmt->execute([$uid, $days]);
            json_response(['success' => true, 'activity' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'top_chats':
            $stmt = $db->prepare("SELECT c.id, c.name, c.type,
                (SELECT COUNT(*) FROM messages m WHERE m.chat_id = c.id AND m.sender_id = ? AND m.is_deleted = 0) as my_count
                FROM chats c JOIN chat_members cm ON cm.chat_id = c.id AND cm.user_id = ?
                ORDER BY my_count DESC LIMIT 10");
            $stmt->execute([$uid, $uid]);
            json_response(['success' => true, 'chats' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'overview':
            $u = current_user();
            $isAdmin = ($u['role'] ?? 'user') === 'admin';
            $data = ['is_admin' => $isAdmin];
            $stmt = $db->prepare("SELECT
                (SELECT COUNT(*) FROM users) as total_users,
                (SELECT COUNT(*) FROM users WHERE is_online = 1) as online_users,
                (SELECT COUNT(*) FROM chats) as total_chats,
                (SELECT COUNT(*) FROM messages WHERE is_deleted = 0) as total_messages");
            $stmt->execute();
            $data['global'] = $stmt->fetch(PDO::FETCH_ASSOC);
            json_response(['success' => true] + $data);
            break;

        default:
            json_response(['success' => false, 'message' => 'unknown_action'], 400);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
