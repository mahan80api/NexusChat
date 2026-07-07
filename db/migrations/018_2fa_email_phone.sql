-- ============================================
-- 018 — Email/Phone verification, 2FA backup codes, countries
-- ============================================

-- Users: add phone, country, phone_e164, verifications
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS phone VARCHAR(20)        NULL AFTER email,
    ADD COLUMN IF NOT EXISTS phone_e164 VARCHAR(20)  NULL,
    ADD COLUMN IF NOT EXISTS country_code VARCHAR(8) NULL,
    ADD COLUMN IF NOT EXISTS email_verified_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS phone_verified_at DATETIME NULL,
    ADD INDEX IF NOT EXISTS idx_users_phone_e164 (phone_e164);

-- Generic verification codes (email/phone/2fa backup)
CREATE TABLE IF NOT EXISTS verify_codes (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    channel    VARCHAR(20)  NOT NULL,        -- 'email' | 'phone' | '2fa_backup'
    code       VARCHAR(64)  NOT NULL,
    meta       JSON         NULL,            -- e.g. backup-codes list
    consumed   TINYINT(1)   NOT NULL DEFAULT 0,
    expires_at DATETIME     NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_vc_user    (user_id),
    INDEX idx_vc_channel (channel),
    INDEX idx_vc_code    (code),
    INDEX idx_vc_exp     (expires_at)
) ENGINE=InnoDB;

-- Password reset: add channel
ALTER TABLE password_resets
    ADD COLUMN IF NOT EXISTS channel VARCHAR(20) NULL AFTER ip;

-- Cache countries
CREATE TABLE IF NOT EXISTS countries_cache (
    code         VARCHAR(8)   NOT NULL PRIMARY KEY, -- e.g. 'IR', 'US'
    name_en      VARCHAR(64)  NOT NULL,
    name_fa      VARCHAR(64)  NULL,
    dial_code    VARCHAR(8)   NOT NULL,             -- e.g. '+98'
    flag_emoji   VARCHAR(8)   NOT NULL,
    phone_length TINYINT UNSIGNED NULL,
    updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
