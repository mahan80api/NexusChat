<?php
/**
 * NexusChat - Login / Register page
 */
define('NEXUSCHAT', true);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Database.php';

if (current_user_id()) { header('Location: /index.php'); exit; }

$mode = $_GET['mode'] ?? 'login';
$theme = $_COOKIE['nc_theme'] ?? 'cosmic';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="<?= htmlspecialchars($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> — ورود</title>
    <link rel="icon" type="image/svg+xml" href="/assets/img/logo.svg">
    <link rel="stylesheet" href="/assets/css/themes.css">
    <link rel="stylesheet" href="/assets/css/animations.css">
    <link rel="stylesheet" href="/assets/css/auth.css">
</head>
<body class="cosmic-theme auth-page">

<div class="cosmic-bg">
    <div class="starfield"></div>
    <div class="nebula nebula-1"></div>
    <div class="nebula nebula-2"></div>
    <div class="particles"></div>
</div>

<div class="auth-container">
    <div class="auth-card glass">
        <div class="auth-header">
            <div class="auth-logo">✨</div>
            <h1>NexusChat</h1>
            <p class="auth-subtitle">پیام‌رسان کیهانی</p>
        </div>

        <div class="auth-tabs">
            <button class="auth-tab <?= $mode === 'login' ? 'active' : '' ?>" data-mode="login">ورود</button>
            <button class="auth-tab <?= $mode === 'register' ? 'active' : '' ?>" data-mode="register">ثبت‌نام</button>
        </div>

        <form id="authForm" class="auth-form" data-mode="<?= $mode ?>">
            <div class="form-group" id="nameGroup" style="<?= $mode === 'login' ? 'display:none' : '' ?>">
                <label>نام نمایشی</label>
                <input type="text" name="display_name" placeholder="نام شما" autocomplete="name">
            </div>
            <div class="form-group">
                <label>نام کاربری</label>
                <input type="text" name="username" required placeholder="username" autocomplete="username" minlength="3" maxlength="32" pattern="[a-zA-Z0-9_]+">
            </div>
            <div class="form-group">
                <label>رمز عبور</label>
                <input type="password" name="password" required placeholder="••••••••" autocomplete="current-password" minlength="6">
            </div>
            <button type="submit" class="btn-primary auth-submit">
                <span class="btn-text"><?= $mode === 'login' ? 'ورود' : 'ثبت‌نام' ?></span>
                <span class="btn-loader" style="display:none;">...</span>
            </button>
            <div class="auth-error" id="authError" style="display:none;"></div>
        </form>

        <div class="auth-footer">
            <small>با ورود، <a href="#">قوانین</a> را می‌پذیرید.</small>
        </div>
    </div>
</div>

<script>
const form = document.getElementById('authForm');
const err = document.getElementById('authError');
const submitBtn = form.querySelector('.auth-submit');
const btnText = submitBtn.querySelector('.btn-text');
const btnLoader = submitBtn.querySelector('.btn-loader');

document.querySelectorAll('.auth-tab').forEach(t => t.addEventListener('click', () => {
    const mode = t.dataset.mode;
    window.location.href = '/login.php?mode=' + mode;
}));

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    err.style.display = 'none';
    btnText.style.display = 'none';
    btnLoader.style.display = 'inline';
    submitBtn.disabled = true;

    const fd = new FormData(form);
    const mode = form.dataset.mode;
    fd.append('action', mode);

    try {
        const r = await fetch('/api/auth.php', { method: 'POST', body: fd, credentials: 'same-origin' });
        const data = await r.json();
        if (data.success) {
            btnText.textContent = '✅ موفق!';
            setTimeout(() => window.location.href = '/index.php', 500);
        } else {
            err.textContent = data.message || 'خطا';
            err.style.display = 'block';
        }
    } catch (ex) {
        err.textContent = 'خطای شبکه';
        err.style.display = 'block';
    } finally {
        btnText.style.display = 'inline';
        btnLoader.style.display = 'none';
        submitBtn.disabled = false;
    }
});

// Strength meter (register only)
const pw = form.querySelector('input[name="password"]');
if (form.dataset.mode === 'register') {
    const meter = document.createElement('div');
    meter.className = 'pw-strength';
    meter.innerHTML = '<div class="pw-strength-bar"></div><div class="pw-strength-bar"></div><div class="pw-strength-bar"></div><div class="pw-strength-bar"></div>';
    pw.parentElement.appendChild(meter);
    pw.addEventListener('input', () => {
        const v = pw.value;
        let s = 0;
        if (v.length >= 8) s++;
        if (/[A-Z]/.test(v)) s++;
        if (/[0-9]/.test(v)) s++;
        if (/[^A-Za-z0-9]/.test(v)) s++;
        meter.querySelectorAll('.pw-strength-bar').forEach((b, i) => b.classList.toggle('on', i < s));
    });
}
</script>
</body>
</html>
