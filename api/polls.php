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
        case 'create':
            $chatId = (int)($_POST['chat_id'] ?? 0);
            $question = sanitize($_POST['question'] ?? '');
            $options = $_POST['options'] ?? [];
            $multiple = (int)($_POST['multiple'] ?? 0);
            $anonymous = (int)($_POST['anonymous'] ?? 0);
            if (!$question || count($options) < 2) json_response(['success' => false, 'message' => 'invalid_poll'], 400);
            $stmt = $db->prepare("SELECT 1 FROM chat_members WHERE chat_id = ? AND user_id = ?");
            $stmt->execute([$chatId, $uid]);
            if (!$stmt->fetch()) json_response(['success' => false, 'message' => 'forbidden'], 403);
            $db->beginTransaction();
            $db->prepare("INSERT INTO polls (chat_id, creator_id, question, multiple, anonymous, created_at) VALUES (?, ?, ?, ?, ?, NOW())")
                ->execute([$chatId, $uid, $question, $multiple, $anonymous]);
            $pollId = (int)$db->lastInsertId();
            $ins = $db->prepare("INSERT INTO poll_options (poll_id, text, position) VALUES (?, ?, ?)");
            foreach ((array)$options as $i => $opt) {
                $opt = sanitize($opt);
                if ($opt) $ins->execute([$pollId, $opt, $i]);
            }
            $db->prepare("INSERT INTO messages (chat_id, sender_id, type, content, created_at) VALUES (?, ?, 'poll', ?, NOW())")
                ->execute([$chatId, $uid, (string)$pollId]);
            $db->prepare("UPDATE chats SET updated_at = NOW() WHERE id = ?")->execute([$chatId]);
            $db->commit();
            json_response(['success' => true, 'poll_id' => $pollId]);
            break;

        case 'vote':
            $pollId = (int)($_POST['poll_id'] ?? 0);
            $optionIds = $_POST['option_ids'] ?? [];
            if (!$pollId || !is_array($optionIds)) json_response(['success' => false, 'message' => 'invalid'], 400);
            $stmt = $db->prepare("SELECT * FROM polls WHERE id = ?");
            $stmt->execute([$pollId]);
            $poll = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$poll) json_response(['success' => false, 'message' => 'not_found'], 404);
            $db->beginTransaction();
            $db->prepare("DELETE FROM poll_votes WHERE poll_id = ? AND user_id = ?")->execute([$pollId, $uid]);
            $ins = $db->prepare("INSERT INTO poll_votes (poll_id, option_id, user_id, created_at) VALUES (?, ?, ?, NOW())");
            foreach ($optionIds as $oid) {
                $oid = (int)$oid;
                $check = $db->prepare("SELECT 1 FROM poll_options WHERE id = ? AND poll_id = ?");
                $check->execute([$oid, $pollId]);
                if ($check->fetch()) $ins->execute([$pollId, $oid, $uid]);
            }
            $db->commit();
            json_response(['success' => true]);
            break;

        case 'get':
            $pollId = (int)($_GET['poll_id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM polls WHERE id = ?");
            $stmt->execute([$pollId]);
            $poll = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$poll) json_response(['success' => false, 'message' => 'not_found'], 404);
            $opts = $db->prepare("SELECT o.*, COUNT(v.id) as vote_count FROM poll_options o LEFT JOIN poll_votes v ON v.option_id = o.id WHERE o.poll_id = ? GROUP BY o.id ORDER BY o.position");
            $opts->execute([$pollId]);
            $options = $opts->fetchAll(PDO::FETCH_ASSOC);
            $myVotes = $db->prepare("SELECT option_id FROM poll_votes WHERE poll_id = ? AND user_id = ?");
            $myVotes->execute([$pollId, $uid]);
            $my = array_column($myVotes->fetchAll(PDO::FETCH_ASSOC), 'option_id');
            $total = array_sum(array_column($options, 'vote_count'));
            json_response(['success' => true, 'poll' => $poll, 'options' => $options, 'my_votes' => $my, 'total_votes' => $total]);
            break;

        case 'close':
            $pollId = (int)($_POST['poll_id'] ?? 0);
            $stmt = $db->prepare("SELECT creator_id FROM polls WHERE id = ?");
            $stmt->execute([$pollId]);
            $p = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$p || $p['creator_id'] != $uid) json_response(['success' => false, 'message' => 'forbidden'], 403);
            $db->prepare("UPDATE polls SET is_closed = 1, closed_at = NOW() WHERE id = ?")->execute([$pollId]);
            json_response(['success' => true]);
            break;

        default:
            json_response(['success' => false, 'message' => 'unknown_action'], 400);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
