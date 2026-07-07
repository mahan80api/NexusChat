<?php
define('NEXUSCHAT_API', true);
require_once __DIR__ . '/../config/config.php';

header('Access-Control-Allow-Origin: ' . APP_URL);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$db = Database::getInstance();

try {
    switch ($action) {
        case 'webhook':
            $token = $_GET['token'] ?? $_POST['token'] ?? '';
            $stmt = $db->prepare("SELECT * FROM bots WHERE token = ?");
            $stmt->execute([$token]);
            $bot = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$bot) json_response(['success' => false, 'message' => 'invalid_token'], 401);
            $bm = new BotManager();
            $bm->processIncoming($bot, $_POST);
            json_response(['success' => true]);
            break;
        case 'incoming':
            require_auth();
            $botId = (int)($_POST['bot_id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM bots WHERE id = ?");
            $stmt->execute([$botId]);
            $bot = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$bot) json_response(['success' => false, 'message' => 'not_found'], 404);
            $bm = new BotManager();
            $bm->processIncoming($bot, $_POST);
            json_response(['success' => true]);
            break;
        default:
            json_response(['success' => false, 'message' => 'unknown_action'], 400);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
