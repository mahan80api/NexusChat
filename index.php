<?php
/**
 * NexusChat - Front Controller
 */
define('NEXUSCHAT', true);
require_once __DIR__ . '/config/config.php';

$url = $_GET['url'] ?? '';
$url = trim($url, '/');
$parts = $url ? explode('/', $url) : [];

// API routes
if (!empty($parts[0]) && $parts[0] === 'api') {
    array_shift($parts);
    $endpoint = !empty($parts[0]) ? $parts[0] : '';
    $apiFile = __DIR__ . '/api/' . $endpoint . '.php';

    if (file_exists($apiFile)) {
        require_once $apiFile;
    } else {
        json_response(['success' => false, 'error' => 'not_found', 'message' => 'API endpoint not found'], 404);
    }
    exit;
}

// Page routes
$page = !empty($parts[0]) ? $parts[0] : 'home';

if ($page === 'home' || $page === '') {
    if (!empty($_SESSION['user_id'])) {
        header('Location: ' . APP_URL . '/chat');
    } else {
        header('Location: ' . APP_URL . '/login');
    }
    exit;
}

$pageFile = __DIR__ . '/pages/' . $page . '.php';
if (file_exists($pageFile)) {
    require_once $pageFile;
} else {
    http_response_code(404);
    echo '<h1>404 - صفحه یافت نشد</h1>';
    echo '<p><a href="' . APP_URL . '">بازگشت به خانه</a></p>';
}
