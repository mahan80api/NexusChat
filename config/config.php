<?php
/**
 * NexusChat - Main Configuration
 */

if (!defined('NEXUSCHAT')) {
    define('NEXUSCHAT', true);
}

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

date_default_timezone_set('Asia/Tehran');

define('APP_NAME', 'NexusChat');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/NexusChat');
define('APP_ROOT', dirname(__DIR__));

// Paths
define('UPLOAD_PATH', APP_ROOT . '/assets/uploads/');
define('UPLOAD_URL',  'assets/uploads/');
define('AVATAR_PATH', UPLOAD_PATH . 'avatars/');
define('STORY_PATH',  UPLOAD_PATH . 'stories/');
define('FILE_PATH',   UPLOAD_PATH . 'files/');
define('VOICE_PATH',  UPLOAD_PATH . 'voice/');

// Allowed file types
define('ALLOWED_IMAGE', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
define('ALLOWED_VIDEO', ['mp4', 'webm', 'mov', 'avi']);
define('ALLOWED_AUDIO', ['mp3', 'wav', 'ogg', 'm4a', 'webm']);
define('ALLOWED_FILE',  ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'rar']);

define('MAX_UPLOAD_SIZE', 50 * 1024 * 1024);

// Session
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', 1);
    ini_set('session.gc_maxlifetime', 60 * 60 * 24 * 30);
    session_start();
}

require_once __DIR__ . '/database.php';

// Autoload classes
spl_autoload_register(function ($className) {
    $file = APP_ROOT . '/classes/' . $className . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Helpers
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function json_response($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function require_auth() {
    if (empty($_SESSION['user_id'])) {
        json_response(['success' => false, 'error' => 'unauthorized', 'message' => 'لطفاً وارد شوید'], 401);
    }
}

function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode($data) {
    return base64_decode(strtr($data, '-_', '+/'));
}

function time_ago($datetime) {
    $time = is_numeric($datetime) ? (int)$datetime : strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return 'هم اکنون';
    if ($diff < 3600) return floor($diff / 60) . ' دقیقه پیش';
    if ($diff < 86400) return floor($diff / 3600) . ' ساعت پیش';
    if ($diff < 604800) return floor($diff / 86400) . ' روز پیش';
    if ($diff < 2592000) return floor($diff / 604800) . ' هفته پیش';
    return date('Y/m/d', $time);
}
