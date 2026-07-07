<?php
/**
 * TOTP — Time-based One-Time Password (RFC 6238)
 *
 * Compatible with Google Authenticator, Authy, 1Password, etc.
 */
declare(strict_types=1);

class TOTP
{
    private const PERIOD = 30;
    private const DIGITS = 6;
    private const ALGO   = 'sha1';

    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\s+/', '', $code);
        if (!preg_match('/^\d{6}$/', $code)) return false;
        $timestamp = time();
        for ($i = -$window; $i <= $window; $i++) {
            $t = intdiv($timestamp, self::PERIOD) + $i;
            if (hash_equals(self::hotp($secret, $t), $code)) {
                return true;
            }
        }
        return false;
    }

    public static function current(string $secret): string
    {
        $t = intdiv(time(), self::PERIOD);
        return self::hotp($secret, $t);
    }

    public static function otpauthUri(string $secret, string $label, string $issuer = 'NexusChat'): string
    {
        $label = rawurlencode($issuer) . ':' . rawurlencode($label);
        $params = http_build_query([
            'secret'    => $secret,
            'issuer'    => $issuer,
            'algorithm' => strtoupper(self::ALGO),
            'digits'    => self::DIGITS,
            'period'    => self::PERIOD,
        ]);
        return "otpauth://totp/{$label}?{$params}";
    }

    public static function qrCodeDataUri(string $otpauth): string
    {
        // minimal SVG QR-like rendering fallback (real impl uses chillerlan/php-qrcode)
        return 'data:image/svg+xml;utf8,' . rawurlencode(
            '<svg xmlns="http://www.w3.org/2000/svg" width="160" height="160"><rect width="100%" height="100%" fill="white"/><text x="50%" y="50%" text-anchor="middle" font-size="10" fill="black">' . htmlspecialchars($otpauth) . '</text></svg>'
        );
    }

    private static function hotp(string $secret, int $counter): string
    {
        $key  = self::base32Decode($secret);
        $bin  = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac(self::ALGO, $bin, $key, true);
        $ofs  = ord($hash[strlen($hash) - 1]) & 0x0F;
        $code = (
            ((ord($hash[$ofs])     & 0x7F) << 24) |
            ((ord($hash[$ofs + 1]) & 0xFF) << 16) |
            ((ord($hash[$ofs + 2]) & 0xFF) <<  8) |
             (ord($hash[$ofs + 3]) & 0xFF)
        ) % (10 ** self::DIGITS);
        return str_pad((string)$code, self::DIGITS, '0', STR_PAD_LEFT);
    }

    private static function base32Encode(string $bytes): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($bytes) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            if (strlen($chunk) < 5) $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $out .= $alphabet[bindec($chunk)];
        }
        return $out;
    }

    private static function base32Decode(string $b32): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $b32 = strtoupper(rtrim($b32, '='));
        $bits = '';
        foreach (str_split($b32) as $c) {
            $idx = strpos($alphabet, $c);
            if ($idx === false) return '';
            $bits .= str_pad(decbin($idx), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) $out .= chr(bindec($chunk));
        }
        return $out;
    }
}
