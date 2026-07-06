<?php
/**
 * NexusChat - Real-time Polling API
 * Long polling endpoint for new messages & updates
 */
define('NEXUSCHAT_API', true);
require_once __DIR__ . '/../config/config.php';

header('Access-Control-Allow-Origin: ' . APP_URL);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_auth();
$userId = current_user_id();
$msg    = new Message();
$chat   = new Chat();
$notif  = new Notification();
$user   = new User();

// Disable time limit for long polling
set_time_limit(60);

$chatId   = (int)($_GET['chat_id'] ?? 0);
$lastId   = (int)($_GET['last_id'] ?? 0);
$timeout  = 25; // seconds
$start    = time();

if (!$chatId || !$chat->isMember($chatId, $userId)) {
    json_response(['success' => false, 'error' => 'unauthorized'], 403);
}

while (true) {
    $new = $msg->getNewMessages($chatId, $lastId);
    if (!empty($new)) {
        $unread = $notif->unreadCount($userId);
        json_response([
            'success'     => true,
            'new_messages'=> $new,
            'unread'      => $unread,
        ]);
    }

    if ((time() - $start) >= $timeout) {
        // Timeout - return empty
        json_response([
            'success'       => true,
            'new_messages'  => [],
            'timeout'       => true,
        ]);
    }

    // Sleep briefly to avoid hammering DB
    usleep(500000); // 0.5s
}
