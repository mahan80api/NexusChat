<?php
/**
 * NexusChat - Messages API
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

try {
    switch ($action) {

        case 'send':
            $chatId = (int)($_POST['chat_id'] ?? 0);
            $content = $_POST['content'] ?? '';
            $type   = $_POST['type'] ?? 'text';
            $replyTo = !empty($_POST['reply_to_id']) ? (int)$_POST['reply_to_id'] : null;
            $filePath = $_POST['file_path'] ?? null;
            $fileSize = !empty($_POST['file_size']) ? (int)$_POST['file_size'] : null;
            $mime     = $_POST['mime_type'] ?? null;
            $isEncrypted = !empty($_POST['is_encrypted']) ? 1 : 0;
            $encryptedContent = $_POST['encrypted_content'] ?? null;

            if (!$chatId || !$chat->isMember($chatId, $userId)) {
                throw new Exception('دسترسی غیرمجاز');
            }
            if (empty($content) && empty($filePath)) {
                throw new Exception('پیام خالی است');
            }

            $message = $msg->send($chatId, $userId, $content, [
                'type'              => $type,
                'reply_to_id'       => $replyTo,
                'file_path'         => $filePath,
                'file_size'         => $fileSize,
                'mime_type'         => $mime,
                'is_encrypted'      => $isEncrypted,
                'encrypted_content' => $encryptedContent,
            ]);

            // Notify other members
            $members = $chat->getMembers($chatId);
            $sender = $user->getPublicProfile($userId);
            $notif = new Notification();
            foreach ($members as $m) {
                if ($m['id'] != $userId) {
                    $notif->create(
                        $m['id'],
                        'message',
                        $sender['display_name'],
                        mb_substr($content ?: '[فایل]', 0, 100),
                        $chatId
                    );
                }
            }

            json_response(['success' => true, 'message' => $message]);
            break;

        case 'list':
            $chatId = (int)($_GET['chat_id'] ?? 0);
            $limit  = min(100, max(1, (int)($_GET['limit'] ?? 50)));
            $before = !empty($_GET['before_id']) ? (int)$_GET['before_id'] : null;

            if (!$chatId || !$chat->isMember($chatId, $userId)) {
                throw new Exception('دسترسی غیرمجاز');
            }
            $messages = $msg->getMessages($chatId, $limit, $before);

            // Attach reply info
            foreach ($messages as &$m) {
                if ($m['reply_to_id']) {
                    $reply = $msg->findById($m['reply_to_id']);
                    $m['reply_to'] = $reply ? [
                        'id'           => $reply['id'],
                        'content'      => $reply['is_deleted'] ? null : $reply['content'],
                        'sender_name'  => $user->findById($reply['sender_id'])['display_name'] ?? '',
                    ] : null;
                }
                $m['reactions'] = $msg->getReactions($m['id']);
            }
            unset($m);

            json_response(['success' => true, 'messages' => $messages]);
            break;

        case 'edit':
            $messageId = (int)($_POST['message_id'] ?? 0);
            $newContent = $_POST['content'] ?? '';
            if (!$msg->edit($messageId, $userId, $newContent)) {
                throw new Exception('ویرایش ناموفق');
            }
            json_response(['success' => true]);
            break;

        case 'delete':
            $messageId = (int)($_POST['message_id'] ?? 0);
            if (!$msg->delete($messageId, $userId)) {
                throw new Exception('حذف ناموفق');
            }
            json_response(['success' => true]);
            break;

        case 'react':
            $messageId = (int)($_POST['message_id'] ?? 0);
            $emoji     = $_POST['emoji'] ?? '';
            if (mb_strlen($emoji) > 10) {
                throw new Exception('ایموجی نامعتبر');
            }
            $added = $msg->toggleReaction($messageId, $userId, $emoji);
            json_response(['success' => true, 'added' => $added, 'reactions' => $msg->getReactions($messageId)]);
            break;

        case 'pin':
            $messageId = (int)($_POST['message_id'] ?? 0);
            if (!$msg->pin($messageId, $userId)) {
                throw new Exception('سنجاق کردن ناموفق');
            }
            json_response(['success' => true]);
            break;

        default:
            json_response(['success' => false, 'error' => 'unknown_action'], 400);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 400);
}
