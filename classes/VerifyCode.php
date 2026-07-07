<?php
/**
 * VerifyCode — short-lived 6-digit codes for email/phone/2FA backup
 */
declare(strict_types=1);

class VerifyCode
{
    /**
     * Create a 6-digit code, store in DB, return the code.
     * TTL in seconds.
     */
    public static function create(int $userId, string $channel, int $ttlSec = 600): string
    {
        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Database::getInstance()->query(
            "INSERT INTO verify_codes (user_id, channel, code, expires_at) VALUES (?, ?, ?, FROM_UNIXTIME(?))",
            [$userId, $channel, $code, time() + $ttlSec]
        );
        return $code;
    }

    public static function consume(int $userId, string $channel, string $code): bool
    {
        $db = Database::getInstance();
        $row = $db->fetch(
            "SELECT * FROM verify_codes
             WHERE user_id = ? AND channel = ? AND code = ? AND consumed = 0 AND expires_at > NOW()
             ORDER BY id DESC LIMIT 1",
            [$userId, $channel, $code]
        );
        if (!$row) return false;
        $db->query("UPDATE verify_codes SET consumed = 1 WHERE id = ?", [$row['id']]);
        return true;
    }

    /**
     * Generate 10 single-use backup codes for 2FA.
     * Each is random 8-char alphanumeric; stored hashed.
     */
    public static function createBackupCodes(int $userId, int $count = 10): array
    {
        $codes = [];
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        for ($i = 0; $i < $count; $i++) {
            $c = '';
            for ($j = 0; $j < 8; $j++) $c .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            $codes[] = $c;
        }
        $hashes = array_map('hash', ['sha256'], $codes);
        $meta = $codes; // keep plaintext to return ONCE to user
        Database::getInstance()->query(
            "INSERT INTO verify_codes (user_id, channel, code, meta, expires_at) VALUES (?, '2fa_backup', ?, ?, FROM_UNIXTIME(?))",
            [$userId, $hashes[0], json_encode(['all_hashes' => $hashes]), time() + 60 * 60 * 24 * 365]
        );
        return $codes;
    }

    public static function revokeByUser(int $userId, string $channel): void
    {
        Database::getInstance()->query("DELETE FROM verify_codes WHERE user_id = ? AND channel = ?", [$userId, $channel]);
    }
}
