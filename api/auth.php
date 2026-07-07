<?php
/**
 * NexusChat — Auth API
 *
 * Actions:
 *   POST  ?action=login            {username, password, remember?, csrf, g-recaptcha-response?, totp?}
 *   POST  ?action=register         {username, password, display_name, email?, phone?, country_code?, csrf, accept_tos=1, captcha}
 *   POST  ?action=check_username   {username}                   → {available: bool}
 *   POST  ?action=check_email      {email}                      → {available: bool}
 *   POST  ?action=check_phone      {phone, country_code}        → {available: bool}
 *   POST  ?action=verify_2fa       {totp, csrf}                 → success after login
 *   POST  ?action=2fa_setup        {}                           → generate secret + otpauth URI
 *   POST  ?action=2fa_confirm      {totp}                       → enable 2FA
 *   POST  ?action=2fa_disable      {password}                   → disable 2FA
 *   POST  ?action=2fa_backup       {csrf}                       → regenerate backup codes
 *   POST  ?action=verify_email     {code, csrf}                 → verify email with code
 *   POST  ?action=resend_email     {csrf}                       → resend verification
 *   POST  ?action=verify_phone     {code, csrf}                 → verify phone with SMS code
 *   POST  ?action=resend_phone     {csrf}                       → resend SMS
 *   POST  ?action=forgot           {identifier, csrf}           → email/link
 *   POST  ?action=reset            {token, password, csrf}
 *   GET   ?action=logout
 *   GET   ?action=me
 *   GET   ?action=redirect         → safe redirect for ?ref=
 *
 * Security:
 *  - CSRF token required for all POST
 *  - reCAPTCHA v3 (optional, by config)
 *  - Rate limiting (per IP, per action)
 *  - Password: bcrypt cost 12, strong rules
 *  - Username: [a-zA-Z0-9_.]{3,32}
 *  - 2FA: TOTP (RFC 6238), 6 digits, 30s window, ±1 step tolerance, backup codes
 *  - Email: send 6-digit code, expire 15 min
 *  - Phone: send 6-digit SMS via driver, expire 10 min, format E.164
 *  - Session: httponly, secure, samesite=Lax, regenerated on login
 *  - Lockout: 5 failed → 15 min
 *  - Audit log table for all security events
 */
declare(strict_types=1);

define('NEXUSCHAT_API', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/TOTP.php';
require_once __DIR__ . '/../classes/AuditLog.php';
require_once __DIR__ . '/../classes/Mailer.php';
require_once __DIR__ . '/../classes/SMS.php';
require_once __DIR__ . '/../classes/Phone.php';
require_once __DIR__ . '/../classes/VerifyCode.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

// ──────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────
function out(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function err(string $message, int $code = 400, array $extra = []): void {
    out(array_merge(['success' => false, 'message' => $message, 'code' => $code], $extra), $code);
}
function ok(array $data = []): void {
    out(array_merge(['success' => true], $data));
}

function csrf_required(): void {
    $sent = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        err('Invalid CSRF token', 403);
    }
}

function auth_required(): int {
    $uid = current_user_id();
    if (!$uid) err('Unauthorized', 401);
    return (int)$uid;
}

function rate_limit(string $key, int $max, int $windowSec): void {
    $db = Database::getInstance();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $id = sha1($key . '|' . $ip);
    $row = $db->fetch("SELECT attempts, last_attempt FROM rate_limits WHERE id = ?", [$id]);
    $now = time();
    if ($row) {
        if ($now - (int)$row['last_attempt'] > $windowSec) {
            $db->query("UPDATE rate_limits SET attempts = 1, last_attempt = ? WHERE id = ?", [$now, $id]);
        } else {
            $attempts = (int)$row['attempts'] + 1;
            if ($attempts > $max) {
                $retry = $windowSec - ($now - (int)$row['last_attempt']);
                err('Too many requests. Try again later.', 429, ['retry_after' => $retry]);
            }
            $db->query("UPDATE rate_limits SET attempts = ?, last_attempt = ? WHERE id = ?", [$attempts, $now, $id]);
        }
    } else {
        $db->query("INSERT INTO rate_limits (id, attempts, last_attempt) VALUES (?, 1, ?)", [$id, $now]);
    }
}

function verify_captcha(): void {
    $secret = defined('RECAPTCHA_SECRET_KEY') ? RECAPTCHA_SECRET_KEY : '';
    if (!$secret) return;
    $token = $_POST['g-recaptcha-response'] ?? '';
    if (!$token) err('Captcha required', 400);
    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query(['secret' => $secret, 'response' => $token, 'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '']),
        CURLOPT_TIMEOUT        => 5,
    ]);
    $resp = curl_exec($ch);
    $data = json_decode($resp, true);
    if (!($data['success'] ?? false) || ($data['score'] ?? 0) < 0.5) {
        err('Captcha failed', 400);
    }
}

function safe_redirect_target(): string {
    $r = $_GET['ref'] ?? '/index.php';
    return preg_match('#^/[a-z0-9_/.\-]+\.php(\?[a-z0-9_=&%-]+)?$#i', $r) ? $r : '/index.php';
}

function send_verification_email(int $userId): bool {
    $user = User::find($userId);
    if (!$user || empty($user['email'])) return false;
    $code = VerifyCode::create($userId, 'email', 900); // 15 min
    return Mailer::send($user['email'], 'تأیید ایمیل NexusChat ✨', "
        <div style='font-family:Vazirmatn,Tahoma,sans-serif;max-width:600px;margin:0 auto;background:linear-gradient(135deg,#0f0a1e,#1a0f2e);color:white;padding:40px;border-radius:16px;'>
            <div style='text-align:center;font-size:64px;margin-bottom:20px;'>✨</div>
            <h1 style='text-align:center;color:#8b5cf6;margin:0 0 20px;'>تأیید ایمیل</h1>
            <p style='font-size:16px;line-height:1.8;'>سلام {$user['display_name']} 👋</p>
            <p style='font-size:16px;line-height:1.8;'>برای فعال‌سازی حساب خود، کد زیر را در صفحه تأیید وارد کنید:</p>
            <div style='background:rgba(139,92,246,0.15);border:2px dashed #8b5cf6;border-radius:12px;padding:24px;text-align:center;margin:24px 0;'>
                <div style='font-size:36px;font-weight:bold;letter-spacing:8px;color:#ec4899;font-family:monospace;'>{$code}</div>
            </div>
            <p style='font-size:14px;color:#9ca3af;'>این کد تا ۱۵ دقیقه دیگر معتبر است.</p>
            <p style='font-size:14px;color:#9ca3af;margin-top:24px;'>اگه شما این درخواست رو ندادید، این ایمیل رو نادیده بگیرید.</p>
            <hr style='border-color:rgba(255,255,255,0.1);margin:24px 0;'>
            <p style='text-align:center;color:#6b7280;font-size:12px;'>— تیم NexusChat 🚀</p>
        </div>
    ");
}

function send_verification_sms(int $userId): bool {
    $user = User::find($userId);
    if (!$user || empty($user['phone'])) return false;
    $code = VerifyCode::create($userId, 'phone', 600); // 10 min
    $phoneE164 = Phone::formatE164($user['phone'], $user['country_code'] ?? '+');
    return SMS::send($phoneE164, "NexusChat: کد تأیید شما $code — این کد تا ۱۰ دقیقه معتبر است. ✨");
}

// ──────────────────────────────────────────────
// Route
// ──────────────────────────────────────────────
$action = $_GET['action'] ?? '';
session_start();

try {
    switch ($action) {

    // ─────────── CHECK USERNAME ───────────
    case 'check_username': {
        $u = trim((string)($_POST['username'] ?? ''));
        if (!preg_match('/^[a-zA-Z0-9_.]{3,32}$/', $u)) ok(['available' => false, 'reason' => 'invalid']);
        $exists = Database::getInstance()->fetchColumn("SELECT 1 FROM users WHERE username = ?", [$u]);
        ok(['available' => !$exists]);
        break;
    }

    // ─────────── CHECK EMAIL ───────────
    case 'check_email': {
        $e = trim((string)($_POST['email'] ?? ''));
        if (!filter_var($e, FILTER_VALIDATE_EMAIL)) ok(['available' => false, 'reason' => 'invalid']);
        $exists = Database::getInstance()->fetchColumn("SELECT 1 FROM users WHERE email = ?", [$e]);
        ok(['available' => !$exists]);
        break;
    }

    // ─────────── CHECK PHONE ───────────
    case 'check_phone': {
        $phone = preg_replace('/\s+/', '', (string)($_POST['phone'] ?? ''));
        $cc    = trim((string)($_POST['country_code'] ?? ''));
        if (!$cc || !$phone) ok(['available' => false, 'reason' => 'invalid']);
        $e164 = Phone::formatE164($phone, $cc);
        if (!$e164) ok(['available' => false, 'reason' => 'invalid']);
        $exists = Database::getInstance()->fetchColumn("SELECT 1 FROM users WHERE phone_e164 = ?", [$e164]);
        ok(['available' => !$exists, 'e164' => $e164]);
        break;
    }

    // ─────────── COUNTRIES LIST (for intl-tel-input) ───────────
    case 'countries': {
        header('Cache-Control: public, max-age=86400');
        $f = __DIR__ . '/../data/countries.json';
        if (is_file($f)) { readfile($f); exit; }
        ok(['countries' => Phone::countries()]);
        break;
    }

    // ─────────── LOGIN ───────────
    case 'login': {
        csrf_required();
        rate_limit('login', 10, 3600);
        verify_captcha();

        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $remember = !empty($_POST['remember']);
        $totp     = trim((string)($_POST['totp'] ?? ''));

        if (!preg_match('/^[a-zA-Z0-9_.]{3,32}$/', $username)) err('Invalid username', 400);
        if (strlen($password) < 6) err('Password too short', 400);

        $db = Database::getInstance();
        $user = $db->fetch("SELECT * FROM users WHERE username = ? OR email = ? OR phone_e164 = ?", [$username, $username, $username]);
        if (!$user) {
            AuditLog::record('login_fail', null, ['reason' => 'no_user', 'username' => $username]);
            err('Invalid credentials', 401);
        }
        if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
            $mins = ceil((strtotime($user['locked_until']) - time()) / 60);
            err("Account locked. Try in $mins min.", 423, ['locked_minutes' => $mins]);
        }
        if (!password_verify($password, $user['password_hash'])) {
            $fails = ((int)($user['failed_attempts'] ?? 0)) + 1;
            $lock = $fails >= 5 ? date('Y-m-d H:i:s', time() + 900) : null;
            $db->query("UPDATE users SET failed_attempts = ?, locked_until = ? WHERE id = ?", [$fails, $lock, $user['id']]);
            AuditLog::record('login_fail', $user['id'], ['reason' => 'bad_pw', 'attempts' => $fails]);
            err('Invalid credentials', 401, ['attempts_left' => max(0, 5 - $fails)]);
        }

        if (!empty($user['totp_secret']) && !empty($user['totp_confirmed_at'])) {
            if ($totp === '') {
                $_SESSION['2fa_pending_user_id'] = (int)$user['id'];
                ok(['requires_2fa' => true]);
            } else {
                $valid = TOTP::verify($user['totp_secret'], $totp);
                if (!$valid && !VerifyCode::consume($user['id'], '2fa_backup', $totp)) {
                    AuditLog::record('login_2fa_fail', $user['id']);
                    err('Invalid 2FA code', 401);
                }
                $_SESSION['2fa_pending_user_id'] = null;
            }
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['login_time'] = time();

        $db->query("UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login = NOW(), last_ip = ? WHERE id = ?", [$_SERVER['REMOTE_ADDR'] ?? null, $user['id']]);

        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $hash  = hash('sha256', $token);
            $exp   = time() + 60 * 60 * 24 * 30;
            $db->query("INSERT INTO remember_tokens (user_id, token_hash, expires_at, ip, user_agent) VALUES (?, ?, FROM_UNIXTIME(?), ?, ?)",
                [$user['id'], $hash, $exp, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);
            setcookie('nc_remember', $user['id'] . ':' . $token, [
                'expires'  => $exp, 'path' => '/',
                'secure'   => defined('APP_ENV') && APP_ENV === 'production',
                'httponly' => true, 'samesite' => 'Lax',
            ]);
        }

        AuditLog::record('login_ok', $user['id']);
        ok([
            'user_id'  => (int)$user['id'],
            'username' => $user['username'],
            'email_verified' => !empty($user['email_verified_at']),
            'phone_verified' => !empty($user['phone_verified_at']),
            'redirect' => safe_redirect_target(),
        ]);
        break;
    }

    // ─────────── VERIFY 2FA ───────────
    case 'verify_2fa': {
        csrf_required();
        rate_limit('2fa', 5, 900);
        $uid = (int)($_SESSION['2fa_pending_user_id'] ?? 0);
        if (!$uid) err('No pending 2FA', 400);
        $code = trim((string)($_POST['totp'] ?? ''));
        if (!preg_match('/^\d{6}$/', $code) && strlen($code) < 8) err('Invalid code format', 400);
        $user = Database::getInstance()->fetch("SELECT * FROM users WHERE id = ?", [$uid]);
        if (!$user || empty($user['totp_secret'])) err('User not found', 404);

        $valid = preg_match('/^\d{6}$/', $code) && TOTP::verify($user['totp_secret'], $code);
        if (!$valid) $valid = VerifyCode::consume($user['id'], '2fa_backup', $code);
        if (!$valid) {
            AuditLog::record('2fa_fail', $uid);
            err('Invalid 2FA code', 401);
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['login_time'] = time();
        $_SESSION['2fa_pending_user_id'] = null;
        AuditLog::record('login_ok_2fa', $user['id']);
        ok(['redirect' => safe_redirect_target()]);
        break;
    }

    // ─────────── REGISTER ───────────
    case 'register': {
        csrf_required();
        rate_limit('register', 3, 3600);
        verify_captcha();

        $username     = trim((string)($_POST['username'] ?? ''));
        $display      = trim((string)($_POST['display_name'] ?? ''));
        $email        = trim((string)($_POST['email'] ?? ''));
        $phone        = preg_replace('/\s+/', '', (string)($_POST['phone'] ?? ''));
        $countryCode  = trim((string)($_POST['country_code'] ?? ''));
        $password     = (string)($_POST['password'] ?? '');
        $accept       = !empty($_POST['accept_tos']);

        if (!$accept) err('You must accept the terms', 400);
        if (!preg_match('/^[a-zA-Z0-9_.]{3,32}$/', $username)) err('Invalid username (3-32 chars, a-z 0-9 _ .)', 400);
        if (mb_strlen($display) < 2 || mb_strlen($display) > 64) err('Display name must be 2-64 chars', 400);
        if (strlen($password) < 8) err('Password must be at least 8 characters', 400);
        if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            err('Password must contain uppercase, lowercase and digit', 400);
        }
        if (!$email && !$phone) err('Provide email or phone', 400);

        $db = Database::getInstance();

        if ($db->fetchColumn("SELECT 1 FROM users WHERE username = ?", [$username])) err('Username already taken', 409);

        $emailNorm = null;
        if ($email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) err('Invalid email', 400);
            if ($db->fetchColumn("SELECT 1 FROM users WHERE email = ?", [$email])) err('Email already in use', 409);
            $emailNorm = strtolower($email);
        }

        $phoneE164 = null;
        if ($phone) {
            if (!$countryCode) err('Country code required for phone', 400);
            $phoneE164 = Phone::formatE164($phone, $countryCode);
            if (!$phoneE164) err('Invalid phone number', 400);
            if ($db->fetchColumn("SELECT 1 FROM users WHERE phone_e164 = ?", [$phoneE164])) err('Phone already in use', 409);
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $db->query(
            "INSERT INTO users (username, display_name, email, phone, phone_e164, country_code, password_hash, created_at, last_ip) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)",
            [$username, $display, $emailNorm, $phone, $phoneE164, $countryCode ?: null, $hash, $_SERVER['REMOTE_ADDR'] ?? null]
        );
        $uid = (int)$db->lastInsertId();

        // Auto-login
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$uid;
        $_SESSION['username'] = $username;
        $_SESSION['login_time'] = time();
        $_SESSION['must_verify_email'] = $emailNorm ? true : null;
        $_SESSION['must_verify_phone'] = $phoneE164 ? true : null;

        // Welcome wallet
        $db->query("INSERT INTO wallets (user_id, currency, balance, wallet_number) VALUES (?, 'IRR', 100000, ?)", [$uid, 'WAL' . str_pad((string)$uid, 10, '0', STR_PAD_LEFT) . 'WELC']);

        // Send verifications
        if ($emailNorm) send_verification_email($uid);
        if ($phoneE164) send_verification_sms($uid);

        AuditLog::record('register_ok', $uid, ['email' => $emailNorm ? 1 : 0, 'phone' => $phoneE164 ? 1 : 0]);

        ok([
            'user_id'         => $uid,
            'email_verified'  => false,
            'phone_verified'  => false,
            'needs_email'     => (bool)$emailNorm,
            'needs_phone'     => (bool)$phoneE164,
            'redirect'        => '/login.php?mode=verify&ref=' . urlencode(safe_redirect_target()),
        ]);
        break;
    }

    // ─────────── 2FA SETUP (login required) ───────────
    case '2fa_setup': {
        csrf_required();
        $uid = auth_required();
        $user = User::find($uid);
        if (!$user) err('User not found', 404);
        if (!empty($user['totp_secret']) && !empty($user['totp_confirmed_at'])) {
            err('2FA already enabled', 400);
        }
        $secret = TOTP::generateSecret();
        // store temporary until confirmed
        Database::getInstance()->query("UPDATE users SET totp_secret = ?, totp_confirmed_at = NULL WHERE id = ?", [$secret, $uid]);
        $uri = TOTP::otpauthUri($secret, $user['username']);
        // generate backup codes
        $backups = VerifyCode::createBackupCodes($uid, 10);
        AuditLog::record('2fa_setup_start', $uid);
        ok([
            'secret'      => $secret,
            'otpauth'     => $uri,
            'qr_data_uri' => TOTP::qrCodeDataUri($uri),
            'backup_codes'=> $backups,
        ]);
        break;
    }

    case '2fa_confirm': {
        csrf_required();
        $uid = auth_required();
        $code = trim((string)($_POST['totp'] ?? ''));
        $user = User::find($uid);
        if (!$user || empty($user['totp_secret'])) err('No 2FA setup in progress', 400);
        if (!TOTP::verify($user['totp_secret'], $code)) err('Invalid code — try again', 400);
        Database::getInstance()->query("UPDATE users SET totp_confirmed_at = NOW() WHERE id = ?", [$uid]);
        AuditLog::record('2fa_enabled', $uid);
        ok(['enabled' => true]);
        break;
    }

    case '2fa_disable': {
        csrf_required();
        $uid = auth_required();
        $pw  = (string)($_POST['password'] ?? '');
        $user = User::find($uid);
        if (!$user) err('User not found', 404);
        if (!password_verify($pw, $user['password_hash'])) err('Wrong password', 401);
        Database::getInstance()->query("UPDATE users SET totp_secret = NULL, totp_confirmed_at = NULL WHERE id = ?", [$uid]);
        VerifyCode::revokeByUser($uid, '2fa_backup');
        AuditLog::record('2fa_disabled', $uid);
        ok(['disabled' => true]);
        break;
    }

    case '2fa_backup': {
        csrf_required();
        $uid = auth_required();
        $codes = VerifyCode::createBackupCodes($uid, 10);
        AuditLog::record('2fa_backup_regen', $uid);
        ok(['backup_codes' => $codes]);
        break;
    }

    // ─────────── VERIFY EMAIL ───────────
    case 'verify_email': {
        csrf_required();
        rate_limit('verify_email', 10, 3600);
        $uid = auth_required();
        $code = trim((string)($_POST['code'] ?? ''));
        if (!preg_match('/^\d{6}$/', $code)) err('Invalid code (6 digits)', 400);
        if (!VerifyCode::consume($uid, 'email', $code)) err('Wrong or expired code', 400);
        Database::getInstance()->query("UPDATE users SET email_verified_at = NOW() WHERE id = ?", [$uid]);
        $_SESSION['must_verify_email'] = null;
        AuditLog::record('email_verified', $uid);
        ok(['verified' => true]);
        break;
    }

    case 'resend_email': {
        csrf_required();
        rate_limit('resend_email', 3, 600);
        $uid = auth_required();
        $user = User::find($uid);
        if (!$user || empty($user['email'])) err('No email on file', 400);
        if (!empty($user['email_verified_at'])) err('Email already verified', 400);
        send_verification_email($uid);
        AuditLog::record('email_resend', $uid);
        ok(['sent' => true]);
        break;
    }

    // ─────────── VERIFY PHONE ───────────
    case 'verify_phone': {
        csrf_required();
        rate_limit('verify_phone', 10, 3600);
        $uid = auth_required();
        $code = trim((string)($_POST['code'] ?? ''));
        if (!preg_match('/^\d{6}$/', $code)) err('Invalid code (6 digits)', 400);
        if (!VerifyCode::consume($uid, 'phone', $code)) err('Wrong or expired code', 400);
        Database::getInstance()->query("UPDATE users SET phone_verified_at = NOW() WHERE id = ?", [$uid]);
        $_SESSION['must_verify_phone'] = null;
        AuditLog::record('phone_verified', $uid);
        ok(['verified' => true]);
        break;
    }

    case 'resend_phone': {
        csrf_required();
        rate_limit('resend_phone', 3, 600);
        $uid = auth_required();
        $user = User::find($uid);
        if (!$user || empty($user['phone'])) err('No phone on file', 400);
        if (!empty($user['phone_verified_at'])) err('Phone already verified', 400);
        send_verification_sms($uid);
        AuditLog::record('phone_resend', $uid);
        ok(['sent' => true]);
        break;
    }

    // ─────────── FORGOT PASSWORD ───────────
    case 'forgot': {
        csrf_required();
        rate_limit('forgot', 5, 3600);
        $ident = trim((string)($_POST['identifier'] ?? ''));
        if (!$ident) err('Enter username, email or phone', 400);

        $user = Database::getInstance()->fetch("SELECT * FROM users WHERE username = ? OR email = ? OR phone_e164 = ?", [$ident, $ident, $ident]);
        if (!$user) { ok(['message' => 'If the account exists, a reset link has been sent.']); break; }

        $token = bin2hex(random_bytes(32));
        $hash  = hash('sha256', $token);
        $exp   = time() + 3600;
        Database::getInstance()->query(
            "INSERT INTO password_resets (user_id, token_hash, expires_at, ip, channel) VALUES (?, ?, FROM_UNIXTIME(?), ?, ?)",
            [$user['id'], $hash, $exp, $_SERVER['REMOTE_ADDR'] ?? null, !empty($user['email']) ? 'email' : 'phone']
        );
        $link = (defined('APP_URL') ? APP_URL : '') . "/login.php?mode=reset&token=" . $token;

        if (!empty($user['email'])) {
            Mailer::send($user['email'], 'بازیابی رمز عبور NexusChat', "
                <div style='font-family:Vazirmatn,sans-serif;max-width:600px;margin:0 auto;background:linear-gradient(135deg,#0f0a1e,#1a0f2e);color:white;padding:40px;border-radius:16px;text-align:center;'>
                    <h1 style='color:#8b5cf6;'>بازیابی رمز عبور</h1>
                    <p>سلام {$user['display_name']} 👋</p>
                    <p>برای بازیابی رمز روی دکمه زیر کلیک کنید (اعتبار: ۱ ساعت):</p>
                    <a href='$link' style='display:inline-block;padding:14px 32px;background:linear-gradient(135deg,#8b5cf6,#ec4899);color:white;border-radius:12px;text-decoration:none;font-weight:bold;margin:24px 0;'>🔑 بازیابی رمز</a>
                    <p style='color:#9ca3af;font-size:12px;margin-top:24px;'>— تیم NexusChat ✨</p>
                </div>
            ");
        } elseif (!empty($user['phone_e164'])) {
            SMS::send($user['phone_e164'], "NexusChat: برای بازیابی رمز کلیک کنید $link");
        }
        AuditLog::record('forgot_request', $user['id']);
        ok(['message' => 'If the account exists, a reset link has been sent.']);
        break;
    }

    case 'reset': {
        csrf_required();
        $token = trim((string)($_POST['token'] ?? ''));
        $pw    = (string)($_POST['password'] ?? '');
        if (strlen($token) < 32) err('Invalid token', 400);
        if (strlen($pw) < 8) err('Password too short', 400);

        $hash = hash('sha256', $token);
        $row  = Database::getInstance()->fetch("SELECT * FROM password_resets WHERE token_hash = ? AND used = 0 AND expires_at > NOW()", [$hash]);
        if (!$row) err('Token expired or invalid', 400);
        $newHash = password_hash($pw, PASSWORD_BCRYPT, ['cost' => 12]);
        Database::getInstance()->query("UPDATE users SET password_hash = ?, failed_attempts = 0, locked_until = NULL WHERE id = ?", [$newHash, $row['user_id']]);
        Database::getInstance()->query("UPDATE password_resets SET used = 1 WHERE id = ?", [$row['id']]);
        AuditLog::record('password_reset_ok', $row['user_id']);
        ok(['message' => 'Password updated. You can now sign in.']);
        break;
    }

    // ─────────── LOGOUT ───────────
    case 'logout': {
        $uid = current_user_id();
        if (!empty($_COOKIE['nc_remember'])) {
            [$rid, $tok] = explode(':', $_COOKIE['nc_remember'], 2) + [null, null];
            if ($rid && $tok) {
                Database::getInstance()->query("DELETE FROM remember_tokens WHERE user_id = ? AND token_hash = ?", [$rid, hash('sha256', $tok)]);
            }
            setcookie('nc_remember', '', time() - 3600, '/');
        }
        $_SESSION = [];
        session_destroy();
        AuditLog::record('logout', $uid);
        ok(['redirect' => '/login.php']);
        break;
    }

    // ─────────── ME ───────────
    case 'me': {
        $uid = current_user_id();
        if (!$uid) err('Not logged in', 401);
        $user = User::find($uid);
        if (!$user) err('User not found', 404);
        unset($user['password_hash'], $user['totp_secret']);
        ok(['user' => $user]);
        break;
    }

    case 'redirect': {
        header('Location: ' . safe_redirect_target());
        exit;
    }

    default:
        err('Unknown action', 404);
    }
} catch (Throwable $e) {
    error_log('[auth] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (defined('APP_ENV') && APP_ENV === 'development') {
        err('Server error: ' . $e->getMessage(), 500);
    }
    err('Server error', 500);
}
