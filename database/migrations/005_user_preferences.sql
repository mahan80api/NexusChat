-- ============================================
-- 005: User preferences, DND, mutes, scheduled status
-- ============================================

CREATE TABLE IF NOT EXISTS user_preferences (
  user_id INT PRIMARY KEY,
  dnd_enabled TINYINT(1) DEFAULT 0,
  dnd_until DATETIME DEFAULT NULL,
  dnd_allow_mentions TINYINT(1) DEFAULT 1,
  dnd_allow_messages_from TEXT DEFAULT NULL COMMENT 'JSON: user IDs always allowed',
  dnd_allow_calls TINYINT(1) DEFAULT 0,
  dnd_show_in_status TINYINT(1) DEFAULT 0,
  notification_sound VARCHAR(50) DEFAULT 'default',
  notification_volume TINYINT DEFAULT 80,
  desktop_notifications TINYINT(1) DEFAULT 1,
  email_notifications TINYINT(1) DEFAULT 0,
  message_preview TINYINT(1) DEFAULT 1,
  vibration TINYINT(1) DEFAULT 1,
  read_receipts TINYINT(1) DEFAULT 1,
  typing_indicators TINYINT(1) DEFAULT 1,
  last_seen_visibility ENUM('everyone', 'contacts', 'nobody') DEFAULT 'everyone',
  profile_photo_visibility ENUM('everyone', 'contacts', 'nobody') DEFAULT 'everyone',
  call_visibility ENUM('everyone', 'contacts', 'nobody') DEFAULT 'everyone',
  forward_privacy TINYINT(1) DEFAULT 1 COMMENT '0 = allow forwards, 1 = restrict',
  auto_download_media TINYINT(1) DEFAULT 1,
  language VARCHAR(10) DEFAULT 'fa',
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_mutes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  chat_id INT NOT NULL,
  user_id INT NOT NULL,
  muted_until DATETIME DEFAULT NULL COMMENT 'NULL = indefinite',
  mute_notifications TINYINT(1) DEFAULT 1,
  mute_calls TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_mute (chat_id, user_id),
  FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS saved_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  message_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_saved (user_id, message_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
  INDEX idx_user_created (user_id, created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pinned_messages (
  user_id INT NOT NULL,
  chat_id INT NOT NULL,
  message_id INT NOT NULL,
  pinned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, chat_id, message_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE,
  FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  chat_id INT,
  message_id INT,
  type VARCHAR(30) DEFAULT 'message' COMMENT 'message, mention, call, reaction, etc.',
  delivered TINYINT(1) DEFAULT 1,
  silenced_reason VARCHAR(50) DEFAULT NULL COMMENT 'dnd, mute, blocked, etc.',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_created (user_id, created_at DESC),
  INDEX idx_silenced (user_id, silenced_reason, created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
