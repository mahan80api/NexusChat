<?php
/**
 * NexusChat - Polls API
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
$poll = new Poll();

try {
    switch ($action) {
        case 'create':
            $chatId    = (int)($_POST['chat_id'] ?? 0);
            $question  = trim($_POST['question'] ?? '');
            $type      = $_POST['type'] ?? 'single';
            $isAnon    = (bool)($_POST['is_anonymous'] ?? false);
            $isPublic  = (bool)($_POST['is_public_results'] ?? true);
            $allowChg  = (bool)($_POST['allows_change_vote'] ?? true);
            $expires   = $_POST['expires_in'] ?? null;
            $options   = json_decode($_POST['options'] ?? '[]', true);
            if (!$question || !$options) throw new Exception('سوال و گزینه‌ها الزامی هستند');
            $created = $poll->create($userId, $chatId, $question, $options, $type, $isAnon, $isPublic, $allowChg, $expires);
            json_response(['success' => true, 'poll' => $created, 'message' => 'نظرسنجی ساخته شد 📊']);
            break;

        case 'vote':
            $pollId  = (int)($_POST['poll_id'] ?? 0);
            $options = json_decode($_POST['option_ids'] ?? '[]', true);
            $updated = $poll->vote($userId, $pollId, $options);
            json_response(['success' => true, 'poll' => $updated]);
            break;

        case 'retract':
            $pollId  = (int)($_POST['poll_id'] ?? 0);
            $updated = $poll->retract($userId, $pollId);
            json_response(['success' => true, 'poll' => $updated]);
            break;

        case 'close':
            $pollId = (int)($_POST['poll_id'] ?? 0);
            $updated = $poll->close($userId, $pollId);
            json_response(['success' => true, 'poll' => $updated, 'message' => 'نظرسنجی بسته شد']);
            break;

        case 'get':
            $pollId = (int)($_GET['poll_id'] ?? 0);
            json_response(['success' => true, 'poll' => $poll->getById($pollId, $userId)]);
            break;

        case 'by_chat':
            $chatId = (int)($_GET['chat_id'] ?? 0);
            $limit  = min(50, max(1, (int)($_GET['limit'] ?? 20)));
            json_response(['success' => true, 'polls' => $poll->getByChat($chatId, $userId, $limit)]);
            break;

        default:
            json_response(['success' => false, 'error' => 'unknown_action'], 400);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 400);
}
