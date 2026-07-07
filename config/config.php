<?php
/**
 * NexusChat - Global config
 */

// Error reporting (turn off in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// App config
define('APP_NAME', 'NexusChat');
define('APP_URL', 'http://localhost:8000');
define('APP_ENV', 'development'); // production
define('APP_DEBUG', true);
define('APP_TIMEZONE', 'Asia/Tehran');

date_default_timezone_set(APP_TIMEZONE);

// Uploads
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', APP_URL . '/uploads/');
define('MAX_UPLOAD_SIZE', 50 * 1024 * 1024); // 50MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
define('ALLOWED_VIDEO_TYPES', ['mp4', 'webm', 'mov']);
define('ALLOWED_AUDIO_TYPES', ['mp3', 'wav', 'ogg', 'webm', 'm4a']);

// Pagination
define('DEFAULT_PAGE_SIZE', 30);
define('MAX_PAGE_SIZE', 100);

// Rate limits (per minute)
define('RATE_LIMIT_MESSAGES', 60);
define('RATE_LIMIT_LOGIN', 10);
define('RATE_LIMIT_API', 300);

// ==== DB config (override in .env) ====
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'nexuschat');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// Pusher
define('PUSHER_KEY', getenv('PUSHER_KEY') ?: 'your-pusher-key');
define('PUSHER_SECRET', getenv('PUSHER_SECRET') ?: 'your-pusher-secret');
define('PUSHER_APP_ID', getenv('PUSHER_APP_ID') ?: 'your-pusher-app-id');
define('PUSHER_CLUSTER', getenv('PUSHER_CLUSTER') ?: 'mt1');

// ==== Helpers ====

if (!function_exists('json_response')) {
    function json_response($data, $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('sanitize')) {
    function sanitize($value) {
        if (is_array($value)) return array_map('sanitize', $value);
        return htmlspecialchars(trim((string)$value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

if (!function_exists('current_user_id')) {
    function current_user_id() {
        return $_SESSION['user_id'] ?? null;
    }
}

if (!function_exists('current_user')) {
    function current_user() {
        $uid = current_user_id();
        if (!$uid) return null;
        $stmt = Database::getInstance()->prepare("SELECT id, username, display_name, avatar, role FROM users WHERE id = ?");
        $stmt->execute([$uid]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('require_auth')) {
    function require_auth() {
        if (!current_user_id()) {
            json_response(['success' => false, 'message' => 'unauthorized'], 401);
        }
    }
}

if (!function_exists('require_role')) {
    function require_role($role) {
        $u = current_user();
        if (!$u || ($role === 'admin' && ($u['role'] ?? 'user') !== 'admin')) {
            json_response(['success' => false, 'message' => 'forbidden'], 403);
        }
    }
}

if (!function_exists('rate_limit')) {
    function rate_limit($key, $limit) {
        $file = sys_get_temp_dir() . '/rl_' . md5($key);
        $now = time();
        $data = is_file($file) ? json_decode(file_get_contents($file), true) : ['start' => $now, 'count' => 0];
        if ($now - $data['start'] > 60) { $data = ['start' => $now, 'count' => 0]; }
        $data['count']++;
        file_put_contents($file, json_encode($data));
        if ($data['count'] > $limit) {
            json_response(['success' => false, 'message' => 'rate_limited'], 429);
        }
    }
}

if (!function_exists('pusher_trigger')) {
    function pusher_trigger($channel, $event, $data) {
        if (PUSHER_KEY === 'your-pusher-key') return false; // not configured
        $url = 'https://api-' . PUSHER_CLUSTER . '.pusher.com/apps/' . PUSHER_APP_ID . '/events';
        $body = http_build_query([
            'name' => $event,
            'channel' => $channel,
            'data' => json_encode($data),
        ]);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: key=' . PUSHER_SECRET,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 3,
        ]);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result !== false;
    }
}

if (!function_exists('pusher_auth')) {
    function pusher_auth($channel, $socketId) {
        $uid = current_user_id();
        if (!$uid) return null;
        $stringToSign = $socketId . ':' . $channel;
        $signature = hash_hmac('sha256', $stringToSign, PUSHER_SECRET);
        return ['auth' => PUSHER_KEY . ':' . $signature, 'channel_data' => json_encode(['user_id' => (string)$uid])];
    }
}

// Auto-load classes
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/../classes/' . $class . '.php';
    if (is_file($file)) require_once $file;
});
