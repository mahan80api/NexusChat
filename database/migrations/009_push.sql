-- ============================================
-- 009: Push Notifications
-- ============================================

CREATE TABLE IF NOT EXISTS push_subscriptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  endpoint TEXT NOT NULL,
  p256dh VARCHAR(255) NOT NULL,
  auth VARCHAR(255) NOT NULL,
  user_agent VARCHAR(500),
  device_name VARCHAR(100),
  is_active TINYINT(1) DEFAULT 1,
  last_used_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_user_endpoint (user_id, endpoint(500)),
  INDEX idx_user (user_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_preferences (
  user_id INT PRIMARY KEY,
  -- Master switches
  enabled TINYINT(1) DEFAULT 1,
  sound_enabled TINYINT(1) DEFAULT 1,
  vibration_enabled TINYINT(1) DEFAULT 1,
  desktop_enabled TINYINT(1) DEFAULT 1,
  mobile_enabled TINYINT(1) DEFAULT 1,
  email_enabled TINYINT(1) DEFAULT 0,
  -- Per-event type
  notify_new_message TINYINT(1) DEFAULT 1,
  notify_mention TINYINT(1) DEFAULT 1,
  notify_reply TINYINT(1) DEFAULT 1,
  notify_reaction TINYINT(1) DEFAULT 0,
  notify_call TINYINT(1) DEFAULT 1,
  notify_poll TINYINT(1) DEFAULT 1,
  notify_story TINYINT(1) DEFAULT 1,
  notify_group_message TINYINT(1) DEFAULT 1,
  -- Privacy
  show_preview TINYINT(1) DEFAULT 1,
  show_sender TINYINT(1) DEFAULT 1,
  show_content TINYINT(1) DEFAULT 1,
  -- Quiet hours
  quiet_hours_enabled TINYINT(1) DEFAULT 0,
  quiet_hours_start TIME DEFAULT '23:00:00',
  quiet_hours_end TIME DEFAULT '08:00:00',
  -- Mentions override
  notify_mention_in_quiet TINYINT(1) DEFAULT 1,
  -- Mute
  muted_until DATETIME NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_log (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  type VARCHAR(50) NOT NULL,
  title VARCHAR(255) NOT NULL,
  body TEXT,
  data JSON,
  status ENUM('pending','sent','clicked','dismissed','failed') DEFAULT 'pending',
  error TEXT,
  sent_at TIMESTAMP NULL,
  clicked_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_created (user_id, created_at DESC),
  INDEX idx_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_notification_overrides (
  chat_id INT NOT NULL,
  user_id INT NOT NULL,
  mode ENUM('default','all','mentions','muted','disabled') DEFAULT 'default',
  custom_sound VARCHAR(100),
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (chat_id, user_id),
  FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
