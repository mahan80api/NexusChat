<?php
/**
 * NexusChat - Channel Manager
 * Public channels (broadcast only), subscribers, posts, analytics
 */
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/MessageManager.php';

class ChannelManager {
    private $db;
    private $mm;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->mm = new MessageManager();
    }

    /**
     * Create a public/private channel
     */
    public function createChannel($ownerId, $name, $username, $description, $isPublic = true, $avatar = null) {
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{4,31}$/', $username)) {
            throw new Exception('نام کاربری نامعتبر است');
        }
        $stmt = $this->db->prepare("SELECT id FROM channels WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) throw new Exception('این نام کاربری قبلاً گرفته شده');

        $stmt = $this->db->prepare("INSERT INTO channels
            (owner_id, name, username, description, avatar, is_public, subscriber_count, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 0, NOW())");
        $stmt->execute([$ownerId, $name, $username, $description, $avatar, $isPublic ? 1 : 0]);
        $channelId = $this->db->lastInsertId();

        // Add owner as first admin
        $this->addAdmin($channelId, $ownerId, 'creator');
        return $channelId;
    }

    public function getChannelById($channelId) {
        $stmt = $this->db->prepare("SELECT c.*, u.display_name as owner_name, u.username as owner_username, u.avatar as owner_avatar
            FROM channels c
            JOIN users u ON u.id = c.owner_id
            WHERE c.id = ?");
        $stmt->execute([$channelId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getChannelByUsername($username) {
        $stmt = $this->db->prepare("SELECT * FROM channels WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getMyChannels($userId) {
        $stmt = $this->db->prepare("SELECT c.*,
            (SELECT COUNT(*) FROM channel_subscribers WHERE channel_id = c.id) as subscriber_count,
            (SELECT COUNT(*) FROM channel_posts WHERE channel_id = c.id) as post_count
            FROM channels c
            WHERE c.id IN (
                SELECT channel_id FROM channel_admins WHERE user_id = ?
                UNION
                SELECT channel_id FROM channel_subscribers WHERE user_id = ?
            )
            ORDER BY c.last_post_at DESC NULLS LAST, c.created_at DESC");
        $stmt->execute([$userId, $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOwnedChannels($userId) {
        $stmt = $this->db->prepare("SELECT c.*, ca.role FROM channels c
            JOIN channel_admins ca ON ca.channel_id = c.id
            WHERE ca.user_id = ? AND ca.role IN ('creator', 'admin')");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function discoverChannels($limit = 30, $q = '') {
        $sql = "SELECT c.*, u.display_name as owner_name
            FROM channels c
            JOIN users u ON u.id = c.owner_id
            WHERE c.is_public = 1";
        $params = [];
        if ($q) {
            $sql .= " AND (c.name LIKE ? OR c.username LIKE ? OR c.description LIKE ?)";
            $like = "%$q%";
            $params = [$like, $like, $like];
        }
        $sql .= " ORDER BY c.subscriber_count DESC LIMIT ?";
        $params[] = $limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateChannel($channelId, $userId, $data) {
        if (!$this->isAdmin($channelId, $userId)) throw new Exception('not_admin');
        $allowed = ['name', 'description', 'avatar', 'is_public', 'slow_mode_seconds', 'sign_messages'];
        $sets = []; $params = [];
        foreach ($data as $k => $v) {
            if (in_array($k, $allowed)) { $sets[] = "$k = ?"; $params[] = $v; }
        }
        if (!$sets) return false;
        $params[] = $channelId;
        $this->db->prepare("UPDATE channels SET " . implode(',', $sets) . " WHERE id = ?")->execute($params);
    }

    public function deleteChannel($channelId, $userId) {
        $ch = $this->getChannelById($channelId);
        if (!$ch || $ch['owner_id'] != $userId) throw new Exception('not_owner');
        $this->db->prepare("DELETE FROM channels WHERE id = ?")->execute([$channelId]);
    }

    // ============ Subscribers ============
    public function subscribe($channelId, $userId) {
        try {
            $stmt = $this->db->prepare("INSERT INTO channel_subscribers (channel_id, user_id, subscribed_at) VALUES (?, ?, NOW())");
            $stmt->execute([$channelId, $userId]);
            $this->db->prepare("UPDATE channels SET subscriber_count = subscriber_count + 1 WHERE id = ?")->execute([$channelId]);
        } catch (Exception $e) { /* already subscribed */ }
    }

    public function unsubscribe($channelId, $userId) {
        $stmt = $this->db->prepare("DELETE FROM channel_subscribers WHERE channel_id = ? AND user_id = ?");
        $stmt->execute([$channelId, $userId]);
        $this->db->prepare("UPDATE channels SET subscriber_count = GREATEST(0, subscriber_count - 1) WHERE id = ?")->execute([$channelId]);
    }

    public function isSubscribed($channelId, $userId) {
        $stmt = $this->db->prepare("SELECT 1 FROM channel_subscribers WHERE channel_id = ? AND user_id = ?");
        $stmt->execute([$channelId, $userId]);
        return (bool)$stmt->fetchColumn();
    }

    public function getSubscribers($channelId, $limit = 100) {
        $stmt = $this->db->prepare("SELECT u.id, u.display_name, u.username, u.avatar, cs.subscribed_at
            FROM channel_subscribers cs
            JOIN users u ON u.id = cs.user_id
            WHERE cs.channel_id = ?
            ORDER BY cs.subscribed_at DESC LIMIT ?");
        $stmt->execute([$channelId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ============ Admins ============
    public function addAdmin($channelId, $userId, $role = 'admin', $addedBy = null) {
        $stmt = $this->db->prepare("INSERT IGNORE INTO channel_admins (channel_id, user_id, role, added_by, added_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$channelId, $userId, $role, $addedBy]);
    }

    public function removeAdmin($channelId, $userId, $removedBy) {
        $ch = $this->getChannelById($channelId);
        if ($ch['owner_id'] != $removedBy) throw new Exception('not_owner');
        $this->db->prepare("DELETE FROM channel_admins WHERE channel_id = ? AND user_id = ? AND role != 'creator'")
                 ->execute([$channelId, $userId]);
    }

    public function isAdmin($channelId, $userId) {
        $stmt = $this->db->prepare("SELECT role FROM channel_admins WHERE channel_id = ? AND user_id = ?");
        $stmt->execute([$channelId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['role'] : false;
    }

    public function getChannelAdmins($channelId) {
        $stmt = $this->db->prepare("SELECT u.id, u.display_name, u.username, u.avatar, ca.role, ca.added_at
            FROM channel_admins ca
            JOIN users u ON u.id = ca.user_id
            WHERE ca.channel_id = ? ORDER BY FIELD(ca.role, 'creator', 'admin', 'moderator')");
        $stmt->execute([$channelId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ============ Posts ============
    public function publishPost($channelId, $userId, $content, $mediaPath = null, $mediaType = 'text', $extra = []) {
        if (!$this->isAdmin($channelId, $userId)) throw new Exception('not_admin');
        $ch = $this->getChannelById($channelId);
        if ($ch['slow_mode_seconds'] > 0) {
            $stmt = $this->db->prepare("SELECT MAX(created_at) as last FROM channel_posts WHERE channel_id = ?");
            $stmt->execute([$channelId]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($r['last'] && (time() - strtotime($r['last'])) < $ch['slow_mode_seconds']) {
                throw new Exception('slow_mode_active');
            }
        }
        $stmt = $this->db->prepare("INSERT INTO channel_posts
            (channel_id, sender_id, content, media_path, media_type, is_pinned, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$channelId, $userId, $content, $mediaPath, $mediaType, !empty($extra['pinned']) ? 1 : 0]);
        $postId = $this->db->lastInsertId();
        $this->db->prepare("UPDATE channels SET last_post_at = NOW() WHERE id = ?")->execute([$channelId]);
        // Notify subscribers
        $this->notifySubscribers($channelId, $postId, $content, $mediaType);
        return $postId;
    }

    public function getPosts($channelId, $limit = 30, $offset = 0) {
        $stmt = $this->db->prepare("SELECT p.*, u.display_name as sender_name, u.username, u.avatar as sender_avatar,
            (SELECT COUNT(*) FROM channel_post_views WHERE post_id = p.id) as view_count,
            (SELECT COUNT(*) FROM channel_post_reactions WHERE post_id = p.id) as reaction_count
            FROM channel_posts p
            JOIN users u ON u.id = p.sender_id
            WHERE p.channel_id = ?
            ORDER BY p.is_pinned DESC, p.created_at DESC LIMIT ? OFFSET ?");
        $stmt->execute([$channelId, $limit, $offset]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['reactions'] = $this->getPostReactions($r['id']);
        }
        return $rows;
    }

    public function getPostById($postId) {
        $stmt = $this->db->prepare("SELECT p.*, u.display_name as sender_name, u.username, u.avatar as sender_avatar
            FROM channel_posts p
            JOIN users u ON u.id = p.sender_id
            WHERE p.id = ?");
        $stmt->execute([$postId]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($post) $post['reactions'] = $this->getPostReactions($postId);
        return $post;
    }

    public function deletePost($postId, $userId) {
        $stmt = $this->db->prepare("SELECT channel_id, sender_id FROM channel_posts WHERE id = ?");
        $stmt->execute([$postId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return false;
        if ($row['sender_id'] != $userId && !$this->isAdmin($row['channel_id'], $userId)) {
            throw new Exception('not_authorized');
        }
        $this->db->prepare("DELETE FROM channel_posts WHERE id = ?")->execute([$postId]);
        return true;
    }

    public function pinPost($postId, $userId, $pin = true) {
        $stmt = $this->db->prepare("SELECT channel_id FROM channel_posts WHERE id = ?");
        $stmt->execute([$postId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !$this->isAdmin($row['channel_id'], $userId)) throw new Exception('not_admin');
        $this->db->prepare("UPDATE channel_posts SET is_pinned = ? WHERE id = ?")->execute([$pin ? 1 : 0, $postId]);
    }

    // ============ Post views & reactions ============
    public function recordView($postId, $userId) {
        try {
            $stmt = $this->db->prepare("INSERT IGNORE INTO channel_post_views (post_id, user_id, viewed_at) VALUES (?, ?, NOW())");
            $stmt->execute([$postId, $userId]);
        } catch (Exception $e) {}
    }

    public function reactToPost($postId, $userId, $emoji) {
        try {
            $stmt = $this->db->prepare("INSERT INTO channel_post_reactions (post_id, user_id, emoji, reacted_at) VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE reacted_at = NOW()");
            $stmt->execute([$postId, $userId, $emoji]);
        } catch (Exception $e) {}
    }

    public function unreactToPost($postId, $userId, $emoji) {
        $this->db->prepare("DELETE FROM channel_post_reactions WHERE post_id = ? AND user_id = ? AND emoji = ?")
                 ->execute([$postId, $userId, $emoji]);
    }

    public function getPostReactions($postId) {
        $stmt = $this->db->prepare("SELECT emoji, COUNT(*) as count FROM channel_post_reactions WHERE post_id = ? GROUP BY emoji ORDER BY count DESC");
        $stmt->execute([$postId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ============ Analytics ============
    public function getStats($channelId) {
        $stmt = $this->db->prepare("SELECT
            (SELECT COUNT(*) FROM channel_subscribers WHERE channel_id = ?) as subscribers,
            (SELECT COUNT(*) FROM channel_posts WHERE channel_id = ?) as posts,
            (SELECT COUNT(*) FROM channel_post_views WHERE post_id IN (SELECT id FROM channel_posts WHERE channel_id = ?)) as total_views,
            (SELECT COUNT(*) FROM channel_post_reactions WHERE post_id IN (SELECT id FROM channel_posts WHERE channel_id = ?)) as total_reactions");
        $stmt->execute([$channelId, $channelId, $channelId, $channelId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getGrowthData($channelId, $days = 30) {
        $stmt = $this->db->prepare("SELECT DATE(subscribed_at) as date, COUNT(*) as count
            FROM channel_subscribers
            WHERE channel_id = ? AND subscribed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(subscribed_at) ORDER BY date ASC");
        $stmt->execute([$channelId, $days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ============ Notification ============
    private function notifySubscribers($channelId, $postId, $content, $mediaType) {
        // Create a mirror chat-like record so subscribers can see in their feed
        $ch = $this->getChannelById($channelId);
        $stmt = $this->db->prepare("INSERT INTO channel_notifications
            (channel_id, post_id, content, media_type, created_at)
            VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$channelId, $postId, $content, $mediaType]);
    }

    public function getMyFeed($userId, $limit = 30) {
        $stmt = $this->db->prepare("SELECT p.*, c.name as channel_name, c.username as channel_username, c.avatar as channel_avatar,
            u.display_name as sender_name
            FROM channel_posts p
            JOIN channels c ON c.id = p.channel_id
            JOIN channel_subscribers cs ON cs.channel_id = c.id
            JOIN users u ON u.id = p.sender_id
            WHERE cs.user_id = ?
            ORDER BY p.created_at DESC LIMIT ?");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
