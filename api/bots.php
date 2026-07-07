<?php
/**
 * NexusChat - Bots API
 */
define('NEXUSCHAT_API', true);
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . APP_URL);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Bot-Token');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$userId = current_user_id();
$bot    = new BotManager();

try {
    switch ($action) {
        // ===== Bot management =====
        case 'create':
            $name = $_POST['name'] ?? '';
            $username = $_POST['username'] ?? '';
            $description = $_POST['description'] ?? '';
            $res = $bot->createBot($userId, $name, $username, $description);
            json_response(['success' => true, 'bot' => $res]);
            break;

        case 'my_bots':
            json_response(['success' => true, 'bots' => $bot->listBots($userId)]);
            break;

        case 'browse':
            $q = $_GET['q'] ?? '';
            $bots = $bot->browsePublicBots();
            if ($q) $bots = array_filter($bots, fn($b) => stripos($b['name'] . $b['username'] . $b['description'], $q) !== false);
            json_response(['success' => true, 'bots' => array_values($bots)]);
            break;

        case 'info':
            $botId = (int)($_GET['bot_id'] ?? 0);
            $info = $bot->getBotById($botId);
            if ($info) {
                $info['commands'] = $bot->listCommands($botId);
                $info['stats'] = $bot->getStats($botId, 30);
                // Hide token from non-owners
                if ($info['owner_id'] != $userId) unset($info['token']);
            }
            json_response(['success' => true, 'bot' => $info]);
            break;

        case 'update':
            $botId = (int)($_POST['bot_id'] ?? 0);
            $data = [];
            foreach (['name', 'description', 'is_public', 'avatar'] as $f) {
                if (isset($_POST[$f])) $data[$f] = $_POST[$f];
            }
            $bot->updateBot($botId, $userId, $data);
            json_response(['success' => true]);
            break;

        case 'delete':
            $botId = (int)($_POST['bot_id'] ?? 0);
            $bot->deleteBot($botId, $userId);
            json_response(['success' => true]);
            break;

        case 'regenerate_token':
            $botId = (int)($_POST['bot_id'] ?? 0);
            $token = $bot->regenerateToken($botId, $userId);
            json_response(['success' => true, 'token' => $token]);
            break;

        case 'install_builtins':
            $bot->installBuiltinBots($userId);
            json_response(['success' => true]);
            break;

        // ===== Commands =====
        case 'add_command':
            $botId = (int)($_POST['bot_id'] ?? 0);
            $command = $_POST['command'] ?? '';
            $description = $_POST['description'] ?? '';
            $response = json_decode($_POST['response'] ?? '{}', true);
            $id = $bot->addCommand($botId, $command, $description, $response, !empty($_POST['is_inline']));
            json_response(['success' => true, 'command_id' => $id]);
            break;

        case 'list_commands':
            $botId = (int)($_GET['bot_id'] ?? 0);
            json_response(['success' => true, 'commands' => $bot->listCommands($botId)]);
            break;

        case 'delete_command':
            $cmdId = (int)($_POST['command_id'] ?? 0);
            $botId = (int)($_POST['bot_id'] ?? 0);
            $bot->deleteCommand($cmdId, $botId);
            json_response(['success' => true]);
            break;

        // ===== Installations =====
        case 'install':
            $botId = (int)($_POST['bot_id'] ?? 0);
            $chatId = !empty($_POST['chat_id']) ? (int)$_POST['chat_id'] : null;
            $bot->installBot($botId, $userId, $chatId);
            json_response(['success' => true]);
            break;

        case 'uninstall':
            $botId = (int)($_POST['bot_id'] ?? 0);
            $chatId = !empty($_POST['chat_id']) ? (int)$_POST['chat_id'] : null;
            $bot->uninstallBot($botId, $userId, $chatId);
            json_response(['success' => true]);
            break;

        case 'installed':
            json_response(['success' => true, 'bots' => $bot->getInstalledBots($userId)]);
            break;

        case 'chat_bots':
            $chatId = (int)($_GET['chat_id'] ?? 0);
            json_response(['success' => true, 'bots' => $bot->getChatBots($chatId)]);
            break;

        // ===== Webhook (incoming updates from external bots) =====
        case 'webhook':
            $token = $_SERVER['HTTP_X_BOT_TOKEN'] ?? $_POST['token'] ?? '';
            $botInfo = $bot->getBotByToken($token);
            if (!$botInfo) json_response(['success' => false, 'message' => 'invalid_token'], 401);
            $payload = json_decode(file_get_contents('php://input'), true) ?? json_decode($_POST['payload'] ?? '{}', true);
            // Process webhook update
            $this->processWebhook($botInfo, $payload);
            json_response(['success' => true]);
            break;

        // ===== Inline =====
        case 'inline':
            $botId = (int)($_GET['bot_id'] ?? 0);
            $query = $_GET['q'] ?? '';
            $results = $bot->inlineQuery($botId, $query, $userId);
            json_response(['success' => true, 'results' => $results]);
            break;

        // ===== Process a message for bot commands =====
        case 'process_message':
            $message = json_decode($_POST['message'] ?? '{}', true);
            $responses = $bot->processMessage($message);
            json_response(['success' => true, 'responses' => $responses]);
            break;

        case 'stats':
            $botId = (int)($_GET['bot_id'] ?? 0);
            $days = (int)($_GET['days'] ?? 30);
            json_response(['success' => true, 'stats' => $bot->getStats($botId, $days)]);
            break;

        default:
            json_response(['success' => false, 'message' => 'unknown_action'], 400);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}

function processWebhook($botInfo, $payload) {
    // Hook: trigger all bots' message hooks
    $bM = new BotManager();
    $bM->fireHook('message', [
        'bot_id' => $botInfo['id'],
        'chat_id' => $payload['chat_id'] ?? null,
        'text' => $payload['text'] ?? '',
        'from' => $payload['from'] ?? null,
    ]);
}
