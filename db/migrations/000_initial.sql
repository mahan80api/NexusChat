-- ============================================
-- 000 — initial.sql
-- Base tables: users, sessions, chat, messages, etc.
-- ============================================

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(32) NOT NULL UNIQUE,
    email VARCHAR(128) NULL,
    phone VARCHAR(20) NULL,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(64) NOT NULL,
    avatar VARCHAR(255) NULL,
    bio VARCHAR(255) NULL,
    status_text VARCHAR(140) NULL,
    theme VARCHAR(32) DEFAULT 'cosmic',
    language VARCHAR(8) DEFAULT 'fa',
    role ENUM('user', 'admin', 'bot') DEFAULT 'user',
    is_online TINYINT(1) DEFAULT 0,
    dnd_until DATETIME NULL,
    wallet_pin VARCHAR(255) NULL,
    last_seen DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_online (is_online),
    INDEX idx_email (email),
    INDEX idx_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    ip VARCHAR(45),
    user_agent TEXT,
    payload TEXT,
    last_activity DATETIME NOT NULL,
    INDEX idx_user (user_id),
    INDEX idx_activity (last_activity),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chats (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type ENUM('private', 'group', 'channel', 'bot') DEFAULT 'private',
    name VARCHAR(128) NULL,
    description VARCHAR(255) NULL,
    avatar VARCHAR(255) NULL,
    owner_id BIGINT UNSIGNED NULL,
    is_public TINYINT(1) DEFAULT 0,
    pinned_message_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_type (type),
    INDEX idx_updated (updated_at),
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_members (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chat_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    role ENUM('owner', 'admin', 'member') DEFAULT 'member',
    unread_count INT UNSIGNED DEFAULT 0,
    last_read_message_id BIGINT UNSIGNED NULL,
    is_muted TINYINT(1) DEFAULT 0,
    is_pinned TINYINT(1) DEFAULT 0,
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_chat_user (chat_id, user_id),
    INDEX idx_user (user_id),
    FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chat_id BIGINT UNSIGNED NOT NULL,
    sender_id BIGINT UNSIGNED NULL,
    reply_to_id BIGINT UNSIGNED NULL,
    type ENUM('text', 'image', 'video', 'audio', 'voice', 'file', 'location', 'sticker', 'poll', 'contact', 'system') DEFAULT 'text',
    content TEXT,
    media_url VARCHAR(255) NULL,
    media_meta JSON NULL,
    forwarded_from BIGINT UNSIGNED NULL,
    is_edited TINYINT(1) DEFAULT 0,
    is_deleted TINYINT(1) DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_chat_created (chat_id, created_at),
    INDEX idx_sender (sender_id),
    INDEX idx_reply (reply_to_id),
    FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (reply_to_id) REFERENCES messages(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add wallet_pin to existing users table (idempotent for MySQL 5.7+)
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'wallet_pin');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE users ADD COLUMN wallet_pin VARCHAR(255) NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Same for dnd_until
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'dnd_until');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE users ADD COLUMN dnd_until DATETIME NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
