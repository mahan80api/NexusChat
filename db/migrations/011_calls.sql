-- ============================================
-- 📞 Calls System Database Schema
-- WebRTC voice/video calls with signaling
-- ============================================

CREATE TABLE IF NOT EXISTS calls (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    call_id CHAR(32) NOT NULL UNIQUE,
    chat_id BIGINT UNSIGNED NOT NULL,
    caller_id BIGINT UNSIGNED NOT NULL,
    call_type ENUM('voice', 'video', 'screen') DEFAULT 'voice',
    is_group_call TINYINT(1) DEFAULT 0,
    status ENUM('ringing', 'active', 'ended', 'missed', 'rejected', 'busy', 'failed') DEFAULT 'ringing',
    started_at DATETIME NULL,
    answered_at DATETIME NULL,
    ended_at DATETIME NULL,
    end_reason VARCHAR(50) NULL,
    recording_url VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_chat (chat_id),
    INDEX idx_caller (caller_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at),
    FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE,
    FOREIGN KEY (caller_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS call_participants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    call_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    status ENUM('invited', 'joined', 'left', 'rejected', 'busy') DEFAULT 'invited',
    audio_enabled TINYINT(1) DEFAULT 1,
    video_enabled TINYINT(1) DEFAULT 1,
    screen_sharing TINYINT(1) DEFAULT 0,
    joined_at DATETIME NULL,
    left_at DATETIME NULL,
    UNIQUE KEY uk_call_user (call_id, user_id),
    INDEX idx_user (user_id),
    FOREIGN KEY (call_id) REFERENCES calls(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS call_signals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    call_id CHAR(32) NOT NULL,
    from_user_id BIGINT UNSIGNED NOT NULL,
    to_user_id BIGINT UNSIGNED NOT NULL,
    signal_type ENUM('offer', 'answer', 'ice-candidate', 'renegotiate', 'bye', 'mute', 'unmute', 'video-on', 'video-off', 'screen-share') NOT NULL,
    payload JSON NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_call_to (call_id, to_user_id, id),
    INDEX idx_call_from (call_id, from_user_id, id),
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
