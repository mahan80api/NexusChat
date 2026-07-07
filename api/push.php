<?php
/**
 * NexusChat - Push Notifications API
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
$push   = new PushManager();

try {
    switch ($action) {
        case 'vapid_public_key':
            json_response(['success' => true, 'publicKey' => $push->getPublicKey()]);
            break;

        case 'subscribe':
            $endpoint = $_POST['endpoint'] ?? '';
            $keys     = json_decode($_POST['keys'] ?? '{}', true);
            $device   = $_POST['device_name'] ?? '';
            $ua       = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $res = $push->subscribe($userId, $endpoint, $keys, $ua, $device);
            json_response($res);
            break;

        case 'unsubscribe':
            $endpoint = $_POST['endpoint'] ?? '';
            json_response($push->unsubscribe($userId, $endpoint));
            break;

        case 'devices':
            json_response(['success' => true, 'devices' => $push->listDevices($userId)]);
            break;

        case 'remove_device':
            $deviceId = (int)($_POST['device_id'] ?? 0);
            json_response($push->removeDevice($userId, $deviceId));
            break;

        case 'preferences':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $prefs = json_decode(file_get_contents('php://input'), true) ?: $_POST;
                json_response($push->updatePreferences($userId, $prefs));
            } else {
                json_response(['success' => true, 'preferences' => $push->getPreferences($userId), 'overrides' => $push->getChatOverrides($userId)]);
            }
            break;

        case 'mute_chat':
            $chatId  = (int)($_POST['chat_id'] ?? 0);
            $duration = $_POST['duration'] ?? null;
            $mode    = $_POST['mode'] ?? 'muted';
            json_response($push->muteChat($userId, $chatId, $duration, $mode));
            break;

        case 'unmute_chat':
            $chatId = (int)($_POST['chat_id'] ?? 0);
            json_response($push->unmuteChat($userId, $chatId));
            break;

        case 'test':
            // Send a test push
            $res = $push->sendToUser($userId, [
                'type'  => 'mention',
                'title' => '🔔 تست Push Notification',
                'body'  => 'اگر این اعلان را می‌بینید، Push درست کار می‌کند! ✨',
                'data'  => ['url' => '/chat'],
                'tag'   => 'test_' . time(),
            ]);
            json_response($res);
            break;

        case 'logs':
            $limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
            json_response(['success' => true, 'logs' => $push->getNotificationLog($userId, $limit)]);
            break;

        case 'stats':
            json_response(['success' => true, 'stats' => $push->getStats($userId)]);
            break;

        case 'mark_clicked':
            $notifId = (int)($_POST['notification_id'] ?? 0);
            $push->markClicked($userId, $notifId);
            json_response(['success' => true]);
            break;

        default:
            json_response(['success' => false, 'error' => 'unknown_action'], 400);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 400);
}
