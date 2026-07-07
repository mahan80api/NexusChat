<?php
/**
 * NexusChat - Sticker Manager
 * Handles sticker packs, custom uploads, favorites
 */
class Sticker {
    private $db;
    private $uploadDir = 'assets/uploads/stickers/';
    private $maxStickerSize = 5 * 1024 * 1024; // 5MB
    private $allowedTypes = ['image/webp', 'image/png', 'image/jpeg', 'image/gif'];

    public function __construct() {
        $this->db = Database::getInstance();
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    /**
     * Get all available packs (installed + featured)
     */
    public function getAvailablePacks($userId) {
        // Installed packs
        $stmt = $this->db->prepare("SELECT sp.*, usp.is_favorite, usp.sort_order as user_sort,
                                            (SELECT COUNT(*) FROM stickers s WHERE s.pack_id = sp.id) as sticker_count
                                     FROM sticker_packs sp
                                     LEFT JOIN user_sticker_packs usp ON usp.pack_id = sp.id AND usp.user_id = ?
                                     WHERE sp.is_public = 1
                                     ORDER BY (usp.user_id IS NOT NULL) DESC, sp.is_official DESC, sp.sort_order ASC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get stickers of a pack (only if installed or official)
     */
    public function getPackStickers($userId, $packId) {
        $stmt = $this->db->prepare("SELECT s.*,
                                           EXISTS(SELECT 1 FROM user_favorite_stickers ufs WHERE ufs.sticker_id = s.id AND ufs.user_id = ?) as is_favorite
                                    FROM stickers s
                                    WHERE s.pack_id = ?
                                    ORDER BY s.sort_order, s.id");
        $stmt->execute([$userId, $packId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Install a pack for a user
     */
    public function installPack($userId, $packId) {
        $stmt = $this->db->prepare("INSERT IGNORE INTO user_sticker_packs (user_id, pack_id) VALUES (?, ?)");
        $stmt->execute([$userId, $packId]);
        $this->db->prepare("UPDATE sticker_packs SET install_count = install_count + 1 WHERE id = ?")->execute([$packId]);
        return true;
    }

    public function uninstallPack($userId, $packId) {
        $this->db->prepare("DELETE FROM user_sticker_packs WHERE user_id = ? AND pack_id = ?")->execute([$userId, $packId]);
        return true;
    }

    /**
     * Toggle favorite pack
     */
    public function toggleFavoritePack($userId, $packId) {
        $stmt = $this->db->prepare("SELECT is_favorite FROM user_sticker_packs WHERE user_id = ? AND pack_id = ?");
        $stmt->execute([$userId, $packId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $this->installPack($userId, $packId);
            $row = ['is_favorite' => 0];
        }
        $newVal = $row['is_favorite'] == 1 ? 0 : 1;
        $this->db->prepare("UPDATE user_sticker_packs SET is_favorite = ? WHERE user_id = ? AND pack_id = ?")
            ->execute([$newVal, $userId, $packId]);
        return $newVal == 1;
    }

    /**
     * Toggle favorite sticker
     */
    public function toggleFavoriteSticker($userId, $stickerId) {
        $stmt = $this->db->prepare("SELECT 1 FROM user_favorite_stickers WHERE user_id = ? AND sticker_id = ?");
        $stmt->execute([$userId, $stickerId]);
        if ($stmt->fetch()) {
            $this->db->prepare("DELETE FROM user_favorite_stickers WHERE user_id = ? AND sticker_id = ?")
                ->execute([$userId, $stickerId]);
            return false;
        }
        $this->db->prepare("INSERT INTO user_favorite_stickers (user_id, sticker_id) VALUES (?, ?)")
            ->execute([$userId, $stickerId]);
        return true;
    }

    /**
     * Search stickers
     */
    public function search($userId, $query) {
        $q = '%' . $query . '%';
        $stmt = $this->db->prepare("SELECT s.*, sp.name as pack_name, sp.icon as pack_icon
                                    FROM stickers s
                                    JOIN sticker_packs sp ON sp.id = s.pack_id
                                    WHERE s.emoji LIKE ? OR sp.name LIKE ? OR sp.tags LIKE ?
                                    ORDER BY sp.is_official DESC, s.id
                                    LIMIT 100");
        $stmt->execute([$q, $q, $q]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get favorite stickers
     */
    public function getFavorites($userId) {
        $stmt = $this->db->prepare("SELECT s.*, sp.name as pack_name, sp.icon as pack_icon
                                    FROM user_favorite_stickers ufs
                                    JOIN stickers s ON s.id = ufs.sticker_id
                                    JOIN sticker_packs sp ON sp.id = s.pack_id
                                    WHERE ufs.user_id = ?
                                    ORDER BY ufs.favorited_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get trending stickers
     */
    public function getTrending($userId, $days = 7, $limit = 30) {
        $since = date('Y-m-d H:i:s', time() - $days * 86400);
        $stmt = $this->db->prepare("SELECT s.*, sp.name as pack_name, sp.icon as pack_icon, COUNT(sus.id) as uses
                                    FROM sticker_usage_stats sus
                                    JOIN stickers s ON s.id = sus.sticker_id
                                    JOIN sticker_packs sp ON sp.id = s.pack_id
                                    WHERE sus.used_at >= ?
                                    GROUP BY s.id
                                    ORDER BY uses DESC
                                    LIMIT ?");
        $stmt->execute([$since, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Track sticker usage
     */
    public function trackUsage($stickerId, $userId, $chatId = null) {
        $this->db->prepare("INSERT INTO sticker_usage_stats (sticker_id, user_id, chat_id) VALUES (?, ?, ?)")
            ->execute([$stickerId, $userId, $chatId]);
    }

    /**
     * Create custom sticker pack
     */
    public function createCustomPack($userId, $name, $description, $icon, $isPublic) {
        $slug = $this->slugify($name) . '_' . $userId;
        $stmt = $this->db->prepare("INSERT INTO sticker_packs (name, slug, description, author_id, is_official, is_public, icon)
                                    VALUES (?, ?, ?, ?, 0, ?, ?)");
        $stmt->execute([$name, $slug, $description, $userId, $isPublic ? 1 : 0, $icon]);
        $packId = $this->db->lastInsertId();
        $this->installPack($userId, $packId);
        return $packId;
    }

    /**
     * Upload a sticker to a pack
     */
    public function uploadSticker($userId, $packId, $file, $emoji) {
        if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('خطا در آپلود فایل');
        }
        if ($file['size'] > $this->maxStickerSize) {
            throw new Exception('حجم فایل نباید بیش از ۵ مگابایت باشد');
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, $this->allowedTypes, true)) {
            throw new Exception('فرمت باید WebP، PNG، JPG یا GIF باشد');
        }
        $ext = $this->mimeToExt($mime);
        $filename = 'sticker_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = $this->uploadDir . $filename;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new Exception('خطا در ذخیره فایل');
        }
        [$width, $height] = getimagesize($dest);
        $isAnimated = $mime === 'image/gif' || $mime === 'image/webp';
        $stmt = $this->db->prepare("INSERT INTO stickers (pack_id, emoji, file_path, is_animated, width, height, file_size)
                                    VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$packId, $emoji ?: '😀', $filename, $isAnimated ? 1 : 0, $width, $height, filesize($dest)]);
        return $this->db->lastInsertId();
    }

    /**
     * Get my custom packs
     */
    public function getMyPacks($userId) {
        $stmt = $this->db->prepare("SELECT sp.*,
                                           (SELECT COUNT(*) FROM stickers s WHERE s.pack_id = sp.id) as sticker_count
                                    FROM sticker_packs sp
                                    WHERE sp.author_id = ?
                                    ORDER BY sp.created_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Send a sticker message
     */
    public function sendSticker($userId, $chatId, $stickerId) {
        $sticker = $this->db->prepare("SELECT s.*, sp.id as pack_id, sp.name as pack_name FROM stickers s
                                       JOIN sticker_packs sp ON sp.id = s.pack_id
                                       WHERE s.id = ?");
        $sticker->execute([$stickerId]);
        $s = $sticker->fetch(PDO::FETCH_ASSOC);
        if (!$s) throw new Exception('استیکر یافت نشد');
        $msg = new Message();
        $message = $msg->send([
            'chat_id'   => $chatId,
            'sender_id' => $userId,
            'type'      => 'sticker',
            'content'   => $s['emoji'] . ' ' . $s['pack_name'],
            'file_path' => $s['file_path'],
            'metadata'  => json_encode(['sticker_id' => $s['id'], 'pack_id' => $s['pack_id'], 'emoji' => $s['emoji']]),
        ]);
        $this->trackUsage($s['id'], $userId, $chatId);
        return $message;
    }

    private function mimeToExt($mime) {
        return [
            'image/webp' => 'webp',
            'image/png'  => 'png',
            'image/jpeg' => 'jpg',
            'image/gif'  => 'gif',
        ][$mime] ?? 'webp';
    }

    private function slugify($text) {
        $text = preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', $text);
        $text = preg_replace('/\s+/', '-', $text);
        return mb_strtolower(trim($text, '-'));
    }
}
