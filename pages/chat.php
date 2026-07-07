<?php
/**
 * NexusChat - Main Chat Page
 */
define('NEXUSCHAT', true);
require_once __DIR__ . '/../config/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/login');
    exit;
}

$currentUser = (new User())->findById($_SESSION['user_id']);
$userTheme = $currentUser['theme'] ?? 'galaxy';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="<?= htmlspecialchars($userTheme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🌌 NexusChat - گفتگو</title>
    <link rel="stylesheet" href="assets/css/galaxy.css">
    <link rel="stylesheet" href="assets/css/themes.css">
    <link rel="stylesheet" href="assets/css/dnd.css">
    <link rel="stylesheet" href="assets/css/link_preview.css">
    <link rel="stylesheet" href="assets/css/stickers.css">
    <link rel="stylesheet" href="assets/css/polls.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🌌</text></svg>">
    <meta name="user-id" content="<?= $currentUser['id'] ?>">
    <meta name="user-theme" content="<?= htmlspecialchars($userTheme) ?>">
</head>
<body>
    <div class="starfield"></div>
    <div class="loading-overlay" id="initialLoader">
        <div class="spinner"></div>
    </div>
    <script>
    window.currentUser = <?= json_encode([
        'id'           => $currentUser['id'],
        'username'     => $currentUser['username'],
        'display_name' => $currentUser['display_name'],
        'avatar'       => $currentUser['avatar'],
        'email'        => $currentUser['email'],
        'theme'        => $userTheme,
    ], JSON_UNESCAPED_UNICODE) ?>;
    </script>
    <script src="assets/js/galaxy.js"></script>
    <script src="assets/js/voice.js"></script>
    <script src="assets/js/search.js"></script>
    <script src="assets/js/theme.js"></script>
    <script src="assets/js/dnd.js"></script>
    <script src="assets/js/link_preview.js"></script>
    <script src="assets/js/stickers.js"></script>
    <script src="assets/js/polls.js"></script>
    <script src="assets/js/chat.js"></script>
    <script>
    App.currentUser = window.currentUser;

    (function() {
        const saved = localStorage.getItem('nc_theme') || window.currentUser.theme || 'galaxy';
        if (window.ThemeManager) ThemeManager.apply(saved, { skipTransition: true });
    })();

    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => {
            const l = document.getElementById('initialLoader');
            if (l) l.remove();
            ChatUI.start();
        }, 300);

        if (window.ThemeManager)   ThemeManager.init();
        if (window.DNDManager)     DNDManager.init();
        if (window.StickerUI)      StickerUI.init();
        if (window.PollUI)         PollUI.startTimerUpdater();

        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                if (typeof SearchUI !== 'undefined') SearchUI.open();
            }
        });
    });
    </script>
</body>
</html>
