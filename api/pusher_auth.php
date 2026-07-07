<?php
define('NEXUSCHAT_API', true);
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . APP_URL);
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$uid = current_user_id();
if (!$uid) { http_response_code(401); echo json_encode(['error' => 'unauthorized']); exit; }

$channelName = $_POST['channel_name'] ?? $_GET['channel_name'] ?? '';
$socketId = $_POST['socket_id'] ?? $_GET['socket_id'] ?? '';

if (!$channelName || !$socketId) { http_response_code(400); echo json_encode(['error' => 'bad_request']); exit; }

$allowed = false;
if (preg_match('/^private-user-(\d+)$/', $channelName, $m)) {
    $allowed = ((int)$m[1]) === (int)$uid;
} elseif (preg_match('/^private-chat-(\d+)$/', $channelName, $m)) {
    $chatId = (int)$m[1];
    $stmt = Database::getInstance()->prepare("SELECT 1 FROM chat_members WHERE chat_id = ? AND user_id = ?");
    $stmt->execute([$chatId, $uid]);
    $allowed = (bool)$stmt->fetchColumn();
} elseif (preg_match('/^presence-/', $channelName)) {
    $allowed = true;
}

if (!$allowed) { http_response_code(403); echo json_encode(['error' => 'forbidden']); exit; }

$stringToSign = $socketId . ':' . $channelName;
$signature = hash_hmac('sha256', $stringToSign, PUSHER_SECRET);

$response = ['auth' => PUSHER_KEY . ':' . $signature];
if (strpos($channelName, 'presence-') === 0) {
    $response['channel_data'] = json_encode(['user_id' => (string)$uid]);
}
echo json_encode($response);
