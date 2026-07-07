<?php
/**
 * NexusChat - Search API
 * Full-text search across messages, users, chats
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
$msg    = new Message();
$chat   = new Chat();
$user   = new User();
$notif  = new Notification();

try {
    switch ($action) {

        /**
         * Global search across all user's messages
         * Filters: q, chat_id, type, date_from, date_to, sender_id, limit, offset
         */
        case 'messages':
            $query   = trim($_GET['q'] ?? $_POST['q'] ?? '');
            $chatId  = !empty($_GET['chat_id']) ? (int)$_GET['chat_id'] : null;
            $type    = $_GET['type'] ?? null;
            $sender  = !empty($_GET['sender_id']) ? (int)$_GET['sender_id'] : null;
            $dateFrom = $_GET['date_from'] ?? null;
            $dateTo   = $_GET['date_to'] ?? null;
            $limit   = min(100, max(1, (int)($_GET['limit'] ?? 50)));
            $offset  = max(0, (int)($_GET['offset'] ?? 0));

            if (mb_strlen($query) < 2 && !$chatId && !$sender && !$type && !$dateFrom) {
                json_response(['success' => true, 'results' => [], 'total' => 0]);
            }

            $results = $msg->search($userId, $query, [
                'chat_id'   => $chatId,
                'type'      => $type,
                'sender_id' => $sender,
                'date_from' => $dateFrom,
                'date_to'   => $dateTo,
                'limit'     => $limit,
                'offset'    => $offset,
            ]);

            json_response([
                'success' => true,
                'results' => $results['items'],
                'total'   => $results['total'],
                'limit'   => $limit,
                'offset'  => $offset,
            ]);
            break;

        case 'chats':
            $query = trim($_GET['q'] ?? $_POST['q'] ?? '');
            if (mb_strlen($query) < 1) {
                json_response(['success' => true, 'chats' => []]);
            }
            $chats = $chat->search($userId, $query);
            json_response(['success' => true, 'chats' => $chats]);
            break;

        case 'contacts':
            $query = trim($_GET['q'] ?? $_POST['q'] ?? '');
            $contacts = $user->searchContacts($userId, $query);
            json_response(['success' => true, 'contacts' => $contacts]);
            break;

        case 'save':
            $messageId = (int)($_POST['message_id'] ?? 0);
            if (!$messageId) throw new Exception('message_id نامعتبر');
            $saved = $msg->toggleSave($messageId, $userId);
            json_response(['success' => true, 'saved' => $saved]);
            break;

        case 'saved':
            $limit  = min(100, max(1, (int)($_GET['limit'] ?? 50)));
            $offset = max(0, (int)($_GET['offset'] ?? 0));
            $items  = $msg->getSaved($userId, $limit, $offset);
            json_response(['success' => true, 'items' => $items]);
            break;

        case 'global':
            $query = trim($_GET['q'] ?? $_POST['q'] ?? '');
            if (mb_strlen($query) < 2) {
                json_response(['success' => true, 'messages' => [], 'chats' => [], 'contacts' => []]);
            }
            $msgs     = $msg->search($userId, $query, ['limit' => 20, 'offset' => 0])['items'];
            $chats    = $chat->search($userId, $query);
            $contacts = $user->searchContacts($userId, $query);
            json_response([
                'success'  => true,
                'messages' => $msgs,
                'chats'    => $chats,
                'contacts' => $contacts,
            ]);
            break;

        default:
            json_response(['success' => false, 'error' => 'unknown_action'], 400);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 400);
}
