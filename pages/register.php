<?php
/**
 * NexusChat - Register Page
 */
define('NEXUSCHAT', true);
require_once __DIR__ . '/../config/config.php';

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
    <title>🌌 NexusChat - ثبت‌نام</title>
    <link rel="stylesheet" href="assets/css/galaxy.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🌌</text></svg>">
</head>
<body>
    <div class="starfield"></div>
    <div class="auth-page">
        <div class="auth-card glass">
            <div class="auth-logo gold-text">NexusChat</div>
            <div class="auth-subtitle">🌟 ساخت حساب جدید</div>
            <div id="authError"></div>
            <form id="registerForm">
                <input class="auth-input" name="display_name" placeholder="نام نمایشی" required>
                <input class="auth-input" name="username" placeholder="نام کاربری (انگلیسی)" required pattern="[a-zA-Z0-9_]+" title="فقط حروف انگلیسی، اعداد و _">
                <input class="auth-input" name="email" type="email" placeholder="ایمیل" required>
                <input class="auth-input" name="phone" placeholder="شماره تلفن (اختیاری)">
                <input class="auth-input" type="password" name="password" placeholder="رمز عبور (حداقل ۶ کاراکتر)" required minlength="6">
                <button class="btn-primary" type="submit">✨ ساخت حساب</button>
            </form>
            <div class="auth-link">حساب دارید؟ <a href="login">ورود</a></div>
        </div>
    </div>
    <script src="assets/js/galaxy.js"></script>
    <script>
    document.getElementById('registerForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        document.body.style.opacity = '0.6';
        const data = Object.fromEntries(fd);
        const res = await App.register(data);
        if (!res.success) {
            document.body.style.opacity = '1';
            const err = document.getElementById('authError');
            err.innerHTML = `<div class="auth-error">${App.escapeHTML(res.message || 'خطا')}</div>`;
        }
    });
    </script>
</body>
</html>
