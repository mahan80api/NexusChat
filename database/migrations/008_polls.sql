-- ============================================
-- 008: Polls (نظرسنجی‌ها)
-- ============================================

CREATE TABLE IF NOT EXISTS polls (
  id INT AUTO_INCREMENT PRIMARY KEY,
  message_id INT NOT NULL UNIQUE,
  chat_id INT NOT NULL,
  creator_id INT NOT NULL,
  question VARCHAR(500) NOT NULL,
  type ENUM('single','multiple') DEFAULT 'single',
  is_anonymous TINYINT(1) DEFAULT 0,
  is_public_results TINYINT(1) DEFAULT 1,
  allows_change_vote TINYINT(1) DEFAULT 1,
  expires_at DATETIME NULL,
  closed_at DATETIME NULL,
  is_closed TINYINT(1) DEFAULT 0,
  total_votes INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
  FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE,
  FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_chat (chat_id, created_at DESC),
  INDEX idx_expires (expires_at, is_closed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS poll_options (
  id INT AUTO_INCREMENT PRIMARY KEY,
  poll_id INT NOT NULL,
  text VARCHAR(200) NOT NULL,
  sort_order INT DEFAULT 0,
  vote_count INT DEFAULT 0,
  FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE,
  INDEX idx_poll (poll_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS poll_votes (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  poll_id INT NOT NULL,
  option_id INT NOT NULL,
  user_id INT NOT NULL,
  voted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_vote (poll_id, option_id, user_id),
  FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE,
  FOREIGN KEY (option_id) REFERENCES poll_options(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_poll_user (poll_id, user_id),
  INDEX idx_option (option_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
