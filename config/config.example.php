<?php
/**
 * NexusChat - Configuration Example
 * Copy to config.php and fill in your values
 */

// --- Database ---
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'nexuschat');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// --- App ---
define('APP_NAME', 'NexusChat');
define('APP_URL', getenv('APP_URL') ?: 'http://localhost:8000');
define('APP_ENV', getenv('APP_ENV') ?: 'development'); // development|production
define('APP_DEBUG', APP_ENV === 'development');
define('APP_VERSION', '1.0.0');
define('APP_SECRET', getenv('APP_SECRET') ?: 'change-me-to-a-long-random-string-please');

// --- Pusher (real-time) ---
define('PUSHER_KEY', getenv('PUSHER_KEY') ?: '');
define('PUSHER_SECRET', getenv('PUSHER_SECRET') ?: '');
define('PUSHER_APP_ID', getenv('PUSHER_APP_ID') ?: '');
define('PUSHER_CLUSTER', getenv('PUSHER_CLUSTER') ?: 'mt1');

// --- Upload limits ---
define('MAX_UPLOAD_SIZE', 50 * 1024 * 1024); // 50 MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_VIDEO_TYPES', ['mp4', 'webm', 'mov', 'avi']);
define('ALLOWED_AUDIO_TYPES', ['mp3', 'wav', 'ogg', 'm4a', 'webm']);

// --- Storage ---
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', APP_URL . '/uploads/');

// --- Rate limits (per hour) ---
define('RATE_LIMIT_MESSAGES', 600);
define('RATE_LIMIT_LOGIN', 10);
define('RATE_LIMIT_REGISTER', 3);

// --- Timezone ---
date_default_timezone_set('Asia/Tehran');

// --- Session ---
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', APP_ENV === 'production' ? 1 : 0);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', 1);
    ini_set('session.gc_maxlifetime', 30 * 24 * 60 * 60);
    session_start();
}

// --- Error reporting ---
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/../logs/error.log');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/../logs/error.log');
}

// --- Helper functions ---
function json_response($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function sanitize($v) {
    if (is_array($v)) return array_map('sanitize', $v);
    return htmlspecialchars(trim((string)$v), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function current_user() {
    $uid = current_user_id();
    if (!$uid) return null;
    $stmt = Database::getInstance()->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$uid]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function require_auth() {
    if (!current_user_id()) {
        json_response(['success' => false, 'message' => 'unauthorized'], 401);
    }
}

function rate_limit($key, $limit) {
    $f = sys_get_temp_dir() . '/rl_' . md5($key);
    $c = is_file($f) ? (int)file_get_contents($f) : 0;
    if ($c >= $limit) json_response(['success' => false, 'message' => 'rate_limited'], 429);
    file_put_contents($f, $c + 1);
}

function pusher_trigger($channel, $event, $data) {
    if (!PUSHER_KEY) return;
    $body = json_encode(['name' => $event, 'channel' => $channel, 'data' => json_encode($data)]);
    $timestamp = time();
    $bodyMd5 = md5($body);
    $query = [
        'auth_key' => PUSHER_KEY, 'auth_timestamp' => $timestamp, 'auth_version' => '1.0',
        'body_md5' => $bodyMd5,
    ];
    ksort($query);
    $str = "POST\n/apps/" . PUSHER_APP_ID . "/events\n" . http_build_query($query);
    $query['auth_signature'] = hash_hmac('sha256', $str, PUSHER_SECRET);
    $url = 'https://api-' . PUSHER_CLUSTER . '.pusher.com/apps/' . PUSHER_APP_ID . '/events?' . http_build_query($query);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 3,
    ]);
    curl_exec($ch); curl_close($ch);
}

class Database {
    private static $pdo = null;
    public static function getInstance() {
        if (self::$pdo === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            try {
                self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                if (APP_DEBUG) json_response(['success' => false, 'message' => 'db_error', 'detail' => $e->getMessage()], 500);
                json_response(['success' => false, 'message' => 'db_error'], 500);
            }
        }
        return self::$pdo;
    }
}
