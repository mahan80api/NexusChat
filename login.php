<?php
/**
 * NexusChat — login / register page
 */
require_once __DIR__ . '/config/config.php';

if (current_user_id()) {
    header('Location: /index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود — NexusChat</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/themes.css">
    <link rel="stylesheet" href="/assets/css/animations.css">
    <link rel="stylesheet" href="/assets/css/auth.css">
    <script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
</head>
<body class="auth-body" data-theme="cosmic">

    <div class="cosmos-bg" aria-hidden="true">
        <div class="stars"></div>
        <div class="nebula"></div>
    </div>

    <div class="auth-shell">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo">✨</div>
                <h1>NexusChat</h1>
                <p>پیام‌رسان کیهانی</p>
            </div>

            <div class="auth-tabs">
                <button class="auth-tab active" data-tab="login">ورود</button>
                <button class="auth-tab" data-tab="register">ثبت‌نام</button>
            </div>

            <!-- Login form -->
            <form class="auth-form active" id="loginForm">
                <div class="form-group">
                    <label>نام کاربری، ایمیل یا شماره</label>
                    <input type="text" name="identifier" required autocomplete="username">
                </div>
                <div class="form-group">
                    <label>رمز عبور</label>
                    <input type="password" name="password" required autocomplete="current-password">
                </div>
                <button type="submit" class="auth-btn">🚀 ورود</button>
                <div class="auth-error" id="loginError"></div>
            </form>

            <!-- Register form -->
            <form class="auth-form" id="registerForm">
                <div class="form-group">
                    <label>نام کاربری</label>
                    <input type="text" name="username" required minlength="3" pattern="[a-zA-Z0-9_]+">
                </div>
                <div class="form-group">
                    <label>نام نمایشی (اختیاری)</label>
                    <input type="text" name="display_name">
                </div>
                <div class="form-group">
                    <label>ایمیل (اختیاری)</label>
                    <input type="email" name="email">
                </div>
                <div class="form-group">
                    <label>شماره تلفن (اختیاری)</label>
                    <input type="tel" name="phone" pattern="[0-9+]+">
                </div>
                <div class="form-group">
                    <label>رمز عبور (حداقل ۶ کاراکتر)</label>
                    <input type="password" name="password" required minlength="6" autocomplete="new-password">
                </div>
                <button type="submit" class="auth-btn">✨ ساخت حساب</button>
                <div class="auth-error" id="registerError"></div>
            </form>
        </div>
    </div>

    <div class="toast-root" id="toastRoot"></div>

    <script>
    // Minimal app for auth page
    const App = {
        toast(msg, type = 'info') {
            const el = document.createElement('div');
            el.className = 'toast toast-' + type;
            el.textContent = msg;
            document.getElementById('toastRoot').appendChild(el);
            setTimeout(() => el.classList.add('show'), 10);
            setTimeout(() => { el.classList.remove('show'); setTimeout(() => el.remove(), 300); }, 3500);
        },
        async api(action, body = null) {
            const fd = body instanceof FormData ? body : (body ? new URLSearchParams(body) : null);
            const url = '/api/auth.php?action=' + encodeURIComponent(action);
            const res = await fetch(url, {
                method: fd ? 'POST' : 'GET',
                credentials: 'same-origin',
                body: fd,
            });
            return res.json();
        }
    };

    document.querySelectorAll('.auth-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.auth-form').forEach(f => f.classList.remove('active'));
            tab.classList.add('active');
            document.getElementById(tab.dataset.tab + 'Form').classList.add('active');
        });
    });

    const errorMap = {
        'username_taken': 'این نام کاربری قبلاً گرفته شده',
        'email_taken': 'این ایمیل قبلاً ثبت شده',
        'phone_taken': 'این شماره قبلاً ثبت شده',
        'invalid_credentials': 'نام کاربری یا رمز اشتباه است',
        'username_too_short': 'نام کاربری باید حداقل ۳ کاراکتر باشد',
        'password_too_short': 'رمز عبور باید حداقل ۶ کاراکتر باشد',
        'missing_fields': 'همه فیلدها را پر کنید',
        'rate_limited': 'تعداد درخواست‌ها زیاد است. کمی صبر کنید.',
    };

    function showError(id, msg) {
        const el = document.getElementById(id);
        el.textContent = errorMap[msg] || msg || 'خطایی رخ داد';
        el.classList.add('show');
    }

    document.getElementById('loginForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const r = await App.api('login', fd);
        if (r.success) {
            App.toast('خوش آمدید! 🎉', 'success');
            setTimeout(() => location.href = '/index.php', 500);
        } else {
            showError('loginError', r.message);
        }
    });

    document.getElementById('registerForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const r = await App.api('register', fd);
        if (r.success) {
            App.toast('ثبت‌نام موفق! 🎉', 'success');
            setTimeout(() => location.href = '/index.php', 500);
        } else {
            showError('registerError', r.message);
        }
    });
    </script>
</body>
</html>
