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
        case 'initiate':
            $to = (int)($_POST['to_user_id'] ?? 0);
            $type = $_POST['type'] ?? 'voice';
            $roomId = bin2hex(random_bytes(8));
            $db->prepare("INSERT INTO call_sessions (room_id, caller_id, callee_id, type, status, created_at) VALUES (?, ?, ?, ?, 'ringing', NOW())")
                ->execute([$roomId, $uid, $to, $type]);
            $callId = (int)$db->lastInsertId();
            pusher_trigger('private-user-' . $to, 'incoming-call', ['call_id' => $callId, 'room_id' => $roomId, 'caller_id' => $uid, 'type' => $type]);
            json_response(['success' => true, 'call_id' => $callId, 'room_id' => $roomId]);
            break;

        case 'accept':
            $callId = (int)($_POST['call_id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM call_sessions WHERE id = ? AND callee_id = ? AND status = 'ringing'");
            $stmt->execute([$callId, $uid]);
            $c = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$c) json_response(['success' => false, 'message' => 'not_found'], 404);
            $db->prepare("UPDATE call_sessions SET status = 'active', started_at = NOW() WHERE id = ?")->execute([$callId]);
            pusher_trigger('private-user-' . $c['caller_id'], 'call-accepted', ['call_id' => $callId, 'room_id' => $c['room_id']]);
            json_response(['success' => true, 'room_id' => $c['room_id']]);
            break;

        case 'reject':
            $callId = (int)($_POST['call_id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM call_sessions WHERE id = ? AND callee_id = ?");
            $stmt->execute([$callId, $uid]);
            $c = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$c) json_response(['success' => false, 'message' => 'not_found'], 404);
            $db->prepare("UPDATE call_sessions SET status = 'rejected', ended_at = NOW() WHERE id = ?")->execute([$callId]);
            pusher_trigger('private-user-' . $c['caller_id'], 'call-rejected', ['call_id' => $callId]);
            json_response(['success' => true]);
            break;

        case 'end':
            $callId = (int)($_POST['call_id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM call_sessions WHERE id = ? AND (caller_id = ? OR callee_id = ?)");
            $stmt->execute([$callId, $uid, $uid]);
            $c = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$c) json_response(['success' => false, 'message' => 'not_found'], 404);
            $db->prepare("UPDATE call_sessions SET status = 'ended', ended_at = NOW() WHERE id = ?")->execute([$callId]);
            $other = $c['caller_id'] == $uid ? $c['callee_id'] : $c['caller_id'];
            pusher_trigger('private-user-' . $other, 'call-ended', ['call_id' => $callId]);
            json_response(['success' => true]);
            break;

        case 'signal':
            $callId = (int)($_POST['call_id'] ?? 0);
            $type = $_POST['signal_type'] ?? '';
            $data = $_POST['signal_data'] ?? '';
            $stmt = $db->prepare("SELECT * FROM call_sessions WHERE id = ? AND (caller_id = ? OR callee_id = ?)");
            $stmt->execute([$callId, $uid, $uid]);
            $c = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$c) json_response(['success' => false, 'message' => 'not_found'], 404);
            $other = $c['caller_id'] == $uid ? $c['callee_id'] : $c['caller_id'];
            pusher_trigger('private-user-' . $other, 'signal', ['call_id' => $callId, 'type' => $type, 'data' => $data, 'from' => $uid]);
            json_response(['success' => true]);
            break;

        case 'history':
            $stmt = $db->prepare("SELECT c.*, u.display_name as other_name, u.avatar as other_avatar
                FROM call_sessions c
                LEFT JOIN users u ON u.id = CASE WHEN c.caller_id = ? THEN c.callee_id ELSE c.caller_id END
                WHERE c.caller_id = ? OR c.callee_id = ?
                ORDER BY c.created_at DESC LIMIT 50");
            $stmt->execute([$uid, $uid, $uid]);
            json_response(['success' => true, 'calls' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'pending':
            $stmt = $db->prepare("SELECT c.*, u.display_name as caller_name, u.avatar as caller_avatar
                FROM call_sessions c
                LEFT JOIN users u ON u.id = c.caller_id
                WHERE c.callee_id = ? AND c.status = 'ringing' ORDER BY c.created_at DESC");
            $stmt->execute([$uid]);
            json_response(['success' => true, 'calls' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        default:
            json_response(['success' => false, 'message' => 'unknown_action'], 400);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
