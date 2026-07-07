<?php
/**
 * NexusChat - Theme Manager
 * Custom themes: colors, gradients, fonts, animations, marketplace
 */
require_once __DIR__ . '/Database.php';

class ThemeManager {
    private $db;
    private $presets = [
        'cosmic_gold' => [
            'name' => 'Cosmic Gold (پیش‌فرض)',
            'primary' => '#d4af37', 'secondary' => '#1a0030', 'background' => '#0a0118',
            'surface' => 'rgba(255, 255, 255, 0.05)', 'text' => '#ffffff', 'text_dim' => '#a0a0c0',
            'accent' => '#b19cd9', 'border' => 'rgba(212, 175, 55, 0.3)',
            'gradient' => 'linear-gradient(135deg, #d4af37 0%, #1a0030 100%)',
            'background_image' => '', 'font_family' => 'system-ui, sans-serif', 'border_radius' => '12',
            'is_public' => 1, 'category' => 'cosmic',
        ],
        'ocean_blue' => [
            'name' => 'Ocean Blue',
            'primary' => '#00bcd4', 'secondary' => '#003547', 'background' => '#001a2e',
            'surface' => 'rgba(255, 255, 255, 0.05)', 'text' => '#ffffff', 'text_dim' => '#80deea',
            'accent' => '#4dd0e1', 'border' => 'rgba(0, 188, 212, 0.3)',
            'gradient' => 'linear-gradient(135deg, #00bcd4 0%, #003547 100%)',
            'background_image' => '', 'font_family' => 'system-ui, sans-serif', 'border_radius' => '10',
            'is_public' => 1, 'category' => 'nature',
        ],
        'sunset' => [
            'name' => 'Sunset Glow',
            'primary' => '#ff6b6b', 'secondary' => '#4a1942', 'background' => '#1a0820',
            'surface' => 'rgba(255, 255, 255, 0.05)', 'text' => '#ffffff', 'text_dim' => '#ffb3b3',
            'accent' => '#ffa07a', 'border' => 'rgba(255, 107, 107, 0.3)',
            'gradient' => 'linear-gradient(135deg, #ff6b6b 0%, #ffa07a 50%, #4a1942 100%)',
            'background_image' => '', 'font_family' => 'Georgia, serif', 'border_radius' => '16',
            'is_public' => 1, 'category' => 'warm',
        ],
        'matrix' => [
            'name' => 'Matrix Green',
            'primary' => '#00ff41', 'secondary' => '#001a0a', 'background' => '#000000',
            'surface' => 'rgba(0, 255, 65, 0.05)', 'text' => '#00ff41', 'text_dim' => '#008822',
            'accent' => '#39ff14', 'border' => 'rgba(0, 255, 65, 0.3)',
            'gradient' => 'linear-gradient(135deg, #00ff41 0%, #001a0a 100%)',
            'background_image' => '', 'font_family' => '"Courier New", monospace', 'border_radius' => '4',
            'is_public' => 1, 'category' => 'tech',
        ],
        'neon_pink' => [
            'name' => 'Neon Pink',
            'primary' => '#ff10f0', 'secondary' => '#1a0030', 'background' => '#0a0014',
            'surface' => 'rgba(255, 16, 240, 0.05)', 'text' => '#ffffff', 'text_dim' => '#ff8af0',
            'accent' => '#ff10f0', 'border' => 'rgba(255, 16, 240, 0.4)',
            'gradient' => 'linear-gradient(135deg, #ff10f0 0%, #7928ca 50%, #1a0030 100%)',
            'background_image' => '', 'font_family' => 'system-ui, sans-serif', 'border_radius' => '14',
            'is_public' => 1, 'category' => 'vibrant',
        ],
        'forest' => [
            'name' => 'Forest Calm',
            'primary' => '#4ade80', 'secondary' => '#1a3a1a', 'background' => '#0a1f0a',
            'surface' => 'rgba(255, 255, 255, 0.05)', 'text' => '#ffffff', 'text_dim' => '#86efac',
            'accent' => '#22c55e', 'border' => 'rgba(74, 222, 128, 0.3)',
            'gradient' => 'linear-gradient(135deg, #4ade80 0%, #1a3a1a 100%)',
            'background_image' => '', 'font_family' => 'system-ui, sans-serif', 'border_radius' => '12',
            'is_public' => 1, 'category' => 'nature',
        ],
        'pure_light' => [
            'name' => 'Pure Light',
            'primary' => '#2563eb', 'secondary' => '#f1f5f9', 'background' => '#ffffff',
            'surface' => 'rgba(0, 0, 0, 0.03)', 'text' => '#0f172a', 'text_dim' => '#64748b',
            'accent' => '#3b82f6', 'border' => 'rgba(0, 0, 0, 0.1)',
            'gradient' => 'linear-gradient(135deg, #2563eb 0%, #60a5fa 100%)',
            'background_image' => '', 'font_family' => 'system-ui, sans-serif', 'border_radius' => '8',
            'is_public' => 1, 'category' => 'minimal',
        ],
        'midnight_purple' => [
            'name' => 'Midnight Purple',
            'primary' => '#a855f7', 'secondary' => '#1e1b4b', 'background' => '#0c0a1e',
            'surface' => 'rgba(168, 85, 247, 0.05)', 'text' => '#ffffff', 'text_dim' => '#c4b5fd',
            'accent' => '#c084fc', 'border' => 'rgba(168, 85, 247, 0.3)',
            'gradient' => 'linear-gradient(135deg, #a855f7 0%, #ec4899 50%, #1e1b4b 100%)',
            'background_image' => '', 'font_family' => 'system-ui, sans-serif', 'border_radius' => '14',
            'is_public' => 1, 'category' => 'cosmic',
        ],
    ];

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getPresets() { return $this->presets; }

    public function getPreset($key) { return $this->presets[$key] ?? null; }

    public function createTheme($userId, $data) {
        $themeData = [
            'user_id' => $userId,
            'name' => $data['name'] ?? 'بدون نام',
            'description' => $data['description'] ?? '',
            'primary' => $data['primary'] ?? '#d4af37',
            'secondary' => $data['secondary'] ?? '#1a0030',
            'background' => $data['background'] ?? '#0a0118',
            'surface' => $data['surface'] ?? 'rgba(255,255,255,0.05)',
            'text' => $data['text'] ?? '#ffffff',
            'text_dim' => $data['text_dim'] ?? '#a0a0c0',
            'accent' => $data['accent'] ?? '#b19cd9',
            'border' => $data['border'] ?? 'rgba(212,175,55,0.3)',
            'gradient' => $data['gradient'] ?? 'linear-gradient(135deg, #d4af37, #1a0030)',
            'background_image' => $data['background_image'] ?? '',
            'font_family' => $data['font_family'] ?? 'system-ui, sans-serif',
            'border_radius' => (int)($data['border_radius'] ?? 12),
            'animation_speed' => $data['animation_speed'] ?? 'normal',
            'is_public' => !empty($data['is_public']) ? 1 : 0,
            'is_dark' => !empty($data['is_dark']) ? 1 : 0,
            'category' => $data['category'] ?? 'custom',
            'config_json' => isset($data['config_json']) ? json_encode($data['config_json']) : '{}',
        ];
        $cols = implode(',', array_keys($themeData));
        $placeholders = implode(',', array_fill(0, count($themeData), '?'));
        $stmt = $this->db->prepare("INSERT INTO themes ($cols, created_at) VALUES ($placeholders, NOW())");
        $stmt->execute(array_values($themeData));
        return $this->db->lastInsertId();
    }

    public function getThemeById($themeId) {
        $stmt = $this->db->prepare("SELECT * FROM themes WHERE id = ?");
        $stmt->execute([$themeId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getUserThemes($userId) {
        $stmt = $this->db->prepare("SELECT * FROM themes WHERE user_id = ? ORDER BY updated_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getActiveUserTheme($userId) {
        $stmt = $this->db->prepare("SELECT t.* FROM themes t
            JOIN user_active_theme uat ON uat.theme_id = t.id
            WHERE uat.user_id = ?");
        $stmt->execute([$userId]);
        $t = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$t) {
            $t = $this->getPreset('cosmic_gold');
            $t['is_preset'] = true;
        }
        return $t;
    }

    public function setActiveTheme($userId, $themeId) {
        if ($themeId === null || $themeId === 'preset_cosmic_gold') {
            $this->db->prepare("DELETE FROM user_active_theme WHERE user_id = ?")->execute([$userId]);
            return ['theme_id' => null, 'preset' => 'cosmic_gold'];
        }
        $theme = $this->getThemeById($themeId);
        if (!$theme) throw new Exception('theme_not_found');
        if ($theme['user_id'] != $userId && !$theme['is_public']) {
            throw new Exception('not_authorized');
        }
        $stmt = $this->db->prepare("INSERT INTO user_active_theme (user_id, theme_id, applied_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE theme_id = VALUES(theme_id), applied_at = NOW()");
        $stmt->execute([$userId, $themeId]);
        return ['theme_id' => $themeId];
    }

    public function updateTheme($themeId, $userId, $data) {
        $theme = $this->getThemeById($themeId);
        if (!$theme || $theme['user_id'] != $userId) throw new Exception('not_authorized');
        $allowed = ['name', 'description', 'primary', 'secondary', 'background', 'surface', 'text', 'text_dim', 'accent', 'border', 'gradient', 'background_image', 'font_family', 'border_radius', 'animation_speed', 'is_public', 'is_dark', 'category', 'config_json'];
        $sets = []; $params = [];
        foreach ($data as $k => $v) {
            if (in_array($k, $allowed)) {
                $sets[] = "$k = ?";
                $params[] = is_array($v) ? json_encode($v) : $v;
            }
        }
        if (!$sets) return false;
        $sets[] = "updated_at = NOW()";
        $params[] = $themeId;
        $this->db->prepare("UPDATE themes SET " . implode(',', $sets) . " WHERE id = ?")->execute($params);
    }

    public function deleteTheme($themeId, $userId) {
        $theme = $this->getThemeById($themeId);
        if (!$theme || $theme['user_id'] != $userId) throw new Exception('not_authorized');
        $this->db->prepare("DELETE FROM themes WHERE id = ?")->execute([$themeId]);
    }

    public function duplicateTheme($themeId, $userId, $newName) {
        $theme = $this->getThemeById($themeId);
        if (!$theme) throw new Exception('theme_not_found');
        $copy = $theme;
        unset($copy['id']);
        $copy['user_id'] = $userId;
        $copy['name'] = $newName ?: ($theme['name'] . ' (کپی)');
        $copy['is_public'] = 0;
        $cols = array_keys($copy);
        $vals = array_values($copy);
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $stmt = $this->db->prepare("INSERT INTO themes (" . implode(',', $cols) . ", created_at) VALUES ($placeholders, NOW())");
        $stmt->execute($vals);
        return $this->db->lastInsertId();
    }

    // ====== Marketplace ======
    public function browseMarketplace($category = null, $sort = 'popular', $limit = 30) {
        $sql = "SELECT t.*, u.display_name as author_name, u.username as author_username, u.avatar as author_avatar
            FROM themes t
            JOIN users u ON u.id = t.user_id
            WHERE t.is_public = 1";
        $params = [];
        if ($category) { $sql .= " AND t.category = ?"; $params[] = $category; }
        switch ($sort) {
            case 'newest': $sql .= " ORDER BY t.created_at DESC"; break;
            case 'top_rated': $sql .= " ORDER BY t.avg_rating DESC, t.rating_count DESC"; break;
            case 'most_used': $sql .= " ORDER BY t.use_count DESC"; break;
            default: $sql .= " ORDER BY t.use_count DESC, t.avg_rating DESC";
        }
        $sql .= " LIMIT ?";
        $params[] = $limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCategories() {
        return [
            ['id' => 'cosmic', 'name' => 'کیهانی', 'icon' => '🌌'],
            ['id' => 'nature', 'name' => 'طبیعت', 'icon' => '🌿'],
            ['id' => 'warm', 'name' => 'گرم', 'icon' => '🔥'],
            ['id' => 'tech', 'name' => 'تکنولوژی', 'icon' => '💻'],
            ['id' => 'vibrant', 'name' => 'پرجنب‌وجوش', 'icon' => '🎨'],
            ['id' => 'minimal', 'name' => 'مینیمال', 'icon' => '◻️'],
            ['id' => 'custom', 'name' => 'سفارشی', 'icon' => '✨'],
        ];
    }

    public function incrementUseCount($themeId) {
        $this->db->prepare("UPDATE themes SET use_count = use_count + 1 WHERE id = ?")->execute([$themeId]);
    }

    // ====== Rating ======
    public function rateTheme($themeId, $userId, $rating) {
        $rating = max(1, min(5, (int)$rating));
        $stmt = $this->db->prepare("INSERT INTO theme_ratings (theme_id, user_id, rating, rated_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE rating = VALUES(rating), rated_at = NOW()");
        $stmt->execute([$themeId, $userId, $rating]);
        $this->recalcAvg($themeId);
    }

    public function unrateTheme($themeId, $userId) {
        $this->db->prepare("DELETE FROM theme_ratings WHERE theme_id = ? AND user_id = ?")->execute([$themeId, $userId]);
        $this->recalcAvg($themeId);
    }

    private function recalcAvg($themeId) {
        $stmt = $this->db->prepare("UPDATE themes t
            SET avg_rating = (SELECT COALESCE(AVG(rating), 0) FROM theme_ratings WHERE theme_id = ?),
                rating_count = (SELECT COUNT(*) FROM theme_ratings WHERE theme_id = ?)
            WHERE id = ?");
        $stmt->execute([$themeId, $themeId, $themeId]);
    }

    public function getUserRating($themeId, $userId) {
        $stmt = $this->db->prepare("SELECT rating FROM theme_ratings WHERE theme_id = ? AND user_id = ?");
        $stmt->execute([$themeId, $userId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r ? (int)$r['rating'] : 0;
    }

    // ====== Generate CSS ======
    public function generateCSS($theme) {
        $css = ":root {
  --gold: {$theme['primary']};
  --secondary: {$theme['secondary']};
  --bg-deep: {$theme['background']};
  --glass-bg: {$theme['surface']};
  --text: {$theme['text']};
  --text-dim: {$theme['text_dim']};
  --accent: {$theme['accent']};
  --glass-border: {$theme['border']};
  --gradient-cosmic: {$theme['gradient']};
  --gradient-gold: linear-gradient(135deg, {$theme['primary']}, {$theme['accent']});
  --radius: {$theme['border_radius']}px;
  --font: {$theme['font_family']};
}
";
        if (!empty($theme['background_image'])) {
            $css .= "body { background-image: url('assets/uploads/{$theme['background_image']}') !important; background-size: cover !important; }\n";
        }
        if ($theme['animation_speed'] === 'slow') {
            $css .= "*, *::before, *::after { animation-duration: 1.5s !important; transition-duration: 0.6s !important; }\n";
        } elseif ($theme['animation_speed'] === 'fast') {
            $css .= "*, *::before, *::after { animation-duration: 0.3s !important; transition-duration: 0.15s !important; }\n";
        } elseif ($theme['animation_speed'] === 'none') {
            $css .= "*, *::before, *::after { animation: none !important; transition: none !important; }\n";
        }
        return $css;
    }

    public function exportTheme($theme) {
        $export = [
            'format_version' => '1.0',
            'name' => $theme['name'],
            'description' => $theme['description'],
            'colors' => [
                'primary' => $theme['primary'],
                'secondary' => $theme['secondary'],
                'background' => $theme['background'],
                'surface' => $theme['surface'],
                'text' => $theme['text'],
                'text_dim' => $theme['text_dim'],
                'accent' => $theme['accent'],
                'border' => $theme['border'],
            ],
            'gradient' => $theme['gradient'],
            'font_family' => $theme['font_family'],
            'border_radius' => (int)$theme['border_radius'],
            'animation_speed' => $theme['animation_speed'],
            'is_dark' => (bool)$theme['is_dark'],
            'category' => $theme['category'],
            'exported_at' => date('c'),
        ];
        return json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function importTheme($userId, $json) {
        $data = json_decode($json, true);
        if (!$data || !isset($data['colors'])) throw new Exception('invalid_theme_file');
        return $this->createTheme($userId, [
            'name' => ($data['name'] ?? 'Imported') . ' (Import)',
            'description' => $data['description'] ?? '',
            'primary' => $data['colors']['primary'] ?? '#d4af37',
            'secondary' => $data['colors']['secondary'] ?? '#1a0030',
            'background' => $data['colors']['background'] ?? '#0a0118',
            'surface' => $data['colors']['surface'] ?? 'rgba(255,255,255,0.05)',
            'text' => $data['colors']['text'] ?? '#ffffff',
            'text_dim' => $data['colors']['text_dim'] ?? '#a0a0c0',
            'accent' => $data['colors']['accent'] ?? '#b19cd9',
            'border' => $data['colors']['border'] ?? 'rgba(212,175,55,0.3)',
            'gradient' => $data['gradient'] ?? 'linear-gradient(135deg, #d4af37, #1a0030)',
            'font_family' => $data['font_family'] ?? 'system-ui',
            'border_radius' => $data['border_radius'] ?? 12,
            'animation_speed' => $data['animation_speed'] ?? 'normal',
            'is_dark' => !empty($data['is_dark']),
            'category' => $data['category'] ?? 'custom',
        ]);
    }

    public function getStats($userId) {
        $stmt = $this->db->prepare("SELECT
            (SELECT COUNT(*) FROM themes WHERE user_id = ?) as created,
            (SELECT COUNT(*) FROM themes WHERE user_id = ? AND is_public = 1) as public_themes,
            (SELECT COALESCE(SUM(use_count), 0) FROM themes WHERE user_id = ?) as total_uses,
            (SELECT COALESCE(AVG(avg_rating), 0) FROM themes WHERE user_id = ? AND rating_count > 0) as avg_rating");
        $stmt->execute([$userId, $userId, $userId, $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
