<?php
/**
 * NexusChat — single-page app entry
 */
require_once __DIR__ . '/config/config.php';

if (!current_user_id()) {
    header('Location: /login.php');
    exit;
}

$me = current_user();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0a0420">
    <title><?= APP_NAME ?> — پیام‌رسان کیهانی</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">

    <!-- Vendor CSS -->
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/chat.css">
    <link rel="stylesheet" href="/assets/css/wallet.css">
    <link rel="stylesheet" href="/assets/css/themes.css">
    <link rel="stylesheet" href="/assets/css/animations.css">

    <script>
        window.APP_USER = <?= json_encode($me, JSON_UNESCAPED_UNICODE) ?>;
        window.APP_URL = <?= json_encode(APP_URL) ?>;
        window.PUSHER_KEY = <?= json_encode(PUSHER_KEY) ?>;
        window.PUSHER_CLUSTER = <?= json_encode(PUSHER_CLUSTER) ?>;
    </script>
</head>
<body data-theme="<?= htmlspecialchars($me['theme'] ?? 'cosmic') ?>">

    <!-- Animated background -->
    <div class="cosmos-bg" aria-hidden="true">
        <div class="stars"></div>
        <div class="nebula"></div>
        <div class="particles"></div>
    </div>

    <div class="app-shell" id="appShell">

        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <header class="sidebar-header">
                <button class="hamburger" id="hamburger" aria-label="منو">☰</button>
                <h1 class="logo">
                    <span class="logo-icon">✨</span>
                    <span class="logo-text">NexusChat</span>
                </h1>
                <button class="icon-btn" id="themeBtn" title="تم">🎨</button>
            </header>

            <div class="sidebar-search">
                <input type="search" id="globalSearch" placeholder="🔍 جستجو...">
            </div>

            <nav class="sidebar-nav">
                <button class="nav-btn active" data-view="chats">💬 گفت‌وگوها</button>
                <button class="nav-btn" data-view="contacts">👥 مخاطبان</button>
                <button class="nav-btn" data-view="channels">📢 کانال‌ها</button>
                <button class="nav-btn" data-view="bots">🤖 ربات‌ها</button>
                <button class="nav-btn" data-view="calls">📞 تماس‌ها</button>
            </nav>

            <div class="chat-list" id="chatList"></div>

            <footer class="sidebar-footer">
                <div class="user-pill" id="userPill">
                    <img class="avatar" src="<?= htmlspecialchars($me['avatar'] ?? '/assets/img/default-avatar.svg') ?>" alt="">
                    <div class="user-info">
                        <div class="user-name"><?= htmlspecialchars($me['display_name']) ?></div>
                        <div class="user-status">@<?= htmlspecialchars($me['username']) ?></div>
                    </div>
                </div>
                <div class="user-actions">
                    <button class="icon-btn" id="walletBtn" title="کیف پول">💰</button>
                    <button class="icon-btn" id="settingsBtn" title="تنظیمات">⚙️</button>
                    <button class="icon-btn" id="logoutBtn" title="خروج">🚪</button>
                </div>
            </footer>
        </aside>

        <!-- Main panel -->
        <main class="main-panel" id="mainPanel">
            <div class="chat-viewport" id="chatViewport">
                <div class="welcome-screen" id="welcomeScreen">
                    <div class="welcome-content">
                        <div class="welcome-icon">🌌</div>
                        <h2>به NexusChat خوش آمدید</h2>
                        <p>یک گفت‌وگو انتخاب کنید یا شروع جدیدی داشته باشید</p>
                    </div>
                </div>
            </div>
        </main>

    </div>

    <!-- Modals root -->
    <div class="modal-root" id="modalRoot"></div>
    <div class="toast-root" id="toastRoot"></div>

    <!-- Scripts -->
    <script src="/assets/js/app.js"></script>
    <script src="/assets/js/auth.js"></script>
    <script src="/assets/js/chat.js"></script>
    <script src="/assets/js/contacts.js"></script>
    <script src="/assets/js/channels.js"></script>
    <script src="/assets/js/bots.js"></script>
    <script src="/assets/js/calls.js"></script>
    <script src="/assets/js/wallet.js"></script>
    <script src="/assets/js/themes.js"></script>
    <script src="/assets/js/polls.js"></script>
    <script src="/assets/js/stickers.js"></script>
    <script src="/assets/js/push.js"></script>
    <script src="/assets/js/voice.js"></script>
    <script src="/assets/js/dnd.js"></script>
    <script src="/assets/js/forward.js"></script>
    <script src="/assets/js/search.js"></script>
    <script src="/assets/js/stats.js"></script>
    <script src="/assets/js/preview.js"></script>

    <script>
        // Boot
        document.addEventListener('DOMContentLoaded', () => {
            if (window.ThemeManager) ThemeManager.init();
            if (window.Chat) Chat.init();
            if (window.Push) Push.init();
        });
    </script>
</body>
</html>
