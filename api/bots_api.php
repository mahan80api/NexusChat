<?php
define('NEXUSCHAT_API', true);
require_once __DIR__ . '/../config/config.php';

header('Access-Control-Allow-Origin: ' . APP_URL);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_auth();
$uid = current_user_id();
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$db = Database::getInstance();

try {
    switch ($action) {
        case 'list':
            $stmt = $db->prepare("SELECT * FROM bots WHERE creator_id = ? OR is_public = 1 ORDER BY use_count DESC");
            $stmt->execute([$uid]);
            json_response(['success' => true, 'bots' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'get':
            $botId = (int)($_GET['bot_id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM bots WHERE id = ?");
            $stmt->execute([$botId]);
            $bot = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt = $db->prepare("SELECT * FROM bot_commands WHERE bot_id = ? ORDER BY id");
            $stmt->execute([$botId]);
            $commands = $stmt->fetchAll(PDO::FETCH_ASSOC);
            json_response(['success' => true, 'bot' => $bot, 'commands' => $commands]);
            break;

        case 'create':
            $name = sanitize($_POST['name'] ?? '');
            $username = sanitize($_POST['username'] ?? '');
            $desc = sanitize($_POST['description'] ?? '');
            $token = bin2hex(random_bytes(16));
            $isPublic = (int)($_POST['is_public'] ?? 0);
            if (!$name || !$username) json_response(['success' => false, 'message' => 'missing_fields'], 400);
            $db->prepare("INSERT INTO bots (creator_id, name, username, description, token, is_public, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())")
                ->execute([$uid, $name, $username, $desc, $token, $isPublic]);
            $botId = (int)$db->lastInsertId();
            $botPass = bin2hex(random_bytes(8));
            $db->prepare("INSERT INTO users (username, display_name, password_hash, role) VALUES (?, ?, ?, 'bot')")
                ->execute(['bot_' . $username, $name, password_hash($botPass, PASSWORD_BCRYPT)]);
            $botUserId = (int)$db->lastInsertId();
            $db->prepare("UPDATE bots SET bot_user_id = ? WHERE id = ?")->execute([$botUserId, $botId]);
            json_response(['success' => true, 'bot_id' => $botId, 'token' => $token]);
            break;

        case 'add_command':
            $botId = (int)($_POST['bot_id'] ?? 0);
            $cmd = sanitize($_POST['command'] ?? '');
            $desc = sanitize($_POST['description'] ?? '');
            $response = $_POST['response'] ?? '';
            if (!$cmd) json_response(['success' => false, 'message' => 'no_command'], 400);
            $stmt = $db->prepare("SELECT creator_id FROM bots WHERE id = ?");
            $stmt->execute([$botId]);
            $b = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$b || $b['creator_id'] != $uid) json_response(['success' => false, 'message' => 'forbidden'], 403);
            $db->prepare("INSERT INTO bot_commands (bot_id, command, description, response) VALUES (?, ?, ?, ?)")
                ->execute([$botId, $cmd, $desc, $response]);
            json_response(['success' => true, 'command_id' => (int)$db->lastInsertId()]);
            break;

        case 'run':
            $botId = (int)($_POST['bot_id'] ?? 0);
            $cmd = sanitize($_POST['command'] ?? '');
            $text = $_POST['text'] ?? '';
            $stmt = $db->prepare("SELECT * FROM bots WHERE id = ?");
            $stmt->execute([$botId]);
            $bot = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$bot) json_response(['success' => false, 'message' => 'not_found'], 404);
            $bm = new BotManager();
            $response = $bm->processCommand($bot, $cmd, $text, $uid);
            $db->prepare("UPDATE bots SET use_count = use_count + 1 WHERE id = ?")->execute([$botId]);
            json_response(['success' => true, 'response' => $response]);
            break;

        case 'add_to_chat':
            $botId = (int)($_POST['bot_id'] ?? 0);
            $chatId = (int)($_POST['chat_id'] ?? 0);
            $stmt = $db->prepare("SELECT bot_user_id FROM bots WHERE id = ?");
            $stmt->execute([$botId]);
            $b = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$b) json_response(['success' => false, 'message' => 'not_found'], 404);
            $stmt = $db->prepare("SELECT 1 FROM chat_members WHERE chat_id = ? AND user_id = ?");
            $stmt->execute([$chatId, $b['bot_user_id']]);
            if (!$stmt->fetch()) {
                $db->prepare("INSERT INTO chat_members (chat_id, user_id, role) VALUES (?, ?, 'member')")
                    ->execute([$chatId, $b['bot_user_id']]);
            }
            json_response(['success' => true]);
            break;

        case 'delete':
            $botId = (int)($_POST['bot_id'] ?? 0);
            $stmt = $db->prepare("SELECT creator_id, bot_user_id FROM bots WHERE id = ?");
            $stmt->execute([$botId]);
            $b = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$b || $b['creator_id'] != $uid) json_response(['success' => false, 'message' => 'forbidden'], 403);
            $db->prepare("DELETE FROM bots WHERE id = ?")->execute([$botId]);
            $db->prepare("DELETE FROM users WHERE id = ?")->execute([$b['bot_user_id']]);
            json_response(['success' => true]);
            break;

        default:
            json_response(['success' => false, 'message' => 'unknown_action'], 400);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
