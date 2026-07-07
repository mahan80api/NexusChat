<?php
/**
 * NexusChat - Voice/Video Call Manager
 * Handles WebRTC signaling, call state, history
 */
require_once __DIR__ . '/Database.php';

class CallManager {
    private $db;
    public const SIGNAL_TYPES = ['offer', 'answer', 'ice-candidate', 'renegotiate', 'bye', 'mute', 'unmute', 'video-on', 'video-off', 'screen-share'];

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Initiate a new call
     */
    public function startCall($callerId, $calleeId, $chatId, $callType = 'voice', $isGroup = false) {
        $callId = bin2hex(random_bytes(16));
        $stmt = $this->db->prepare("INSERT INTO calls
            (call_id, chat_id, caller_id, call_type, is_group_call, status, started_at, created_at)
            VALUES (?, ?, ?, ?, ?, 'ringing', NOW(), NOW())");
        $stmt->execute([$callId, $chatId, $callerId, $callType, $isGroup ? 1 : 0]);
        $id = $this->db->lastInsertId();

        // Add participants
        if ($isGroup) {
            $members = $this->db->prepare("SELECT user_id FROM chat_members WHERE chat_id = ? AND user_id != ?");
            $members->execute([$chatId, $callerId]);
            foreach ($members->fetchAll(PDO::FETCH_COLUMN) as $memberId) {
                $this->addParticipant($id, $memberId, 'invited');
            }
            $this->addParticipant($id, $callerId, 'joined');
        } else {
            $this->addParticipant($id, $callerId, 'joined');
            $this->addParticipant($id, $calleeId, 'invited');
        }

        return ['call_db_id' => $id, 'call_id' => $callId, 'status' => 'ringing'];
    }

    private function addParticipant($callDbId, $userId, $status) {
        $stmt = $this->db->prepare("INSERT INTO call_participants (call_id, user_id, status) VALUES (?, ?, ?)
                                    ON DUPLICATE KEY UPDATE status = VALUES(status)");
        $stmt->execute([$callDbId, $userId, $status]);
    }

    /**
     * Answer an incoming call
     */
    public function answerCall($callId, $userId) {
        $call = $this->getCall($callId);
        if (!$call) throw new Exception('Call not found');
        $this->db->prepare("UPDATE call_participants SET status='joined', joined_at=NOW() WHERE call_id=? AND user_id=?")
                 ->execute([$call['id'], $userId]);
        // Auto-start status when first user joins (after caller)
        $joined = $this->db->prepare("SELECT COUNT(*) FROM call_participants WHERE call_id=? AND status='joined'");
        $joined->execute([$call['id']]);
        if ($joined->fetchColumn() >= 1 && $call['status'] === 'ringing') {
            $this->db->prepare("UPDATE calls SET status='active', answered_at=NOW() WHERE id=?")->execute([$call['id']]);
        }
        return $this->getCall($callId);
    }

    /**
     * Reject an incoming call
     */
    public function rejectCall($callId, $userId) {
        $call = $this->getCall($callId);
        if (!$call) throw new Exception('Call not found');
        $this->db->prepare("UPDATE call_participants SET status='rejected', left_at=NOW() WHERE call_id=? AND user_id=?")
                 ->execute([$call['id'], $userId]);
        // End call if everyone rejected
        $active = $this->db->prepare("SELECT COUNT(*) FROM call_participants WHERE call_id=? AND status IN ('joined','invited')");
        $active->execute([$call['id']]);
        if (!$active->fetchColumn()) {
            $this->endCall($callId, $userId, 'rejected');
        }
        return $this->getCall($callId);
    }

    /**
     * End an active call
     */
    public function endCall($callId, $userId, $reason = 'completed') {
        $call = $this->getCall($callId);
        if (!$call) return null;
        $this->db->prepare("UPDATE calls SET status='ended', ended_at=NOW(), end_reason=? WHERE id=?")
                 ->execute([$reason, $call['id']]);
        $this->db->prepare("UPDATE call_participants SET status='left', left_at=NOW()
                            WHERE call_id=? AND user_id=? AND status IN ('joined','invited')")
                 ->execute([$call['id'], $userId]);
        return $this->getCall($callId);
    }

    /**
     * Toggle media state
     */
    public function updateMediaState($callId, $userId, $audio = null, $video = null, $screen = null) {
        $sets = [];
        $params = [];
        if ($audio !== null) { $sets[] = 'audio_enabled=?'; $params[] = $audio ? 1 : 0; }
        if ($video !== null) { $sets[] = 'video_enabled=?'; $params[] = $video ? 1 : 0; }
        if ($screen !== null) { $sets[] = 'screen_sharing=?'; $params[] = $screen ? 1 : 0; }
        if (!$sets) return false;
        $params[] = $callId; $params[] = $userId;
        $sql = "UPDATE call_participants SET " . implode(',', $sets) . " WHERE call_id=? AND user_id=?";
        $this->db->prepare($sql)->execute($params);
        return true;
    }

    /**
     * Save signaling message (offer/answer/ICE)
     */
    public function saveSignal($callId, $fromUserId, $toUserId, $type, $payload) {
        if (!in_array($type, self::SIGNAL_TYPES)) throw new Exception('Invalid signal type');
        $stmt = $this->db->prepare("INSERT INTO call_signals
            (call_id, from_user_id, to_user_id, signal_type, payload, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$callId, $fromUserId, $toUserId, $type, json_encode($payload)]);
        return $this->db->lastInsertId();
    }

    /**
     * Fetch pending signals (poll-based fallback when WebSocket unavailable)
     */
    public function getPendingSignals($callId, $userId, $lastId = 0) {
        $stmt = $this->db->prepare("SELECT * FROM call_signals
            WHERE call_id=? AND to_user_id=? AND id > ?
            ORDER BY id ASC LIMIT 100");
        $stmt->execute([$callId, $userId, $lastId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['payload'] = json_decode($r['payload'], true);
        }
        return $rows;
    }

    /**
     * Get call info by call_id (string)
     */
    public function getCall($callId) {
        $stmt = $this->db->prepare("SELECT c.*,
                  u1.display_name as caller_name, u1.avatar as caller_avatar,
                  ch.name as chat_name
            FROM calls c
            JOIN users u1 ON u1.id = c.caller_id
            LEFT JOIN chats ch ON ch.id = c.chat_id
            WHERE c.call_id = ?");
        $stmt->execute([$callId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get participants of a call
     */
    public function getParticipants($callId) {
        $stmt = $this->db->prepare("SELECT cp.*, u.display_name, u.username, u.avatar
            FROM call_participants cp
            JOIN users u ON u.id = cp.user_id
            WHERE cp.call_id = ? ORDER BY cp.joined_at");
        $stmt->execute([$callId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get call history for a user
     */
    public function getCallHistory($userId, $limit = 50) {
        $sql = "SELECT c.*, ch.name as chat_name,
                       u.display_name as other_name, u.avatar as other_avatar,
                       (CASE WHEN c.caller_id = ? THEN 'outgoing' ELSE 'incoming' END) as direction
                FROM calls c
                LEFT JOIN chats ch ON ch.id = c.chat_id
                LEFT JOIN users u ON u.id = (CASE WHEN c.caller_id = ? THEN
                                                  (SELECT user_id FROM call_participants WHERE call_id = c.id AND user_id != ? LIMIT 1)
                                                  ELSE c.caller_id END)
                WHERE c.id IN (SELECT call_id FROM call_participants WHERE user_id = ?)
                ORDER BY c.created_at DESC
                LIMIT ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $userId, $userId, $userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Compute call duration
     */
    public function getDuration($call) {
        if (!$call['ended_at'] || !$call['answered_at']) return 0;
        return strtotime($call['ended_at']) - strtotime($call['answered_at']);
    }

    /**
     * Get call stats for analytics
     */
    public function getStats($userId) {
        $sql = "SELECT
                COUNT(*) as total_calls,
                SUM(call_type='voice') as voice_calls,
                SUM(call_type='video') as video_calls,
                SUM(caller_id=?) as outgoing,
                SUM(caller_id!=?) as incoming,
                SUM(status='completed') as completed,
                SUM(status='missed') as missed,
                AVG(TIMESTAMPDIFF(SECOND, answered_at, ended_at)) as avg_duration
                FROM calls WHERE id IN (SELECT call_id FROM call_participants WHERE user_id=?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $userId, $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
