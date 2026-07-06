<?php
/**
 * NexusChat - Story Class
 */
class Story {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Create a story (24h expiry)
     */
    public function create($userId, $mediaPath, $type = 'image', $caption = null) {
        return $this->db->insert('stories', [
            'user_id'    => $userId,
            'media_path' => $mediaPath,
            'media_type' => $type,
            'caption'    => $caption,
            'expires_at' => date('Y-m-d H:i:s', time() + 86400),
        ]);
    }

    /**
     * Get active stories from users I follow / chatted with
     */
    public function getActiveStories($userId) {
        $rows = $this->db->fetchAll(
            "SELECT s.*, u.username, u.display_name, u.avatar
             FROM stories s
             JOIN users u ON u.id = s.user_id
             WHERE s.expires_at > NOW()
               AND s.user_id IN (
                 SELECT DISTINCT user_id FROM chat_members
                 WHERE chat_id IN (SELECT chat_id FROM chat_members WHERE user_id = ?)
                 UNION SELECT ?
               )
             ORDER BY s.user_id, s.created_at DESC",
            [$userId, $userId]
        );

        // Group by user
        $grouped = [];
        foreach ($rows as $row) {
            $uid = $row['user_id'];
            if (!isset($grouped[$uid])) {
                $grouped[$uid] = [
                    'user' => [
                        'id' => $uid,
                        'username' => $row['username'],
                        'display_name' => $row['display_name'],
                        'avatar' => $row['avatar'],
                    ],
                    'stories' => [],
                ];
            }
            $grouped[$uid]['stories'][] = $row;
        }
        return array_values($grouped);
    }

    public function markViewed($storyId, $userId) {
        try {
            $this->db->insert('story_views', [
                'story_id' => $storyId,
                'user_id'  => $userId,
            ]);
            $this->db->query(
                "UPDATE stories SET views_count = views_count + 1 WHERE id = ?",
                [$storyId]
            );
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Cleanup expired stories
     */
    public function cleanup() {
        return $this->db->query("DELETE FROM stories WHERE expires_at < NOW()")->rowCount();
    }
}

class Notification {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function create($userId, $type, $title, $body = null, $relatedId = null) {
        return $this->db->insert('notifications', [
            'user_id'    => $userId,
            'type'       => $type,
            'title'      => $title,
            'body'       => $body,
            'related_id' => $relatedId,
        ]);
    }

    public function getForUser($userId, $limit = 50) {
        return $this->db->fetchAll(
            "SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT " . (int)$limit,
            [$userId]
        );
    }

    public function unreadCount($userId) {
        $row = $this->db->fetch(
            "SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND is_read = 0",
            [$userId]
        );
        return (int)($row['c'] ?? 0);
    }

    public function markAllRead($userId) {
        return $this->db->update('notifications',
            ['is_read' => 1],
            'user_id = :u',
            ['u' => $userId]
        );
    }
}
