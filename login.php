<?php
/**
 * NexusChat — Login / Register / Recover page
 *
 * Features:
 *  - 3 modes: login, register, forgot
 *  - 2FA (TOTP) support via ?2fa=1
 *  - reCAPTCHA v3 (optional)
 *  - Demo account quick-login
 *  - Theme persistence (cookie)
 *  - Strong password meter (client-side)
 *  - Username availability check (debounced)
 *  - Email format validation
 *  - "Remember me" with extended session cookie
 *  - Multi-language (fa/en) via ?lang
 *  - 2-step redirect after success
 *  - Error/success toasts (in-page)
 *  - No-JS fallback (form posts to api/auth.php)
 *  - Session regeneration on login
 *  - Brute-force lockout (handled in api/auth.php)
 *  - CSRF token embedded
 *  - Open Graph meta for sharing
 *  - PWA meta tags
 *  - RTL/LTR auto based on lang
 *  - Keyboard accessibility (tab order, ARIA)
 *  - Mobile responsive
 */
declare(strict_types=1);

define('NEXUSCHAT', true);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/User.php';

// Already logged in → go to chat
if (current_user_id()) {
    header('Location: ' . (defined('APP_URL') ? APP_URL : '') . '/index.php');
    exit;
}

// ──────────────────────────────────────────────
// Input
// ──────────────────────────────────────────────
$mode    = in_array($_GET['mode'] ?? '', ['login', 'register', 'forgot', '2fa']) ? ($_GET['mode']) : 'login';
$lang    = in_array($_GET['lang'] ?? '', ['fa', 'en']) ? ($_GET['lang']) : 'fa';
$theme   = preg_replace('/[^a-z0-9_-]/i', '', $_COOKIE['nc_theme'] ?? 'cosmic');
$ref     = $_GET['ref'] ?? '/index.php'; // post-login redirect
$ref     = preg_match('#^/[a-z0-9_/.\-]+\.php(\?[a-z0-9_=&%-]+)?$#i', $ref) ? $ref : '/index.php';
$needs2fa = !empty($_GET['2fa']);
$pending2fa = $_SESSION['2fa_pending_user_id'] ?? null;

// CSRF token
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf'];

// Captcha site key (optional, from config)
$recaptchaKey = defined('RECAPTCHA_SITE_KEY') ? RECAPTCHA_SITE_KEY : '';

// Open Graph
$appName = defined('APP_NAME') ? APP_NAME : 'NexusChat';
$appUrl  = defined('APP_URL')  ? APP_URL  : '';
$dir     = $lang === 'fa' ? 'rtl' : 'ltr';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>" dir="<?= $dir ?>" data-theme="<?= htmlspecialchars($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#8b5cf6">
    <meta name="color-scheme" content="dark light">

    <title><?= htmlspecialchars($appName) ?> — <?= $mode === 'register' ? 'ثبت‌نام' : ($mode === 'forgot' ? 'بازیابی رمز' : 'ورود') ?></title>

    <!-- SEO / OG -->
    <meta name="description" content="<?= htmlspecialchars($appName) ?> — پیام‌رسان کیهانی با کیف پول چند ارزی، تماس، ربات و کانال">
    <meta property="og:title" content="<?= htmlspecialchars($appName) ?> — پیام‌رسان کیهانی">
    <meta property="og:description" content="تجربه‌ای فراتر از یک چت ساده. پیام، تماس، کیف پول، ربات و کانال.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars($appUrl) ?>/login.php">
    <meta property="og:image" content="<?= htmlspecialchars($appUrl) ?>/assets/img/og-image.png">
    <meta name="twitter:card" content="summary_large_image">

    <!-- PWA -->
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" type="image/svg+xml" href="/assets/img/logo.svg">
    <link rel="apple-touch-icon" href="/assets/img/logo-192.png">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- CSS -->
    <link rel="stylesheet" href="/assets/css/themes.css">
    <link rel="stylesheet" href="/assets/css/animations.css">
    <link rel="stylesheet" href="/assets/css/auth.css">
    <link rel="stylesheet" href="/assets/css/final-polish.css">
    <link rel="stylesheet" href="/assets/css/login.css">

    <?php if ($recaptchaKey): ?>
    <script src="https://www.google.com/recaptcha/api.js?render=<?= htmlspecialchars($recaptchaKey) ?>" async defer></script>
    <?php endif; ?>
</head>
<body class="cosmic-theme auth-page" data-mode="<?= htmlspecialchars($mode) ?>" data-lang="<?= htmlspecialchars($lang) ?>">

<!-- Cosmic background -->
<div class="cosmic-bg" aria-hidden="true">
    <div class="starfield"></div>
    <div class="nebula nebula-1"></div>
    <div class="nebula nebula-2"></div>
    <div class="nebula nebula-3"></div>
    <div class="particles"></div>
    <div class="shooting-star"></div>
    <div class="shooting-star"></div>
    <div class="shooting-star"></div>
</div>

<!-- Toast container -->
<div id="toastContainer" class="toast-container" role="status" aria-live="polite"></div>

<div class="auth-container">
    <!-- Language + theme switcher -->
    <div class="auth-topbar">
        <div class="lang-switcher" role="group" aria-label="Language">
            <a href="?<?= http_build_query(array_merge($_GET, ['lang' => 'fa'])) ?>" class="lang-btn <?= $lang === 'fa' ? 'active' : '' ?>" aria-label="فارسی">فا</a>
            <a href="?<?= http_build_query(array_merge($_GET, ['lang' => 'en'])) ?>" class="lang-btn <?= $lang === 'en' ? 'active' : '' ?>" aria-label="English">EN</a>
        </div>
        <div class="theme-quick" role="group" aria-label="Theme">
            <button class="theme-dot t-cosmic" data-theme="cosmic" aria-label="Cosmic"></button>
            <button class="theme-dot t-ocean"  data-theme="ocean"  aria-label="Ocean"></button>
            <button class="theme-dot t-forest" data-theme="forest" aria-label="Forest"></button>
            <button class="theme-dot t-sunset" data-theme="sunset" aria-label="Sunset"></button>
            <button class="theme-dot t-aurora" data-theme="aurora" aria-label="Aurora"></button>
        </div>
    </div>

    <div class="auth-card glass" role="main">
        <!-- Logo -->
        <div class="auth-header">
            <div class="auth-logo" aria-hidden="true">✨</div>
            <h1 class="auth-title"><?= htmlspecialchars($appName) ?></h1>
            <p class="auth-subtitle"><?= $lang === 'fa' ? 'پیام‌رسان کیهانی' : 'Cosmic Messenger' ?></p>
        </div>

        <!-- Tabs (only for login/register) -->
        <?php if ($mode !== 'forgot'): ?>
        <div class="auth-tabs" role="tablist">
            <button class="auth-tab <?= $mode === 'login' ? 'active' : '' ?>" data-mode="login"  role="tab" aria-selected="<?= $mode === 'login'  ? 'true' : 'false' ?>"><?= $lang === 'fa' ? 'ورود' : 'Sign in' ?></button>
            <button class="auth-tab <?= $mode === 'register' ? 'active' : '' ?>" data-mode="register" role="tab" aria-selected="<?= $mode === 'register' ? 'true' : 'false' ?>"><?= $lang === 'fa' ? 'ثبت‌نام' : 'Sign up' ?></button>
        </div>
        <?php else: ?>
        <h2 class="auth-section-title"><?= $lang === 'fa' ? 'بازیابی رمز عبور' : 'Reset password' ?></h2>
        <?php endif; ?>

        <!-- Messages (set by JS via ?msg=... or session flash) -->
        <?php
        $flash = $_SESSION['flash'] ?? null;
        if ($flash): unset($_SESSION['flash']); ?>
        <div class="auth-flash auth-flash-<?= htmlspecialchars($flash['type'] ?? 'info') ?>" role="alert">
            <?= htmlspecialchars($flash['message'] ?? '') ?>
        </div>
        <?php endif; ?>

        <!-- Main form -->
        <form id="authForm"
              class="auth-form"
              data-mode="<?= htmlspecialchars($mode) ?>"
              data-csrf="<?= htmlspecialchars($csrf) ?>"
              data-ref="<?= htmlspecialchars($ref) ?>"
              autocomplete="on"
              novalidate>

            <?php if ($mode === 'register'): ?>
            <!-- Display name -->
            <div class="form-group">
                <label for="display_name"><?= $lang === 'fa' ? 'نام نمایشی' : 'Display name' ?></label>
                <input type="text" id="display_name" name="display_name" required
                       minlength="2" maxlength="64"
                       placeholder="<?= $lang === 'fa' ? 'مثلاً ماهان' : 'e.g. Mahan' ?>"
                       autocomplete="name">
                <span class="form-hint"><?= $lang === 'fa' ? 'این نام به دوستانتان نمایش داده می‌شود' : 'Visible to your contacts' ?></span>
            </div>
            <?php endif; ?>

            <!-- Username -->
            <div class="form-group">
                <label for="username"><?= $lang === 'fa' ? 'نام کاربری' : 'Username' ?></label>
                <div class="input-wrap">
                    <span class="input-icon" aria-hidden="true">@</span>
                    <input type="text" id="username" name="username" required
                           minlength="3" maxlength="32" pattern="[a-zA-Z0-9_.]+"
                           placeholder="username"
                           autocomplete="username"
                           spellcheck="false"
                           autocapitalize="off">
                    <span class="input-status" id="usernameStatus" aria-live="polite"></span>
                </div>
                <span class="form-hint"><?= $lang === 'fa' ? 'فقط حروف انگلیسی، عدد، _ و . — قابل تغییر نیست' : 'Letters, digits, _ and . only — permanent' ?></span>
            </div>

            <?php if ($mode === 'register'): ?>
            <!-- Email (optional) -->
            <div class="form-group">
                <label for="email"><?= $lang === 'fa' ? 'ایمیل (اختیاری)' : 'Email (optional)' ?></label>
                <div class="input-wrap">
                    <span class="input-icon" aria-hidden="true">✉</span>
                    <input type="email" id="email" name="email"
                           placeholder="<?= $lang === 'fa' ? 'name@example.com' : 'name@example.com' ?>"
                           autocomplete="email">
                </div>
                <span class="form-hint"><?= $lang === 'fa' ? 'برای بازیابی رمز و اطلاع‌رسانی' : 'For password recovery and notifications' ?></span>
            </div>
            <?php endif; ?>

            <!-- Password -->
            <?php if ($mode !== 'forgot'): ?>
            <div class="form-group">
                <label for="password"><?= $lang === 'fa' ? 'رمز عبور' : 'Password' ?></label>
                <div class="input-wrap">
                    <span class="input-icon" aria-hidden="true">🔒</span>
                    <input type="password" id="password" name="password" required
                           minlength="<?= $mode === 'register' ? '8' : '6' ?>"
                           maxlength="128"
                           placeholder="••••••••"
                           autocomplete="<?= $mode === 'register' ? 'new-password' : 'current-password' ?>">
                    <button type="button" class="input-action" id="togglePw" aria-label="نمایش رمز">👁</button>
                </div>
                <?php if ($mode === 'register'): ?>
                <div class="pw-strength" id="pwStrength" aria-label="قدرت رمز">
                    <div class="pw-strength-bar"></div>
                    <div class="pw-strength-bar"></div>
                    <div class="pw-strength-bar"></div>
                    <div class="pw-strength-bar"></div>
                    <span class="pw-strength-label" id="pwStrengthLabel"></span>
                </div>
                <ul class="pw-rules" id="pwRules">
                    <li data-rule="len"><?= $lang === 'fa' ? 'حداقل ۸ کاراکتر' : 'At least 8 characters' ?></li>
                    <li data-rule="case"><?= $lang === 'fa' ? 'حرف بزرگ و کوچک' : 'Uppercase & lowercase' ?></li>
                    <li data-rule="num"><?= $lang === 'fa' ? 'شامل عدد' : 'Contains a number' ?></li>
                    <li data-rule="sym"><?= $lang === 'fa' ? 'شامل نماد' : 'Contains a symbol' ?></li>
                </ul>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- 2FA code (only if needed) -->
            <?php if ($mode === '2fa' || $needs2fa): ?>
            <div class="form-group">
                <label for="totp"><?= $lang === 'fa' ? 'کد تأیید دو مرحله‌ای' : '2FA code' ?></label>
                <div class="input-wrap">
                    <span class="input-icon" aria-hidden="true">🛡</span>
                    <input type="text" id="totp" name="totp" required
                           minlength="6" maxlength="6" inputmode="numeric"
                           pattern="[0-9]{6}"
                           placeholder="123456"
                           autocomplete="one-time-code"
                           autofocus>
                </div>
                <span class="form-hint"><?= $lang === 'fa' ? 'کد ۶ رقمی از اپلیکیشن احراز هویت' : '6-digit code from your authenticator app' ?></span>
            </div>
            <?php endif; ?>

            <!-- Remember me + Forgot (login only) -->
            <?php if ($mode === 'login'): ?>
            <div class="form-row">
                <label class="checkbox">
                    <input type="checkbox" name="remember" value="1">
                    <span class="checkbox-mark"></span>
                    <span><?= $lang === 'fa' ? 'مرا به خاطر بسپار' : 'Remember me' ?></span>
                </label>
                <a href="?mode=forgot&lang=<?= urlencode($lang) ?>" class="link"><?= $lang === 'fa' ? 'فراموشی رمز؟' : 'Forgot password?' ?></a>
            </div>
            <?php endif; ?>

            <!-- Terms (register only) -->
            <?php if ($mode === 'register'): ?>
            <label class="checkbox checkbox-tos">
                <input type="checkbox" name="accept_tos" value="1" required>
                <span class="checkbox-mark"></span>
                <span><?= $lang === 'fa' ? 'می‌پذیرم' : 'I accept the' ?>
                    <a href="/terms" target="_blank"><?= $lang === 'fa' ? 'قوانین استفاده' : 'Terms of Service' ?></a>
                    <?= $lang === 'fa' ? 'و' : 'and' ?>
                    <a href="/privacy" target="_blank"><?= $lang === 'fa' ? 'حریم خصوصی' : 'Privacy Policy' ?></a>
                </span>
            </label>
            <?php endif; ?>

            <!-- reCAPTCHA v3 (optional) -->
            <?php if ($recaptchaKey): ?>
            <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">
            <?php endif; ?>

            <!-- CSRF -->
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

            <!-- Submit -->
            <button type="submit" class="btn-primary auth-submit" id="submitBtn">
                <span class="btn-text">
                    <?php
                    echo match(true) {
                        $mode === 'register'  => ($lang === 'fa' ? '🚀 ساخت حساب' : '🚀 Create account'),
                        $mode === 'forgot'    => ($lang === 'fa' ? '📧 ارسال لینک بازیابی' : '📧 Send reset link'),
                        $mode === '2fa'       => ($lang === 'fa' ? '🛡 تأیید' : '🛡 Verify'),
                        default               => ($lang === 'fa' ? '✨ ورود به ' . $appName : '✨ Sign in to ' . $appName),
                    };
                    ?>
                </span>
                <span class="btn-loader" aria-hidden="true">
                    <span class="spinner"></span>
                </span>
            </button>

            <!-- Error message -->
            <div class="auth-error" id="authError" role="alert" aria-live="assertive"></div>
        </form>

        <!-- Social / SSO (placeholder, structure ready) -->
        <?php if ($mode !== 'forgot'): ?>
        <div class="auth-divider"><span><?= $lang === 'fa' ? 'یا' : 'or' ?></span></div>
        <div class="sso-row">
            <button type="button" class="sso-btn" data-sso="google"  aria-label="Google">
                <svg viewBox="0 0 24 24" width="18" height="18"><path fill="#EA4335" d="M12 11v3.2h4.5c-.2 1.4-1.6 4-4.5 4-2.7 0-4.9-2.2-4.9-5s2.2-5 4.9-5c1.5 0 2.6.6 3.2 1.2l2.2-2.1C15.9 5.7 14.1 5 12 5 8.1 5 5 8.1 5 12s3.1 7 7 7c4 0 6.7-2.8 6.7-6.8 0-.5 0-.8-.1-1.2H12z"/></svg>
            </button>
            <button type="button" class="sso-btn" data-sso="github" aria-label="GitHub">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M12 2C6.5 2 2 6.6 2 12.2c0 4.5 2.9 8.3 6.8 9.6.5.1.7-.2.7-.5v-1.7c-2.8.6-3.4-1.4-3.4-1.4-.4-1.2-1.1-1.5-1.1-1.5-.9-.6.1-.6.1-.6 1 .1 1.5 1.1 1.5 1.1.9 1.6 2.4 1.1 3 .9.1-.7.4-1.1.6-1.4-2.2-.3-4.6-1.1-4.6-5 0-1.1.4-2 1-2.7-.1-.3-.4-1.3.1-2.7 0 0 .8-.3 2.8 1 .8-.2 1.7-.3 2.5-.3.9 0 1.7.1 2.5.3 1.9-1.3 2.8-1 2.8-1 .6 1.4.2 2.4.1 2.7.7.7 1 1.7 1 2.7 0 3.9-2.3 4.7-4.6 5 .4.3.7.9.7 1.8v2.7c0 .3.2.6.7.5C19.1 20.5 22 16.7 22 12.2 22 6.6 17.5 2 12 2z"/></svg>
            </button>
            <button type="button" class="sso-btn" data-sso="telegram" aria-label="Telegram">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M9.5 15.5l-.4 4c.6 0 .8-.3 1.1-.5l2.7-2.5 5.6 4.1c1 .5 1.7.3 2-.9L22 5.6c.3-1.4-.5-2-1.5-1.6L2.4 10.2c-1.3.5-1.3 1.3-.2 1.6l4.6 1.4L17.5 7c.6-.4 1.1-.2.7.2L9.5 15.5z"/></svg>
            </button>
        </div>
        <?php endif; ?>

        <!-- Demo accounts (only in dev) -->
        <?php if (defined('APP_ENV') && APP_ENV !== 'production'): ?>
        <div class="auth-demo">
            <details>
                <summary>🧪 <?= $lang === 'fa' ? 'حساب‌های دمو (فقط محیط توسعه)' : 'Demo accounts (dev only)' ?></summary>
                <div class="demo-grid">
                    <button type="button" class="demo-account" data-user="mahan" data-pw="password">
                        <strong>mahan</strong><span>ادمین</span>
                    </button>
                    <button type="button" class="demo-account" data-user="sara" data-pw="password">
                        <strong>sara</strong><span>کاربر</span>
                    </button>
                    <button type="button" class="demo-account" data-user="ali" data-pw="password">
                        <strong>ali</strong><span>کاربر</span>
                    </button>
                    <button type="button" class="demo-account" data-user="bot_support" data-pw="password">
                        <strong>bot_support</strong><span>ربات</span>
                    </button>
                </div>
            </details>
        </div>
        <?php endif; ?>

        <div class="auth-footer">
            <small>
                <?= $lang === 'fa'
                    ? 'ورود شما به منزله پذیرش ' . '<a href="/terms">قوانین</a> و <a href="/privacy">حریم خصوصی</a> است.'
                    : 'By signing in you accept our <a href="/terms">Terms</a> and <a href="/privacy">Privacy Policy</a>.' ?>
            </small>
        </div>
    </div>

    <!-- Right-side marketing panel (visible on wide screens) -->
    <aside class="auth-side" aria-hidden="true">
        <div class="auth-side-content">
            <h2><?= $lang === 'fa' ? 'پیام‌رسان نسل جدید' : 'Next-gen messenger' ?></h2>
            <ul class="auth-features">
                <li><span>💬</span> <?= $lang === 'fa' ? 'پیام‌رسانی فوری با رمزگذاری' : 'Instant encrypted messaging' ?></li>
                <li><span>📞</span> <?= $lang === 'fa' ? 'تماس صوتی و تصویری' : 'HD voice & video calls' ?></li>
                <li><span>💰</span> <?= $lang === 'fa' ? 'کیف پول ۷ ارزی' : '7-currency wallet' ?></li>
                <li><span>🤖</span> <?= $lang === 'fa' ? 'ربات‌های هوشمند' : 'Smart bots' ?></li>
                <li><span>📢</span> <?= $lang === 'fa' ? 'کانال‌های عمومی' : 'Public channels' ?></li>
            </ul>
            <div class="auth-stats">
                <div><strong id="statUsers">—</strong><span><?= $lang === 'fa' ? 'کاربر' : 'Users' ?></span></div>
                <div><strong id="statChats">—</strong><span><?= $lang === 'fa' ? 'چت' : 'Chats' ?></span></div>
                <div><strong id="statMessages">—</strong><span><?= $lang === 'fa' ? 'پیام' : 'Messages' ?></span></div>
            </div>
        </div>
    </aside>
</div>

<script>
/* ============================================================
   NexusChat — Auth page client
   - Mode switcher (login ↔ register)
   - Form submit with fetch
   - Password strength meter
   - Username availability check (debounced)
   - Show/hide password
   - Theme switcher
   - Demo account quick-fill
   - Toast notifications
   ============================================================ */
(function () {
    'use strict';

    const $  = (s, c = document) => c.querySelector(s);
    const $$ = (s, c = document) => Array.from(c.querySelectorAll(s));
    const form    = $('#authForm');
    const errBox  = $('#authError');
    const submit  = $('#submitBtn');
    const btnText = submit.querySelector('.btn-text');
    const btnLoad = submit.querySelector('.btn-loader');
    const mode    = form.dataset.mode;
    const ref     = form.dataset.ref || '/index.php';
    const csrf    = form.dataset.csrf;
    const lang    = document.body.dataset.lang || 'fa';

    /* ---------- Toast ---------- */
    function toast(message, type = 'info', duration = 3500) {
        const c = $('#toastContainer');
        const t = document.createElement('div');
        t.className = `toast toast-${type}`;
        t.setAttribute('role', type === 'error' ? 'alert' : 'status');
        t.innerHTML = `<span class="toast-icon">${
            {success: '✅', error: '❌', warn: '⚠️', info: 'ℹ️'}[type] || 'ℹ️'
        }</span><span class="toast-text"></span>`;
        t.querySelector('.toast-text').textContent = message;
        c.appendChild(t);
        requestAnimationFrame(() => t.classList.add('show'));
        setTimeout(() => {
            t.classList.remove('show');
            setTimeout(() => t.remove(), 300);
        }, duration);
    }
    window.toast = toast;

    /* ---------- Mode switcher (no full reload) ---------- */
    $$('.auth-tab').forEach(t => t.addEventListener('click', () => {
        const newMode = t.dataset.mode;
        const url = new URL(location.href);
        url.searchParams.set('mode', newMode);
        location.href = url.toString();
    }));

    /* ---------- Theme switcher (cookie + instant apply) ---------- */
    $$('.theme-dot').forEach(b => b.addEventListener('click', () => {
        const t = b.dataset.theme;
        document.documentElement.dataset.theme = t;
        document.body.dataset.theme = t;
        document.cookie = `nc_theme=${t};path=/;max-age=${60*60*24*365};samesite=lax`;
        $$('.theme-dot').forEach(x => x.classList.toggle('active', x === b));
    }));
    // Mark active theme
    const activeT = document.documentElement.dataset.theme || 'cosmic';
    $$(`.theme-dot[data-theme="${activeT}"]`).forEach(x => x.classList.add('active'));

    /* ---------- Show/hide password ---------- */
    const togglePw = $('#togglePw');
    if (togglePw) {
        togglePw.addEventListener('click', () => {
            const pw = $('#password');
            const isPw = pw.type === 'password';
            pw.type = isPw ? 'text' : 'password';
            togglePw.textContent = isPw ? '🙈' : '👁';
            togglePw.setAttribute('aria-label', isPw ? 'پنهان کردن رمز' : 'نمایش رمز');
        });
    }

    /* ---------- Username availability (debounced) ---------- */
    const uIn  = $('#username');
    const uSt  = $('#usernameStatus');
    let uTimer = null;
    if (uIn && mode === 'register') {
        uIn.addEventListener('input', () => {
            clearTimeout(uTimer);
            const v = uIn.value.trim();
            uSt.textContent = '';
            uSt.className = 'input-status';
            if (!/^[a-zA-Z0-9_.]{3,32}$/.test(v)) {
                if (v.length >= 3) {
                    uSt.textContent = '⛔';
                    uSt.className = 'input-status error';
                }
                return;
            }
            uSt.textContent = '⏳';
            uSt.className = 'input-status loading';
            uTimer = setTimeout(async () => {
                try {
                    const fd = new FormData();
                    fd.append('action', 'check_username');
                    fd.append('username', v);
                    const r = await fetch('/api/auth.php', { method: 'POST', body: fd, credentials: 'same-origin' });
                    const d = await r.json();
                    if (d.available) {
                        uSt.textContent = '✅';
                        uSt.className = 'input-status ok';
                    } else {
                        uSt.textContent = '❌';
                        uSt.className = 'input-status error';
                    }
                } catch (e) {
                    uSt.textContent = '';
                }
            }, 400);
        });
    }

    /* ---------- Password strength meter (register only) ---------- */
    const pwIn = $('#password');
    const pwBar = $('#pwStrength');
    const pwLab = $('#pwStrengthLabel');
    const pwRules = $$('#pwRules li');
    if (pwIn && mode === 'register') {
        const evalPw = (v) => {
            let s = 0;
            const rules = {
                len:  v.length >= 8,
                case: /[a-z]/.test(v) && /[A-Z]/.test(v),
                num:  /[0-9]/.test(v),
                sym:  /[^A-Za-z0-9]/.test(v)
            };
            Object.entries(rules).forEach(([k, ok]) => {
                const el = pwRules.find(li => li.dataset.rule === k);
                if (el) el.classList.toggle('ok', ok);
                if (ok) s++;
            });
            if (v.length >= 12) s++;
            return Math.min(s, 4);
        };
        pwIn.addEventListener('input', () => {
            const s = evalPw(pwIn.value);
            if (pwBar) {
                const bars = $$('.pw-strength-bar', pwBar);
                bars.forEach((b, i) => b.classList.toggle('on', i < s));
                pwBar.dataset.level = s;
            }
            const labels = ['', 'ضعیف', 'متوسط', 'خوب', 'قوی'];
            if (pwLab) pwLab.textContent = pwIn.value ? labels[s] : '';
        });
    }

    /* ---------- Demo account quick-fill ---------- */
    $$('.demo-account').forEach(b => b.addEventListener('click', () => {
        $('#username').value = b.dataset.user;
        $('#password').value = b.dataset.pw;
        toast(`✅ پر شد: ${b.dataset.user}`, 'success');
    }));

    /* ---------- SSO placeholders ---------- */
    $$('.sso-btn').forEach(b => b.addEventListener('click', () => {
        toast(`${b.dataset.sso} به‌زودی`, 'info');
    }));

    /* ---------- Submit ---------- */
    const setLoading = (on) => {
        submit.disabled = on;
        btnText.style.opacity = on ? '0' : '1';
        btnLoad.style.display = on ? 'inline-flex' : 'none';
    };
    const showError = (msg) => {
        errBox.textContent = msg;
        errBox.classList.add('show');
        errBox.style.display = 'block';
        submit.classList.add('shake');
        setTimeout(() => submit.classList.remove('shake'), 500);
    };
    const hideError = () => {
        errBox.textContent = '';
        errBox.classList.remove('show');
        errBox.style.display = 'none';
    };

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideError();

        // client-side validation
        if (mode === 'register') {
            const tos = form.querySelector('input[name="accept_tos"]');
            if (tos && !tos.checked) {
                showError(lang === 'fa' ? 'لطفاً قوانین را بپذیرید' : 'Please accept the terms');
                return;
            }
            if (pwIn && pwIn.value.length < 8) {
                showError(lang === 'fa' ? 'رمز عبور حداقل ۸ کاراکتر باشد' : 'Password must be at least 8 characters');
                pwIn.focus();
                return;
            }
            if (uSt && uSt.classList.contains('error')) {
                showError(lang === 'fa' ? 'نام کاربری نامعتبر است' : 'Invalid username');
                uIn.focus();
                return;
            }
        }

        setLoading(true);

        // reCAPTCHA v3 (if loaded)
        let captcha = '';
        if (window.grecaptcha) {
            try {
                captcha = await new Promise(res => {
                    grecaptcha.ready(() => {
                        const key = document.querySelector('script[src*="recaptcha/api"]').src.match(/render=([^&]+)/)[1];
                        grecaptcha.execute(key, { action: mode }).then(res);
                    });
                });
            } catch (_) {}
        }
        if (captcha) form.querySelector('#g-recaptcha-response').value = captcha;

        try {
            const fd = new FormData(form);
            fd.set('action', mode);
            const r = await fetch('/api/auth.php', { method: 'POST', body: fd, credentials: 'same-origin' });
            const data = await r.json();
            if (data.success) {
                // 2FA required?
                if (data.requires_2fa) {
                    location.href = '?mode=2fa&2fa=1&ref=' + encodeURIComponent(ref);
                    return;
                }
                btnText.textContent = '✅';
                toast(lang === 'fa' ? 'خوش آمدید!' : 'Welcome!', 'success', 1500);
                // Smooth redirect with brand splash
                submit.classList.add('success');
                setTimeout(() => { location.href = data.redirect || ref; }, 600);
            } else {
                showError(data.message || (lang === 'fa' ? 'خطا' : 'Error'));
                toast(data.message || 'Error', 'error');
                setLoading(false);
            }
        } catch (ex) {
            showError(lang === 'fa' ? 'خطای شبکه — دوباره تلاش کنید' : 'Network error — try again');
            toast(lang === 'fa' ? 'خطای شبکه' : 'Network error', 'error');
            setLoading(false);
        }
    });

    /* ---------- Public stats (decorative) ---------- */
    if ($('#statUsers')) {
        fetch('/api/stats.php?action=public', { credentials: 'omit' })
            .then(r => r.ok ? r.json() : null)
            .then(d => {
                if (!d) return;
                const fmt = n => n >= 1000 ? (n/1000).toFixed(1) + 'K' : String(n);
                if (d.users)     $('#statUsers').textContent    = fmt(d.users);
                if (d.chats)     $('#statChats').textContent    = fmt(d.chats);
                if (d.messages)  $('#statMessages').textContent = fmt(d.messages);
            })
            .catch(() => {});
    }

    /* ---------- Enter to submit (anywhere) ---------- */
    form.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
            e.preventDefault();
            form.requestSubmit();
        }
    });

    /* ---------- Auto-focus first empty input ---------- */
    setTimeout(() => {
        const empty = $$('input[required]', form).find(i => !i.value);
        if (empty) empty.focus();
    }, 200);

    /* ---------- Show flash from URL ?msg= ---------- */
    const urlMsg = new URL(location.href).searchParams.get('msg');
    if (urlMsg) {
        const [type, ...rest] = urlMsg.split(':');
        toast(decodeURIComponent(rest.join(':')), type || 'info');
    }
})();
</script>
</body>
</html>
