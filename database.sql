-- ============================================
-- NexusChat Database Schema
-- ============================================

CREATE DATABASE IF NOT EXISTS nexuschat CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE nexuschat;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20) UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(100),
    bio TEXT,
    avatar VARCHAR(255) DEFAULT 'default.png',
    status_text VARCHAR(200),
    last_seen DATETIME,
    is_online TINYINT(1) DEFAULT 0,
    is_verified TINYINT(1) DEFAULT 0,
    public_key TEXT,
    private_key_encrypted TEXT,
    theme ENUM('dark','light','galaxy') DEFAULT 'galaxy',
    language VARCHAR(10) DEFAULT 'fa',
    dnd_until DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_online (is_online)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS chats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('private','group','channel') NOT NULL,
    name VARCHAR(150),
    description TEXT,
    avatar VARCHAR(255),
    created_by INT,
    is_encrypted TINYINT(1) DEFAULT 0,
    pinned_message_id INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_type (type)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS chat_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chat_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('owner','admin','member') DEFAULT 'member',
    is_muted TINYINT(1) DEFAULT 0,
    is_pinned TINYINT(1) DEFAULT 0,
    unread_count INT DEFAULT 0,
    last_read_message_id INT,
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_member (chat_id, user_id),
    FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chat_id INT NOT NULL,
    sender_id INT NOT NULL,
    reply_to_id INT,
    forwarded_from_id INT NULL,
    forwarded_from_chat_id INT NULL,
    forwarded_from_sender_id INT NULL,
    content TEXT,
    encrypted_content LONGTEXT,
    type ENUM('text','image','video','file','voice','location','contact','sticker','poll') DEFAULT 'text',
    file_path VARCHAR(255),
    file_size BIGINT,
    mime_type VARCHAR(100),
    thumbnail VARCHAR(255),
    duration INT NULL,
    waveform_path VARCHAR(255) NULL,
    poll_id INT NULL,
    is_encrypted TINYINT(1) DEFAULT 0,
    is_edited TINYINT(1) DEFAULT 0,
    is_deleted TINYINT(1) DEFAULT 0,
    is_pinned TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reply_to_id) REFERENCES messages(id) ON DELETE SET NULL,
    FOREIGN KEY (forwarded_from_id) REFERENCES messages(id) ON DELETE SET NULL,
    FOREIGN KEY (forwarded_from_chat_id) REFERENCES chats(id) ON DELETE SET NULL,
    FOREIGN KEY (forwarded_from_sender_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_chat (chat_id, created_at),
    INDEX idx_sender (sender_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS message_reactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    user_id INT NOT NULL,
    emoji VARCHAR(10) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_reaction (message_id, user_id, emoji),
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS stories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    media_path VARCHAR(255) NOT NULL,
    media_type ENUM('image','video') DEFAULT 'image',
    caption TEXT,
    views_count INT DEFAULT 0,
    expires_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_expires (user_id, expires_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS story_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    story_id INT NOT NULL,
    user_id INT NOT NULL,
    viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_view (story_id, user_id),
    FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS calls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chat_id INT NOT NULL,
    caller_id INT NOT NULL,
    type ENUM('audio','video') NOT NULL,
    status ENUM('ringing','active','ended','missed','rejected') DEFAULT 'ringing',
    started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    ended_at DATETIME,
    duration INT DEFAULT 0,
    FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE,
    FOREIGN KEY (caller_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_chat (chat_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50),
    title VARCHAR(200),
    body TEXT,
    related_id INT,
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS blocked_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    blocked_user_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_block (user_id, blocked_user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (blocked_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_token VARCHAR(255) UNIQUE NOT NULL,
    device_info TEXT,
    ip_address VARCHAR(45),
    expires_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (session_token)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS saved_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message_id INT NOT NULL,
    saved_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_saved (user_id, message_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS polls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chat_id INT NOT NULL,
    created_by INT NOT NULL,
    question VARCHAR(500) NOT NULL,
    is_multiple TINYINT(1) DEFAULT 0,
    is_anonymous TINYINT(1) DEFAULT 0,
    closes_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS poll_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    poll_id INT NOT NULL,
    text VARCHAR(300) NOT NULL,
    position INT DEFAULT 0,
    FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS poll_votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    poll_id INT NOT NULL,
    option_id INT NOT NULL,
    user_id INT NOT NULL,
    voted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_vote (poll_id, option_id, user_id),
    FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE,
    FOREIGN KEY (option_id) REFERENCES poll_options(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS message_link_previews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    url TEXT NOT NULL,
    title VARCHAR(300),
    description TEXT,
    image_url TEXT,
    site_name VARCHAR(100),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS stickers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pack_id INT,
    name VARCHAR(100),
    image_path VARCHAR(255) NOT NULL,
    emoji VARCHAR(10),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sticker_packs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    cover VARCHAR(255),
    is_default TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ALTER existing DB to add new columns
ALTER TABLE messages
    ADD COLUMN IF NOT EXISTS forwarded_from_id INT NULL,
    ADD COLUMN IF NOT EXISTS forwarded_from_chat_id INT NULL,
    ADD COLUMN IF NOT EXISTS forwarded_from_sender_id INT NULL,
    ADD COLUMN IF NOT EXISTS duration INT NULL,
    ADD COLUMN IF NOT EXISTS waveform_path VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS poll_id INT NULL;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS dnd_until DATETIME NULL;
