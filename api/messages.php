<?php
/**
 * NexusChat - Messages API (extension with bot processing)
 */
define('NEXUSCHAT_API', true);
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . APP_URL);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_auth();
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$userId = current_user_id();
$msg    = new MessageManager();
$bot    = new BotManager();

try {
    switch ($action) {
        case 'send':
            $chatId = (int)$_POST['chat_id'];
            $text = trim($_POST['content'] ?? '');
            $file = $_FILES['file'] ?? null;
            $type = $_POST['type'] ?? 'text';

            // Validate chat membership
            if (!is_chat_member($chatId, $userId)) throw new Exception('not_member');

            // Upload file
            $filePath = null;
            $fileSize = null;
            if ($file && $file['error'] === UPLOAD_ERR_OK) {
                $dir = 'assets/uploads/' . ($type === 'voice' ? 'voice' : ($type === 'image' ? 'images' : 'files')) . '/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $name = uniqid() . ($ext ? '.' . $ext : '');
                $filePath = ($type === 'voice' ? 'voice' : ($type === 'image' ? 'images' : 'files')) . '/' . $name;
                move_uploaded_file($file['tmp_name'], $dir . $name);
                $fileSize = $file['size'];
            }

            $stmt = msg_db()->prepare("INSERT INTO messages
                (chat_id, sender_id, type, content, file_path, file_size, reply_to_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $replyTo = !empty($_POST['reply_to_id']) ? (int)$_POST['reply_to_id'] : null;
            $stmt->execute([$chatId, $userId, $type, $text, $filePath, $fileSize, $replyTo]);
            $messageId = msg_db()->lastInsertId();
            msg_db()->prepare("UPDATE chats SET last_message_at = NOW(), last_message_preview = ? WHERE id = ?")
                    ->execute([mb_substr($text ?: '[media]', 0, 100), $chatId]);

            $message = [
                'id' => $messageId, 'chat_id' => $chatId, 'sender_id' => $userId,
                'type' => $type, 'content' => $text, 'file_path' => $filePath,
                'reply_to_id' => $replyTo, 'created_at' => date('Y-m-d H:i:s'),
            ];

            // Process bot commands if message starts with /
            $botResponses = [];
            if ($type === 'text' && $text && $text[0] === '/') {
                $fullMessage = $message + [
                    'sender_name' => current_user_display_name(),
                    'chat_name'   => '',
                ];
                $botResponses = $bot->processMessage($fullMessage);
            }

            // Fire message hook
            $bot->fireHook('message', [
                'chat_id' => $chatId, 'text' => $text, 'sender_id' => $userId,
            ]);

            json_response(['success' => true, 'message' => $message, 'bot_responses' => $botResponses]);
            break;

        // ====== List messages ======
        case 'list':
            $chatId = (int)$_GET['chat_id'];
            $limit = min(100, (int)($_GET['limit'] ?? 30));
            $offset = (int)($_GET['offset'] ?? 0);
            if (!is_chat_member($chatId, $userId)) throw new Exception('not_member');
            $stmt = msg_db()->prepare("SELECT m.*, u.display_name as sender_name, u.username, u.avatar as sender_avatar,
                b.name as bot_name, b.username as bot_username, b.avatar as bot_avatar,
                (SELECT JSON_OBJECT('id', m2.id, 'content', m2.content, 'sender_name', u2.display_name)
                 FROM messages m2 JOIN users u2 ON u2.id = m2.sender_id WHERE m2.id = m.reply_to_id) as reply_to
                FROM messages m
                LEFT JOIN users u ON u.id = m.sender_id
                LEFT JOIN bots b ON b.id = m.bot_id
                WHERE m.chat_id = ? ORDER BY m.created_at DESC LIMIT ? OFFSET ?");
            $stmt->execute([$chatId, $limit, $offset]);
            $rows = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
            foreach ($rows as &$r) {
                if ($r['reply_to']) $r['reply_to'] = json_decode($r['reply_to'], true);
                if ($r['metadata']) $r['metadata'] = json_decode($r['metadata'], true);
                if ($r['reactions_json']) $r['reactions'] = json_decode($r['reactions_json'], true);
            }
            json_response(['success' => true, 'messages' => $rows]);
            break;

        // ====== Other actions preserved ======
        case 'react':
            $messageId = (int)$_POST['message_id'];
            $emoji = $_POST['emoji'];
            $stmt = msg_db()->prepare("SELECT reactions FROM messages WHERE id = ?");
            $stmt->execute([$messageId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $reactions = $row ? (json_decode($row['reactions'] ?? '[]', true) ?: []) : [];
            $found = false;
            foreach ($reactions as &$r) {
                if ($r['emoji'] === $emoji) {
                    $idx = array_search($userId, $r['user_ids']);
                    if ($idx !== false) {
                        array_splice($r['user_ids'], $idx, 1);
                        $r['count'] = max(0, $r['count'] - 1);
                    } else {
                        $r['user_ids'][] = $userId;
                        $r['count']++;
                    }
                    $found = true;
                    break;
                }
            }
            if (!$found) $reactions[] = ['emoji' => $emoji, 'user_ids' => [$userId], 'count' => 1];
            $reactions = array_values(array_filter($reactions, fn($r) => $r['count'] > 0));
            msg_db()->prepare("UPDATE messages SET reactions = ? WHERE id = ?")
                    ->execute([json_encode($reactions, JSON_UNESCAPED_UNICODE), $messageId]);
            json_response(['success' => true]);
            break;

        case 'delete':
            $messageId = (int)$_POST['message_id'];
            msg_db()->prepare("DELETE FROM messages WHERE id = ? AND sender_id = ?")->execute([$messageId, $userId]);
            json_response(['success' => true]);
            break;

        case 'pin':
            $messageId = (int)$_POST['message_id'];
            msg_db()->prepare("UPDATE messages SET is_pinned = NOT is_pinned WHERE id = ?")->execute([$messageId]);
            json_response(['success' => true]);
            break;

        case 'forward':
            $messageId = (int)$_POST['message_id'];
            $toChatIds = json_decode($_POST['to_chat_ids'] ?? '[]', true);
            $results = [];
            $msgStmt = msg_db()->prepare("SELECT * FROM messages WHERE id = ?");
            $msgStmt->execute([$messageId]);
            $original = $msgStmt->fetch(PDO::FETCH_ASSOC);
            if (!$original) throw new Exception('message_not_found');
            $insertStmt = msg_db()->prepare("INSERT INTO messages
                (chat_id, sender_id, type, content, file_path, file_size, forward_from_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            foreach ($toChatIds as $toChatId) {
                $toChatId = (int)$toChatId;
                if (!is_chat_member($toChatId, $userId)) { $results[] = ['chat_id' => $toChatId, 'ok' => false]; continue; }
                $insertStmt->execute([$toChatId, $userId, $original['type'], $original['content'],
                    $original['file_path'], $original['file_size'], $original['id']]);
                $newId = msg_db()->lastInsertId();
                $results[] = ['chat_id' => $toChatId, 'ok' => true, 'new_message_id' => $newId];
            }
            json_response(['success' => true, 'forwarded' => $results]);
            break;

        default:
            json_response(['success' => false, 'message' => 'unknown_action'], 400);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
