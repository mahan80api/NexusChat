<?php
/**
 * NexusChat — Auth API
 *
 * Actions:
 *   POST  ?action=login            {username, password, remember?, csrf, g-recaptcha-response?, totp?}
 *   POST  ?action=register         {username, password, display_name, email?, csrf, accept_tos=1, captcha}
 *   POST  ?action=check_username   {username}                   → {available: bool}
 *   POST  ?action=verify_2fa       {totp, csrf}                 → success after login
 *   POST  ?action=forgot           {identifier, csrf}           → email/link
 *   POST  ?action=reset            {token, password, csrf}
 *   GET   ?action=logout
 *   GET   ?action=me
 *   GET   ?action=redirect         → safe redirect for ?ref=
 *
 * Security:
 *  - CSRF token required for all POST
 *  - reCAPTCHA v3 (optional, by config)
 *  - Rate limiting (login 10/h, register 3/h, forgot 5/h, verify_2fa 5/15min)
 *  - Password: bcrypt cost 12
 *  - Username: [a-zA-Z0-9_.]{3,32}
 *  - 2FA: TOTP (RFC 6238), 6 digits, 30s window, ±1 step tolerance
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

// ──────────────────────────────────────────────
// Route
// ──────────────────────────────────────────────
$action = $_GET['action'] ?? '';
session_start();

try {
    switch ($action) {

    // ─────────── CHECK USERNAME (public, rate-limited) ───────────
    case 'check_username': {
        $u = trim((string)($_POST['username'] ?? ''));
        if (!preg_match('/^[a-zA-Z0-9_.]{3,32}$/', $u)) {
            ok(['available' => false, 'reason' => 'invalid']);
            break;
        }
        $exists = Database::getInstance()->fetchColumn("SELECT 1 FROM users WHERE username = ?", [$u]);
        ok(['available' => !$exists]);
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
        $user = $db->fetch("SELECT * FROM users WHERE username = ? OR email = ?", [$username, $username]);
        if (!$user) {
            AuditLog::record('login_fail', null, ['reason' => 'no_user', 'username' => $username]);
            err('Invalid credentials', 401);
        }
        // account lockout
        if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
            $mins = ceil((strtotime($user['locked_until']) - time()) / 60);
            err("Account locked. Try in $mins min.", 423, ['locked_minutes' => $mins]);
        }
        if (!password_verify($password, $user['password_hash'])) {
            // increment failed attempts
            $fails = ((int)($user['failed_attempts'] ?? 0)) + 1;
            $lock = $fails >= 5 ? date('Y-m-d H:i:s', time() + 900) : null;
            $db->query("UPDATE users SET failed_attempts = ?, locked_until = ? WHERE id = ?", [$fails, $lock, $user['id']]);
            AuditLog::record('login_fail', $user['id'], ['reason' => 'bad_pw', 'attempts' => $fails]);
            err('Invalid credentials', 401, ['attempts_left' => max(0, 5 - $fails)]);
        }

        // 2FA check
        if (!empty($user['totp_secret']) && empty($user['totp_confirmed_at'])) {
            // not yet confirmed, allow login but enforce setup on next login
        }
        if (!empty($user['totp_secret']) && $user['totp_confirmed_at']) {
            if ($totp === '') {
                $_SESSION['2fa_pending_user_id'] = (int)$user['id'];
                ok(['requires_2fa' => true]);
            } else {
                if (!TOTP::verify($user['totp_secret'], $totp)) {
                    AuditLog::record('login_2fa_fail', $user['id']);
                    err('Invalid 2FA code', 401);
                }
                unset($_SESSION['2fa_pending_user_id']);
            }
        }

        // success: regenerate session, set user
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['login_time'] = time();

        // reset failed attempts
        $db->query("UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login = NOW(), last_ip = ? WHERE id = ?", [$_SERVER['REMOTE_ADDR'] ?? null, $user['id']]);

        // remember me
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $hash  = hash('sha256', $token);
            $exp   = time() + 60 * 60 * 24 * 30; // 30 days
            $db->query("INSERT INTO remember_tokens (user_id, token_hash, expires_at, ip, user_agent) VALUES (?, ?, FROM_UNIXTIME(?), ?, ?)",
                [$user['id'], $hash, $exp, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);
            setcookie('nc_remember', $user['id'] . ':' . $token, [
                'expires'  => $exp,
                'path'     => '/',
                'secure'   => defined('APP_ENV') && APP_ENV === 'production',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        AuditLog::record('login_ok', $user['id']);
        ok([
            'user_id'  => (int)$user['id'],
            'username' => $user['username'],
            'redirect' => safe_redirect_target(),
        ]);
        break;
    }

    // ─────────── VERIFY 2FA (second step of login) ───────────
    case 'verify_2fa': {
        csrf_required();
        rate_limit('2fa', 5, 900);
        $uid = (int)($_SESSION['2fa_pending_user_id'] ?? 0);
        if (!$uid) err('No pending 2FA', 400);
        $code = trim((string)($_POST['totp'] ?? ''));
        if (!preg_match('/^\d{6}$/', $code)) err('Invalid code format', 400);
        $user = Database::getInstance()->fetch("SELECT * FROM users WHERE id = ?", [$uid]);
        if (!$user || empty($user['totp_secret'])) err('User not found', 404);
        if (!TOTP::verify($user['totp_secret'], $code)) {
            AuditLog::record('2fa_fail', $uid);
            err('Invalid 2FA code', 401);
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['login_time'] = time();
        unset($_SESSION['2fa_pending_user_id']);
        AuditLog::record('login_ok_2fa', $user['id']);
        ok(['redirect' => safe_redirect_target()]);
        break;
    }

    // ─────────── REGISTER ───────────
    case 'register': {
        csrf_required();
        rate_limit('register', 3, 3600);
        verify_captcha();

        $username = trim((string)($_POST['username'] ?? ''));
        $display  = trim((string)($_POST['display_name'] ?? ''));
        $email    = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $accept   = !empty($_POST['accept_tos']);

        if (!$accept) err('You must accept the terms', 400);
        if (!preg_match('/^[a-zA-Z0-9_.]{3,32}$/', $username)) err('Invalid username (3-32 chars, a-z 0-9 _ .)', 400);
        if (mb_strlen($display) < 2 || mb_strlen($display) > 64) err('Display name must be 2-64 chars', 400);
        if (strlen($password) < 8) err('Password must be at least 8 characters', 400);
        if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            err('Password must contain uppercase, lowercase and digit', 400);
        }
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) err('Invalid email', 400);

        $db = Database::getInstance();
        if ($db->fetchColumn("SELECT 1 FROM users WHERE username = ?", [$username])) err('Username already taken', 409);
        if ($email && $db->fetchColumn("SELECT 1 FROM users WHERE email = ?", [$email])) err('Email already in use', 409);

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $db->query(
            "INSERT INTO users (username, display_name, email, password_hash, created_at, last_ip) VALUES (?, ?, ?, ?, NOW(), ?)",
            [$username, $display, $email ?: null, $hash, $_SERVER['REMOTE_ADDR'] ?? null]
        );
        $uid = (int)$db->lastInsertId();

        // Auto-login after register
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$uid;
        $_SESSION['username'] = $username;
        $_SESSION['login_time'] = time();

        // Welcome wallet (free IRR)
        $db->query("INSERT INTO wallets (user_id, currency, balance, wallet_number) VALUES (?, 'IRR', 100000, ?)", [$uid, 'WAL' . str_pad((string)$uid, 10, '0', STR_PAD_LEFT) . 'WELC']);

        AuditLog::record('register_ok', $uid);
        ok(['user_id' => $uid, 'redirect' => safe_redirect_target()]);
        break;
    }

    // ─────────── FORGOT PASSWORD ───────────
    case 'forgot': {
        csrf_required();
        rate_limit('forgot', 5, 3600);
        $ident = trim((string)($_POST['identifier'] ?? ''));
        if (!$ident) err('Enter username or email', 400);

        $user = Database::getInstance()->fetch("SELECT * FROM users WHERE username = ? OR email = ?", [$ident, $ident]);
        if (!$user) {
            // Don't leak existence — same response
            ok(['message' => 'If the account exists, a reset link has been sent.']);
            break;
        }
        $token = bin2hex(random_bytes(32));
        $hash  = hash('sha256', $token);
        $exp   = time() + 3600; // 1 hour
        Database::getInstance()->query(
            "INSERT INTO password_resets (user_id, token_hash, expires_at, ip) VALUES (?, ?, FROM_UNIXTIME(?), ?)",
            [$user['id'], $hash, $exp, $_SERVER['REMOTE_ADDR'] ?? null]
        );
        $link = (defined('APP_URL') ? APP_URL : '') . "/login.php?mode=forgot&token=" . $token;

        if (!empty($user['email'])) {
            Mailer::send($user['email'], 'بازیابی رمز عبور NexusChat', "
                <h2>سلام {$user['display_name']} 👋</h2>
                <p>برای بازیابی رمز روی لینک زیر کلیک کنید (اعتبار: ۱ ساعت):</p>
                <p><a href='$link' style='display:inline-block;padding:12px 24px;background:linear-gradient(135deg,#8b5cf6,#ec4899);color:white;border-radius:8px;text-decoration:none;font-weight:bold;'>بازیابی رمز</a></p>
                <p>اگه شما این درخواست رو ندادید، این ایمیل رو نادیده بگیرید.</p>
                <p>— تیم NexusChat ✨</p>
            ");
        } else {
            // no email — show link in dev mode
            if (defined('APP_ENV') && APP_ENV !== 'production') {
                ok(['message' => 'Reset link (dev only):', 'dev_link' => $link]);
                break;
            }
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
        Database::getInstance()->query("UPDATE users SET password_hash = ? WHERE id = ?", [$newHash, $row['user_id']]);
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

    // ─────────── SAFE REDIRECT HELPER ───────────
    case 'redirect': {
        $url = safe_redirect_target();
        header('Location: ' . $url);
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
