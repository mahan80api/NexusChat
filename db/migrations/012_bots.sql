-- ============================================
-- 🤖 Bots System Database Schema
-- Telegram-like bot platform
-- ============================================

CREATE TABLE IF NOT EXISTS bots (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(64) NOT NULL,
    username VARCHAR(32) NOT NULL UNIQUE,
    description VARCHAR(500),
    avatar VARCHAR(255),
    token CHAR(40) NOT NULL UNIQUE,
    is_public TINYINT(1) DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    install_count INT UNSIGNED DEFAULT 0,
    webhook_url VARCHAR(255),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_owner (owner_id),
    INDEX idx_username (username),
    INDEX idx_active (is_active, is_public),
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bot_commands (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bot_id BIGINT UNSIGNED NOT NULL,
    command VARCHAR(64) NOT NULL,
    description VARCHAR(255),
    response JSON NOT NULL,
    is_inline TINYINT(1) DEFAULT 0,
    usage_count INT UNSIGNED DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_bot_command (bot_id, command),
    FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bot_installations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bot_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    chat_id BIGINT UNSIGNED NULL,
    is_enabled TINYINT(1) DEFAULT 1,
    installed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_chat (chat_id),
    INDEX idx_bot (bot_id),
    FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bot_hooks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bot_id BIGINT UNSIGNED NOT NULL,
    hook_name VARCHAR(64) NOT NULL,
    config JSON,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_bot_hook (bot_id, hook_name),
    FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bot_stats (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bot_id BIGINT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    metric VARCHAR(32) NOT NULL,
    value INT UNSIGNED DEFAULT 0,
    UNIQUE KEY uk_bot_date_metric (bot_id, date, metric),
    INDEX idx_date (date),
    FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bot messages (synthetic sender, for /commands)
ALTER TABLE messages
  ADD COLUMN bot_id BIGINT UNSIGNED NULL AFTER sender_id,
  ADD INDEX idx_bot (bot_id),
  ADD FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE SET NULL;
