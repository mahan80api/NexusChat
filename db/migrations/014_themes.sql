-- ============================================
-- 🎨 Custom Themes System
-- ============================================

CREATE TABLE IF NOT EXISTS themes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(128) NOT NULL,
    description TEXT,
    primary_color VARCHAR(16) NOT NULL DEFAULT '#d4af37',
    secondary VARCHAR(32) NOT NULL DEFAULT '#1a0030',
    background VARCHAR(32) NOT NULL DEFAULT '#0a0118',
    surface VARCHAR(64) NOT NULL DEFAULT 'rgba(255,255,255,0.05)',
    text VARCHAR(16) NOT NULL DEFAULT '#ffffff',
    text_dim VARCHAR(16) NOT NULL DEFAULT '#a0a0c0',
    accent VARCHAR(16) NOT NULL DEFAULT '#b19cd9',
    border VARCHAR(64) NOT NULL DEFAULT 'rgba(212,175,55,0.3)',
    gradient VARCHAR(255) NOT NULL DEFAULT 'linear-gradient(135deg, #d4af37 0%, #1a0030 100%)',
    background_image VARCHAR(255),
    font_family VARCHAR(128) DEFAULT 'system-ui, sans-serif',
    border_radius INT UNSIGNED DEFAULT 12,
    animation_speed ENUM('slow', 'normal', 'fast', 'none') DEFAULT 'normal',
    is_public TINYINT(1) DEFAULT 0,
    is_dark TINYINT(1) DEFAULT 1,
    category ENUM('cosmic', 'nature', 'warm', 'tech', 'vibrant', 'minimal', 'custom') DEFAULT 'custom',
    use_count INT UNSIGNED DEFAULT 0,
    avg_rating DECIMAL(3, 2) DEFAULT 0,
    rating_count INT UNSIGNED DEFAULT 0,
    config_json JSON,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_public_rating (is_public, avg_rating DESC, use_count DESC),
    INDEX idx_category (category),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_active_theme (
    user_id BIGINT UNSIGNED PRIMARY KEY,
    theme_id BIGINT UNSIGNED NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (theme_id) REFERENCES themes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS theme_ratings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    theme_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    rated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_theme_user (theme_id, user_id),
    CHECK (rating >= 1 AND rating <= 5),
    FOREIGN KEY (theme_id) REFERENCES themes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
