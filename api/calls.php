<?php
/**
 * NexusChat - Calls API (WebRTC Signaling)
 */
define('NEXUSCHAT_API', true);
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . APP_URL);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_auth();
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$userId = current_user_id();
$call   = new CallManager();

try {
    switch ($action) {
        case 'start':
            $callee = (int)($_POST['callee_id'] ?? 0);
            $chat   = (int)($_POST['chat_id'] ?? 0);
            $type   = $_POST['type'] ?? 'voice';
            $isGroup= !empty($_POST['is_group']);
            $res = $call->startCall($userId, $callee, $chat, $type, $isGroup);
            json_response(['success' => true, 'call' => $res, 'participants' => $call->getParticipants($res['call_id'])]);
            break;

        case 'answer':
            $cid = $_POST['call_id'];
            $res = $call->answerCall($cid, $userId);
            json_response(['success' => true, 'call' => $res]);
            break;

        case 'reject':
            $cid = $_POST['call_id'];
            $res = $call->rejectCall($cid, $userId);
            json_response(['success' => true, 'call' => $res]);
            break;

        case 'end':
            $cid = $_POST['call_id'];
            $res = $call->endCall($cid, $userId, 'completed');
            json_response(['success' => true, 'call' => $res]);
            break;

        case 'signal':
            // Save WebRTC signaling payload (SDP offer/answer or ICE candidate)
            $cid = $_POST['call_id'];
            $to  = (int)$_POST['to_user_id'];
            $type= $_POST['signal_type'];
            $payload = json_decode($_POST['payload'] ?? '{}', true);
            $call->saveSignal($cid, $userId, $to, $type, $payload);
            json_response(['success' => true]);
            break;

        case 'poll':
            // Long polling endpoint to fetch pending signals
            $cid = $_POST['call_id'];
            $last = (int)($_POST['last_id'] ?? 0);
            $start = microtime(true);
            $maxWait = 25; // seconds
            do {
                $signals = $call->getPendingSignals($cid, $userId, $last);
                if ($signals) {
                    json_response(['success' => true, 'signals' => $signals]);
                    exit;
                }
                usleep(500000); // 500ms
            } while (microtime(true) - $start < $maxWait);
            json_response(['success' => true, 'signals' => []]);
            break;

        case 'media_state':
            $cid = $_POST['call_id'];
            $audio = isset($_POST['audio']) ? (bool)$_POST['audio'] : null;
            $video = isset($_POST['video']) ? (bool)$_POST['video'] : null;
            $screen= isset($_POST['screen']) ? (bool)$_POST['screen'] : null;
            $call->updateMediaState($cid, $userId, $audio, $video, $screen);
            json_response(['success' => true]);
            break;

        case 'info':
            $cid = $_GET['call_id'];
            $info = $call->getCall($cid);
            $info['participants'] = $call->getParticipants($cid);
            $info['duration'] = $call->getDuration($info);
            json_response(['success' => true, 'call' => $info]);
            break;

        case 'history':
            $limit = (int)($_GET['limit'] ?? 50);
            $rows = $call->getCallHistory($userId, $limit);
            foreach ($rows as &$r) {
                $participants = $call->getParticipants($r['call_id']);
                $r['participants'] = $participants;
            }
            json_response(['success' => true, 'calls' => $rows]);
            break;

        case 'stats':
            json_response(['success' => true, 'stats' => $call->getStats($userId)]);
            break;

        case 'ice_servers':
            // Public STUN + (configurable) TURN
            $servers = [
                ['urls' => 'stun:stun.l.google.com:19302'],
                ['urls' => 'stun:stun1.l.google.com:19302'],
            ];
            // TURN server (configure with your own credentials)
            $turnUrl = getenv('TURN_URL');
            $turnUser = getenv('TURN_USER');
            $turnCred = getenv('TURN_CRED');
            if ($turnUrl && $turnUser && $turnCred) {
                $servers[] = ['urls' => $turnUrl, 'username' => $turnUser, 'credential' => $turnCred];
            }
            json_response(['success' => true, 'iceServers' => $servers]);
            break;

        default:
            json_response(['success' => false, 'message' => 'unknown_action'], 400);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
