<?php
/**
 * NexusChat - Login Page
 */
define('NEXUSCHAT', true);
require_once __DIR__ . '/../config/config.php';

// If already logged in, redirect to chat
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/chat');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🌌 NexusChat - ورود</title>
    <link rel="stylesheet" href="assets/css/galaxy.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🌌</text></svg>">
</head>
<body>
    <div class="starfield"></div>
    <div class="auth-page">
        <div class="auth-card glass">
            <div class="auth-logo gold-text">NexusChat</div>
            <div class="auth-subtitle">✨ به کهکشان گفتگو خوش آمدید</div>
            <div id="authError"></div>
            <form id="loginForm">
                <input class="auth-input" name="identifier" placeholder="نام کاربری یا ایمیل" required autocomplete="username">
                <input class="auth-input" type="password" name="password" placeholder="رمز عبور" required autocomplete="current-password">
                <button class="btn-primary" type="submit">🚀 ورود به کهکشان</button>
            </form>
            <div class="auth-link">حساب ندارید؟ <a href="register">ثبت‌نام کنید</a></div>
        </div>
    </div>
    <script src="assets/js/galaxy.js"></script>
    <script>
    document.getElementById('loginForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        document.body.style.opacity = '0.6';
        const res = await App.login(fd.get('identifier'), fd.get('password'));
        if (!res.success) {
            document.body.style.opacity = '1';
            const err = document.getElementById('authError');
            err.innerHTML = `<div class="auth-error">${App.escapeHTML(res.message || 'خطا')}</div>`;
            setTimeout(() => err.innerHTML = '', 4000);
        }
    });
    </script>
</body>
</html>
