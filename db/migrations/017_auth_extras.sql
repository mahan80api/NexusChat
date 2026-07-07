-- ============================================
-- 017 — Auth extras (2FA, remember, password reset, audit log, rate limits)
-- ============================================

-- Users: add 2FA, failed login tracking, soft delete
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS totp_secret VARCHAR(64) NULL AFTER password_hash,
    ADD COLUMN IF NOT EXISTS totp_confirmed_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS failed_attempts INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS locked_until DATETIME NULL,
    ADD COLUMN IF NOT EXISTS last_login DATETIME NULL,
    ADD COLUMN IF NOT EXISTS last_ip VARCHAR(45) NULL,
    ADD COLUMN IF NOT EXISTS email_verified_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;

CREATE INDEX IF NOT EXISTS idx_users_locked ON users(locked_until);
CREATE INDEX IF NOT EXISTS idx_users_last_login ON users(last_login);

-- Rate limits
CREATE TABLE IF NOT EXISTS rate_limits (
    id           CHAR(40)      NOT NULL PRIMARY KEY,
    attempts     INT UNSIGNED  NOT NULL DEFAULT 0,
    last_attempt INT UNSIGNED  NOT NULL
) ENGINE=InnoDB;

-- Remember me tokens
CREATE TABLE IF NOT EXISTS remember_tokens (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    token_hash CHAR(64)     NOT NULL,
    expires_at DATETIME     NOT NULL,
    ip         VARCHAR(45)  NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rt_user (user_id),
    INDEX idx_rt_exp  (expires_at)
) ENGINE=InnoDB;

-- Password reset tokens
CREATE TABLE IF NOT EXISTS password_resets (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED  NOT NULL,
    token_hash CHAR(64)      NOT NULL,
    expires_at DATETIME      NOT NULL,
    used       TINYINT(1)    NOT NULL DEFAULT 0,
    ip         VARCHAR(45)   NULL,
    created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pr_user (user_id),
    INDEX idx_pr_hash (token_hash)
) ENGINE=InnoDB;

-- Audit log
CREATE TABLE IF NOT EXISTS audit_log (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event      VARCHAR(64)  NOT NULL,
    user_id    INT UNSIGNED NULL,
    meta       JSON         NULL,
    ip         VARCHAR(45)  NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_user    (user_id),
    INDEX idx_audit_event   (event),
    INDEX idx_audit_created (created_at)
) ENGINE=InnoDB;
