<?php
/**
 * NexusChat - Chats API
 */
define('NEXUSCHAT_API', true);
require_once __DIR__ . '/../config/config.php';

header('Access-Control-Allow-Origin: ' . APP_URL);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_auth();
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$userId = current_user_id();
$chat   = new Chat();
$user   = new User();
$msg    = new Message();

try {
    switch ($action) {

        case 'list':
            $chats = $chat->getUserChats($userId);
            foreach ($chats as &$c) {
                if ($c['type'] === 'private') {
                    $members = $chat->getMembers($c['id']);
                    foreach ($members as $m) {
                        if ($m['id'] != $userId) {
                            $c['other_user'] = [
                                'id'           => $m['id'],
                                'username'     => $m['username'],
                                'display_name' => $m['display_name'],
                                'avatar'       => $m['avatar'],
                                'is_online'    => $m['is_online'],
                            ];
                            if (!$c['name']) $c['name'] = $m['display_name'];
                            if (!$c['avatar']) $c['avatar'] = $m['avatar'];
                            break;
                        }
                    }
                }
                $c['last_message_ago'] = $c['last_message_time'] ? time_ago($c['last_message_time']) : '';
            }
            unset($c);
            json_response(['success' => true, 'chats' => $chats]);
            break;

        case 'info':
            $chatId = (int)($_GET['chat_id'] ?? 0);
            if (!$chat->isMember($chatId, $userId)) {
                throw new Exception('دسترسی غیرمجاز');
            }
            $info = $chat->findById($chatId);
            $info['members'] = $chat->getMembers($chatId);
            json_response(['success' => true, 'chat' => $info]);
            break;

        case 'create_private':
            $otherUserId = (int)($_POST['user_id'] ?? 0);
            if (!$otherUserId || $otherUserId == $userId) {
                throw new Exception('کاربر نامعتبر');
            }
            $newChat = $chat->getOrCreatePrivate($userId, $otherUserId);
            json_response(['success' => true, 'chat' => $newChat]);
            break;

        case 'create_group':
            $name = sanitize($_POST['name'] ?? '');
            $description = sanitize($_POST['description'] ?? '');
            $members = $_POST['members'] ?? [];
            if (!is_array($members)) $members = [];

            $newChat = $chat->createGroup($userId, $name, $description, $members);
            json_response(['success' => true, 'chat' => $newChat]);
            break;

        case 'create_channel':
            $name = sanitize($_POST['name'] ?? '');
            $description = sanitize($_POST['description'] ?? '');
            $newChat = $chat->createChannel($userId, $name, $description);
            json_response(['success' => true, 'chat' => $newChat]);
            break;

        case 'add_member':
            $chatId = (int)($_POST['chat_id'] ?? 0);
            $newUserId = (int)($_POST['user_id'] ?? 0);
            if (!$chat->isMember($chatId, $userId)) {
                throw new Exception('دسترسی غیرمجاز');
            }
            $chat->addMember($chatId, $newUserId);
            json_response(['success' => true]);
            break;

        case 'remove_member':
            $chatId = (int)($_POST['chat_id'] ?? 0);
            $rmUserId = (int)($_POST['user_id'] ?? 0);
            $chat->removeMember($chatId, $rmUserId);
            json_response(['success' => true]);
            break;

        case 'read':
            $chatId = (int)($_POST['chat_id'] ?? 0);
            $lastId = (int)($_POST['last_message_id'] ?? 0);
            $chat->markAsRead($chatId, $userId, $lastId);
            json_response(['success' => true]);
            break;

        case 'toggle_pin':
            $chatId = (int)($_POST['chat_id'] ?? 0);
            $pinned = $chat->togglePin($chatId, $userId);
            json_response(['success' => true, 'pinned' => $pinned]);
            break;

        case 'toggle_mute':
            $chatId = (int)($_POST['chat_id'] ?? 0);
            $muted = $chat->toggleMute($chatId, $userId);
            json_response(['success' => true, 'muted' => $muted]);
            break;

        case 'typing':
            // Just acknowledge - real typing indicator stored in session/cache
            json_response(['success' => true]);
            break;

        default:
            json_response(['success' => false, 'error' => 'unknown_action'], 400);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 400);
}
