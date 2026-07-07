<?php
/**
 * NexusChat - Poll Manager
 * Create, vote, and manage polls
 */
class Poll {
    private $db;
    public function __construct() { $this->db = Database::getInstance(); }

    /**
     * Create a new poll (as a message)
     */
    public function create($userId, $chatId, $question, $options, $type = 'single', $isAnonymous = false, $isPublicResults = true, $allowsChangeVote = true, $expiresIn = null) {
        if (count($options) < 2 || count($options) > 10) {
            throw new Exception('تعداد گزینه‌ها باید بین ۲ تا ۱۰ باشد');
        }
        $expiresAt = null;
        if ($expiresIn) {
            $expiresAt = date('Y-m-d H:i:s', time() + $this->parseDuration($expiresIn));
        }
        $this->db->beginTransaction();
        try {
            $msg = new Message();
            $message = $msg->send([
                'chat_id'   => $chatId,
                'sender_id' => $userId,
                'type'      => 'poll',
                'content'   => '📊 ' . $question,
                'metadata'  => json_encode(['poll_type' => 'question']),
            ]);
            $stmt = $this->db->prepare("INSERT INTO polls (message_id, chat_id, creator_id, question, type, is_anonymous, is_public_results, allows_change_vote, expires_at)
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $message['id'], $chatId, $userId, $question, $type,
                $isAnonymous ? 1 : 0,
                $isPublicResults ? 1 : 0,
                $allowsChangeVote ? 1 : 0,
                $expiresAt,
            ]);
            $pollId = $this->db->lastInsertId();
            $optStmt = $this->db->prepare("INSERT INTO poll_options (poll_id, text, sort_order) VALUES (?, ?, ?)");
            foreach ($options as $i => $opt) {
                $optStmt->execute([$pollId, $opt, $i]);
            }
            $msg->updateMetadata($message['id'], json_encode(['poll_id' => $pollId, 'type' => 'poll']));
            $this->db->commit();
            return $this->getById($pollId);
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Vote on a poll
     */
    public function vote($userId, $pollId, $optionIds) {
        if (!is_array($optionIds)) $optionIds = [$optionIds];
        $poll = $this->getById($pollId);
        if (!$poll) throw new Exception('نظرسنجی یافت نشد');
        if ($poll['is_closed'] || ($poll['expires_at'] && strtotime($poll['expires_at']) < time())) {
            throw new Exception('نظرسنجی به پایان رسیده است');
        }
        $this->db->beginTransaction();
        try {
            // Remove old votes
            if ($poll['allows_change_vote']) {
                $this->db->prepare("DELETE FROM poll_votes WHERE poll_id = ? AND user_id = ?")->execute([$pollId, $userId]);
            } else {
                $existing = $this->db->prepare("SELECT 1 FROM poll_votes WHERE poll_id = ? AND user_id = ?");
                $existing->execute([$pollId, $userId]);
                if ($existing->fetch()) {
                    throw new Exception('شما قبلاً رای داده‌اید');
                }
            }
            // For single-choice polls, only first option counts
            if ($poll['type'] === 'single' && count($optionIds) > 1) {
                $optionIds = [reset($optionIds)];
            }
            $validOptIds = array_column($poll['options'], 'id');
            $ins = $this->db->prepare("INSERT INTO poll_votes (poll_id, option_id, user_id) VALUES (?, ?, ?)");
            $upd = $this->db->prepare("UPDATE poll_options SET vote_count = (SELECT COUNT(*) FROM poll_votes WHERE option_id = poll_options.id) WHERE id = ?");
            foreach ($optionIds as $optId) {
                if (!in_array($optId, $validOptIds)) continue;
                $ins->execute([$pollId, $optId, $userId]);
                $upd->execute([$optId]);
            }
            $this->db->prepare("UPDATE polls SET total_votes = (SELECT COUNT(DISTINCT user_id) FROM poll_votes WHERE poll_id = ?) WHERE id = ?")
                ->execute([$pollId, $pollId]);
            $this->db->commit();
            return $this->getById($pollId);
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Retract a vote
     */
    public function retract($userId, $pollId) {
        $this->db->beginTransaction();
        try {
            $votes = $this->db->prepare("SELECT option_id FROM poll_votes WHERE poll_id = ? AND user_id = ?");
            $votes->execute([$pollId, $userId]);
            $optIds = $votes->fetchAll(PDO::FETCH_COLUMN);
            $this->db->prepare("DELETE FROM poll_votes WHERE poll_id = ? AND user_id = ?")->execute([$pollId, $userId]);
            $upd = $this->db->prepare("UPDATE poll_options SET vote_count = (SELECT COUNT(*) FROM poll_votes WHERE option_id = poll_options.id) WHERE id = ?");
            foreach ($optIds as $oid) $upd->execute([$oid]);
            $this->db->prepare("UPDATE polls SET total_votes = (SELECT COUNT(DISTINCT user_id) FROM poll_votes WHERE poll_id = ?) WHERE id = ?")
                ->execute([$pollId, $pollId]);
            $this->db->commit();
            return $this->getById($pollId);
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Close a poll manually (creator or admin only)
     */
    public function close($userId, $pollId) {
        $poll = $this->getById($pollId);
        if (!$poll) throw new Exception('نظرسنجی یافت نشد');
        if ($poll['creator_id'] != $userId) {
            $role = $this->db->prepare("SELECT role FROM chat_members WHERE chat_id = ? AND user_id = ?");
            $role->execute([$poll['chat_id'], $userId]);
            $userRole = $role->fetchColumn();
            if (!in_array($userRole, ['owner', 'admin'])) throw new Exception('فقط سازنده یا ادمین می‌تواند ببندد');
        }
        $this->db->prepare("UPDATE polls SET is_closed = 1, closed_at = NOW() WHERE id = ?")->execute([$pollId]);
        return $this->getById($pollId);
    }

    /**
     * Get poll with options and user-voted data
     */
    public function getById($pollId, $userId = null) {
        $stmt = $this->db->prepare("SELECT p.*, u.display_name as creator_name, u.avatar as creator_avatar,
                                           m.created_at as message_created_at
                                    FROM polls p
                                    JOIN users u ON u.id = p.creator_id
                                    JOIN messages m ON m.id = p.message_id
                                    WHERE p.id = ?");
        $stmt->execute([$pollId]);
        $poll = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$poll) return null;
        $optStmt = $this->db->prepare("SELECT id, text, vote_count, sort_order FROM poll_options WHERE poll_id = ? ORDER BY sort_order");
        $optStmt->execute([$pollId]);
        $poll['options'] = $optStmt->fetchAll(PDO::FETCH_ASSOC);
        $poll['is_expired'] = $poll['expires_at'] && strtotime($poll['expires_at']) < time();
        $poll['is_closed'] = $poll['is_closed'] || $poll['is_expired'];
        if ($userId) {
            $voteStmt = $this->db->prepare("SELECT option_id FROM poll_votes WHERE poll_id = ? AND user_id = ?");
            $voteStmt->execute([$pollId, $userId]);
            $poll['user_votes'] = $voteStmt->fetchAll(PDO::FETCH_COLUMN);
        }
        $poll['participants'] = (int)$poll['total_votes'];
        return $poll;
    }

    /**
     * Get polls in a chat
     */
    public function getByChat($chatId, $userId, $limit = 20) {
        $stmt = $this->db->prepare("SELECT id FROM polls WHERE chat_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$chatId, $limit]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return array_map(fn($id) => $this->getById($id, $userId), $ids);
    }

    private function parseDuration($d) {
        return match(true) {
            $d === '1h'  => 3600,
            $d === '6h'  => 6 * 3600,
            $d === '1d'  => 86400,
            $d === '7d'  => 7 * 86400,
            str_ends_with($d, 'h') => (int)$d * 3600,
            str_ends_with($d, 'd') => (int)$d * 86400,
            str_ends_with($d, 'm') => (int)$d * 60,
            default => 0,
        };
    }
}
