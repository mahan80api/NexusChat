<?php
/**
 * NexusChat - Main entry point
 */
define('NEXUSCHAT', true);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Database.php';

$user = current_user();
if (!$user) {
    header('Location: /login.php');
    exit;
}

$u = $user;
$u['avatar'] = $u['avatar'] ?: '/assets/img/default-avatar.svg';
$theme = $_COOKIE['nc_theme'] ?? 'cosmic';
$lang = 'fa';
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="rtl" data-theme="<?= htmlspecialchars($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0a0118">
    <title><?= APP_NAME ?> — پیام‌رسان کیهانی</title>
    <meta name="description" content="پیام‌رسان حرفه‌ای با تم کیهانی">
    <link rel="icon" type="image/svg+xml" href="/assets/img/logo.svg">
    <link rel="apple-touch-icon" href="/assets/img/icon-192.png">
    <link rel="manifest" href="/manifest.json">

    <link rel="stylesheet" href="/assets/css/themes.css">
    <link rel="stylesheet" href="/assets/css/animations.css">
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/chat.css">
    <link rel="stylesheet" href="/assets/css/wallet.css">
    <link rel="stylesheet" href="/assets/css/modal.css">
</head>
<body class="cosmic-theme">

<!-- Cosmic background -->
<div class="cosmic-bg">
    <div class="starfield"></div>
    <div class="nebula nebula-1"></div>
    <div class="nebula nebula-2"></div>
    <div class="nebula nebula-3"></div>
    <div class="particles"></div>
</div>

<!-- App shell -->
<div class="app-shell" id="app">

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <span class="logo-icon">✨</span>
                <span class="logo-text">NexusChat</span>
            </div>
            <button class="icon-btn" id="menuToggle" title="منو">☰</button>
        </div>

        <div class="sidebar-search">
            <input type="search" id="globalSearch" placeholder="🔍 جستجو...">
        </div>

        <div class="sidebar-tabs">
            <button class="tab-btn active" data-tab="chats">💬 چت‌ها</button>
            <button class="tab-btn" data-tab="channels">📢 کانال‌ها</button>
            <button class="tab-btn" data-tab="contacts">👥 مخاطبان</button>
        </div>

        <div class="sidebar-content">
            <div class="tab-pane active" id="tab-chats">
                <div id="chatList" class="chat-list"></div>
            </div>
            <div class="tab-pane" id="tab-channels">
                <div id="channelList" class="channel-list"></div>
            </div>
            <div class="tab-pane" id="tab-contacts">
                <div id="contactList" class="contact-list"></div>
            </div>
        </div>

        <div class="sidebar-footer">
            <button class="icon-btn" id="newChatBtn" title="چت جدید">✏️</button>
            <button class="icon-btn" id="statsBtn" title="آمار">📊</button>
            <button class="icon-btn" id="themeBtn" title="تم">🎨</button>
            <button class="icon-btn" id="dndBtn" title="DND">🌙</button>
            <button class="icon-btn" id="walletBtn" title="کیف پول">💰</button>
        </div>
    </aside>

    <!-- Main chat area -->
    <main class="chat-area" id="chatArea">
        <div class="welcome-screen" id="welcomeScreen">
            <div class="welcome-content">
                <div class="welcome-logo">✨</div>
                <h1>به NexusChat خوش آمدید</h1>
                <p>یک چت را انتخاب کنید یا شروع به گفتگو کنید</p>
                <button class="btn-primary" id="welcomeNewChat">شروع چت جدید</button>
            </div>
        </div>

        <div class="chat-container" id="chatContainer" style="display:none;">
            <header class="chat-header">
                <button class="icon-btn back-btn" id="backBtn">←</button>
                <div class="chat-header-info">
                    <img class="avatar" id="chatAvatar" src="">
                    <div>
                        <div class="chat-title" id="chatTitle"></div>
                        <div class="chat-status" id="chatStatus"></div>
                    </div>
                </div>
                <div class="chat-header-actions">
                    <button class="icon-btn" id="callBtn" title="تماس">📞</button>
                    <button class="icon-btn" id="videoBtn" title="ویدیو">📹</button>
                    <button class="icon-btn" id="searchInChatBtn" title="جستجو">🔍</button>
                    <button class="icon-btn" id="chatMenuBtn" title="منو">⋮</button>
                </div>
            </header>

            <div class="messages" id="messages"></div>

            <div class="typing-indicator" id="typingIndicator" style="display:none;">
                <span></span><span></span><span></span>
            </div>

            <footer class="composer">
                <div class="composer-actions">
                    <button class="icon-btn" id="emojiBtn" title="ایموجی">😊</button>
                    <button class="icon-btn" id="attachBtn" title="فایل">📎</button>
                    <button class="icon-btn" id="voiceBtn" title="صوتی">🎤</button>
                    <button class="icon-btn" id="stickerBtn" title="استیکر">😀</button>
                    <button class="icon-btn" id="pollBtn" title="نظرسنجی">📊</button>
                </div>
                <textarea id="messageInput" placeholder="پیام خود را بنویسید..." rows="1"></textarea>
                <button class="send-btn" id="sendBtn">➤</button>
            </footer>
        </div>
    </main>
</div>

<!-- Modal container -->
<div class="modal-backdrop" id="modalBackdrop" style="display:none;">
    <div class="modal" id="modal">
        <button class="modal-close" id="modalClose">×</button>
        <div class="modal-body" id="modalBody"></div>
    </div>
</div>

<!-- Toast container -->
<div class="toast-container" id="toastContainer"></div>

<!-- Floating action button -->
<button class="fab" id="fab" title="منوی سریع">✨</button>

<!-- Hidden file input -->
<input type="file" id="fileInput" style="display:none;" accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.txt,.zip">

<!-- User data for JS -->
<script>window.NEXUSCHAT = {
    user: <?= json_encode([
        'id' => (int)$u['id'],
        'username' => $u['username'],
        'display_name' => $u['display_name'],
        'avatar' => $u['avatar'],
        'is_admin' => ($u['role'] ?? 'user') === 'admin',
    ], JSON_UNESCAPED_UNICODE) ?>,
    appUrl: '<?= APP_URL ?>',
    pusherKey: '<?= PUSHER_KEY ?>',
    pusherCluster: '<?= PUSHER_CLUSTER ?>',
    csrfToken: '<?= bin2hex(random_bytes(16)) ?>'
};</script>

<script src="https://js.pusher.com/8.0/pusher.min.js" defer></script>
<script src="/assets/js/app.js" defer></script>
<script src="/assets/js/chat.js" defer></script>
<script src="/assets/js/wallet.js" defer></script>
<script src="/assets/js/contacts.js" defer></script>

<script>if ('serviceWorker' in navigator) { navigator.serviceWorker.register('/sw.js').catch(() => {}); }</script>
</body>
</html>
