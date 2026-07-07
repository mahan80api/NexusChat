<?php
/**
 * NexusChat - Chats API
 */
define('NEXUSCHAT_API', true);
require_once __DIR__ . '/../config/config.php';

header('Access-Control-Allow-Origin: ' . APP_URL);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_auth();
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$userId = current_user_id();
$db = Database::getInstance();

function ensureMember($chatId, $userId) {
    static $cache = [];
    $key = $chatId . '_' . $userId;
    if (isset($cache[$key])) return;
    $stmt = Database::getInstance()->prepare("SELECT 1 FROM chat_members WHERE chat_id = ? AND user_id = ?");
    $stmt->execute([$chatId, $userId]);
    if (!$stmt->fetch()) json_response(['success' => false, 'message' => 'forbidden'], 403);
    $cache[$key] = true;
}

function broadcastNewMessage($chatId, $msg) {
    pusher_trigger('private-chat-' . $chatId, 'new-message', ['chat_id' => (int)$chatId, 'message' => $msg]);
    $stmt = Database::getInstance()->prepare("SELECT user_id FROM chat_members WHERE chat_id = ? AND user_id != ?");
    $stmt->execute([$chatId, $msg['sender_id']]);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        pusher_trigger('private-user-' . $r['user_id'], 'new-message', ['chat_id' => (int)$chatId, 'message' => $msg]);
    }
}

try {
    switch ($action) {
        case 'list':
            $stmt = $db->prepare("
                SELECT c.*, cm.unread_count,
                    (SELECT content FROM messages WHERE chat_id = c.id AND is_deleted = 0 ORDER BY created_at DESC LIMIT 1) as last_message,
                    (SELECT created_at FROM messages WHERE chat_id = c.id AND is_deleted = 0 ORDER BY created_at DESC LIMIT 1) as last_message_time,
                    u.id as other_id, u.display_name as other_display_name, u.avatar as other_avatar,
                    u.username as other_username, u.is_online as other_online, u.last_seen as other_last_seen
                FROM chats c
                JOIN chat_members cm ON cm.chat_id = c.id AND cm.user_id = ?
                LEFT JOIN chat_members cm2 ON cm2.chat_id = c.id AND cm2.user_id != ?
                LEFT JOIN users u ON u.id = cm2.user_id
                WHERE c.type IN ('private', 'group', 'channel', 'bot')
                ORDER BY c.updated_at DESC LIMIT 100
            ");
            $stmt->execute([$userId, $userId]);
            $chats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($chats as &$c) {
                $c['id'] = (int)$c['id'];
                $c['unread_count'] = (int)$c['unread_count'];
                $c['chat_name'] = $c['name'] ?: $c['other_display_name'];
            }
            json_response(['success' => true, 'chats' => $chats]);
            break;

        case 'messages':
            $chatId = (int)($_GET['chat_id'] ?? 0);
            $limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
            $before = (int)($_GET['before'] ?? 0);
            ensureMember($chatId, $userId);
            $sql = "SELECT m.*, u.display_name as sender_name, u.avatar as sender_avatar
                FROM messages m
                LEFT JOIN users u ON u.id = m.sender_id
                WHERE m.chat_id = ? AND m.is_deleted = 0";
            $params = [$chatId];
            if ($before) { $sql .= " AND m.id < ?"; $params[] = $before; }
            $sql .= " ORDER BY m.created_at DESC LIMIT " . (int)$limit;
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $msgs = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
            foreach ($msgs as &$m) $m['id'] = (int)$m['id'];
            json_response(['success' => true, 'messages' => $msgs]);
            break;

        case 'send':
            $chatId = (int)($_POST['chat_id'] ?? 0);
            $content = $_POST['content'] ?? '';
            $type = $_POST['type'] ?? 'text';
            $replyTo = (int)($_POST['reply_to_id'] ?? 0) ?: null;
            $mediaUrl = $_POST['media_url'] ?? null;
            ensureMember($chatId, $userId);
            if (!in_array($type, ['text','image','video','audio','voice','file','location','sticker','poll','contact'])) {
                json_response(['success' => false, 'message' => 'invalid_type'], 400);
            }
            if ($type === 'text' && !$content) json_response(['success' => false, 'message' => 'empty_message'], 400);
            rate_limit('msg_' . $userId, RATE_LIMIT_MESSAGES);
            $stmt = $db->prepare("INSERT INTO messages (chat_id, sender_id, type, content, media_url, reply_to_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$chatId, $userId, $type, $content, $mediaUrl, $replyTo]);
            $msgId = $db->lastInsertId();
            $db->prepare("UPDATE chats SET updated_at = NOW() WHERE id = ?")->execute([$chatId]);
            $stmt = $db->prepare("SELECT m.*, u.display_name as sender_name, u.avatar as sender_avatar
                FROM messages m LEFT JOIN users u ON u.id = m.sender_id WHERE m.id = ?");
            $stmt->execute([$msgId]);
            $msg = $stmt->fetch(PDO::FETCH_ASSOC);
            $msg['id'] = (int)$msg['id'];
            broadcastNewMessage($chatId, $msg);
            json_response(['success' => true, 'message' => $msg]);
            break;

        case 'read':
            $chatId = (int)($_POST['chat_id'] ?? 0);
            ensureMember($chatId, $userId);
            $db->prepare("UPDATE chat_members SET unread_count = 0, last_read_message_id = (SELECT MAX(id) FROM messages WHERE chat_id = ?)
                WHERE chat_id = ? AND user_id = ?")->execute([$chatId, $chatId, $userId]);
            $db->prepare("UPDATE messages SET is_read = 1 WHERE chat_id = ? AND sender_id != ?")->execute([$chatId, $userId]);
            json_response(['success' => true]);
            break;

        case 'typing':
            $chatId = (int)($_POST['chat_id'] ?? 0);
            ensureMember($chatId, $userId);
            pusher_trigger('private-chat-' . $chatId, 'typing', ['user_id' => $userId, 'chat_id' => $chatId]);
            json_response(['success' => true]);
            break;

        case 'react':
            $msgId = (int)($_POST['message_id'] ?? 0);
            $emoji = substr($_POST['emoji'] ?? '', 0, 8);
            if (!$emoji) json_response(['success' => false, 'message' => 'no_emoji'], 400);
            $stmt = $db->prepare("SELECT reactions FROM messages WHERE id = ?");
            $stmt->execute([$msgId]);
            $m = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$m) json_response(['success' => false, 'message' => 'not_found'], 404);
            $reactions = $m['reactions'] ? json_decode($m['reactions'], true) : [];
            $list = $reactions[$emoji] ?? [];
            if (in_array($userId, $list)) {
                $list = array_diff($list, [$userId]);
                if (empty($list)) unset($reactions[$emoji]);
                else $reactions[$emoji] = array_values($list);
            } else {
                $list[] = $userId;
                $reactions[$emoji] = $list;
            }
            $db->prepare("UPDATE messages SET reactions = ? WHERE id = ?")
                ->execute([json_encode($reactions), $msgId]);
            json_response(['success' => true, 'reactions' => $reactions]);
            break;

        case 'edit':
            $msgId = (int)($_POST['message_id'] ?? 0);
            $content = sanitize($_POST['content'] ?? '');
            $stmt = $db->prepare("SELECT sender_id FROM messages WHERE id = ?");
            $stmt->execute([$msgId]);
            $m = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$m || $m['sender_id'] != $userId) json_response(['success' => false, 'message' => 'forbidden'], 403);
            $db->prepare("UPDATE messages SET content = ?, is_edited = 1, updated_at = NOW() WHERE id = ?")
                ->execute([$content, $msgId]);
            json_response(['success' => true]);
            break;

        case 'delete':
            $msgId = (int)($_POST['message_id'] ?? 0);
            $stmt = $db->prepare("SELECT sender_id FROM messages WHERE id = ?");
            $stmt->execute([$msgId]);
            $m = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$m || $m['sender_id'] != $userId) json_response(['success' => false, 'message' => 'forbidden'], 403);
            $db->prepare("UPDATE messages SET is_deleted = 1 WHERE id = ?")->execute([$msgId]);
            json_response(['success' => true]);
            break;

        case 'create_private':
            $otherId = (int)($_POST['user_id'] ?? 0);
            if (!$otherId || $otherId == $userId) json_response(['success' => false, 'message' => 'invalid_user'], 400);
            $stmt = $db->prepare("SELECT c.id FROM chats c
                JOIN chat_members cm1 ON cm1.chat_id = c.id AND cm1.user_id = ?
                JOIN chat_members cm2 ON cm2.chat_id = c.id AND cm2.user_id = ?
                WHERE c.type = 'private' LIMIT 1");
            $stmt->execute([$userId, $otherId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) { json_response(['success' => true, 'chat_id' => (int)$existing['id']]); }
            $db->beginTransaction();
            $db->prepare("INSERT INTO chats (type, created_at) VALUES ('private', NOW())")->execute();
            $chatId = (int)$db->lastInsertId();
            $db->prepare("INSERT INTO chat_members (chat_id, user_id, role) VALUES (?, ?, 'member'), (?, ?, 'member')")
                ->execute([$chatId, $userId, $chatId, $otherId]);
            $db->commit();
            json_response(['success' => true, 'chat_id' => $chatId]);
            break;

        case 'create_group':
            $name = sanitize($_POST['name'] ?? '');
            $members = $_POST['members'] ?? [];
            if (!$name) json_response(['success' => false, 'message' => 'no_name'], 400);
            $db->beginTransaction();
            $db->prepare("INSERT INTO chats (type, name, owner_id) VALUES ('group', ?, ?)")->execute([$name, $userId]);
            $chatId = (int)$db->lastInsertId();
            $db->prepare("INSERT INTO chat_members (chat_id, user_id, role) VALUES (?, ?, 'owner')")->execute([$chatId, $userId]);
            $ins = $db->prepare("INSERT INTO chat_members (chat_id, user_id) VALUES (?, ?)");
            foreach ((array)$members as $mid) {
                $mid = (int)$mid;
                if ($mid && $mid != $userId) $ins->execute([$chatId, $mid]);
            }
            $db->commit();
            json_response(['success' => true, 'chat_id' => $chatId]);
            break;

        case 'forward':
            $msgId = (int)($_POST['message_id'] ?? 0);
            $toChatId = (int)($_POST['to_chat_id'] ?? 0);
            ensureMember($toChatId, $userId);
            $stmt = $db->prepare("SELECT * FROM messages WHERE id = ?");
            $stmt->execute([$msgId]);
            $src = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$src) json_response(['success' => false, 'message' => 'not_found'], 404);
            $db->prepare("INSERT INTO messages (chat_id, sender_id, type, content, media_url, forwarded_from, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())")
                ->execute([$toChatId, $userId, $src['type'], $src['content'], $src['media_url'], $msgId]);
            json_response(['success' => true]);
            break;

        case 'search':
            $q = sanitize($_GET['q'] ?? '');
            $chatId = (int)($_GET['chat_id'] ?? 0);
            if (!$q) json_response(['success' => true, 'messages' => []]);
            if ($chatId) ensureMember($chatId, $userId);
            $sql = "SELECT m.*, u.display_name as sender_name FROM messages m
                LEFT JOIN users u ON u.id = m.sender_id
                WHERE m.is_deleted = 0 AND m.content LIKE ?";
            $params = ['%' . $q . '%'];
            if ($chatId) { $sql .= " AND m.chat_id = ?"; $params[] = $chatId; }
            $sql .= " ORDER BY m.created_at DESC LIMIT 50";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            json_response(['success' => true, 'messages' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        default:
            json_response(['success' => false, 'message' => 'unknown_action'], 400);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
