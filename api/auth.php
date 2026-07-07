<?php
/**
 * NexusChat - Auth API
 */
define('NEXUSCHAT_API', true);
require_once __DIR__ . '/../config/config.php';

header('Access-Control-Allow-Origin: ' . APP_URL);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$db = Database::getInstance();

try {
    switch ($action) {
        case 'register':
            rate_limit('reg_' . ($_SERVER['REMOTE_ADDR'] ?? ''), RATE_LIMIT_REGISTER);
            $username = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['username'] ?? '');
            $displayName = sanitize($_POST['display_name'] ?? '');
            $password = $_POST['password'] ?? '';
            if (strlen($username) < 3 || strlen($username) > 32) json_response(['success' => false, 'message' => 'نام کاربری باید ۳-۳۲ کاراکتر باشد'], 400);
            if (strlen($password) < 6) json_response(['success' => false, 'message' => 'رمز عبور حداقل ۶ کاراکتر'], 400);
            $displayName = $displayName ?: $username;
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) json_response(['success' => false, 'message' => 'نام کاربری تکراری است'], 400);
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $db->prepare("INSERT INTO users (username, display_name, password_hash, role, created_at) VALUES (?, ?, ?, 'user', NOW())")
                ->execute([$username, $displayName, $hash]);
            $uid = (int)$db->lastInsertId();
            foreach (['IRR', 'USD', 'EUR', 'BTC', 'ETH', 'TON', 'USDT'] as $cur) {
                $num = 'WAL' . str_pad($uid, 8, '0', STR_PAD_LEFT) . strtoupper(substr(md5($uid.$cur), 0, 6));
                $db->prepare("INSERT INTO wallets (user_id, currency, balance, wallet_number, created_at) VALUES (?, ?, 0, ?, NOW())")
                    ->execute([$uid, $cur, $num]);
            }
            session_regenerate_id(true);
            $_SESSION['user_id'] = $uid;
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
            json_response(['success' => true, 'user_id' => $uid, 'redirect' => '/index.php']);
            break;

        case 'login':
            rate_limit('login_' . ($_SERVER['REMOTE_ADDR'] ?? ''), RATE_LIMIT_LOGIN);
            $username = sanitize($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $stmt = $db->prepare("SELECT * FROM users WHERE username = ? OR email = ? OR phone = ? LIMIT 1");
            $stmt->execute([$username, $username, $username]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$u || !password_verify($password, $u['password_hash'])) {
                json_response(['success' => false, 'message' => 'نام کاربری یا رمز اشتباه'], 401);
            }
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$u['id'];
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
            $db->prepare("UPDATE users SET last_seen = NOW(), is_online = 1 WHERE id = ?")->execute([$u['id']]);
            json_response(['success' => true, 'redirect' => '/index.php']);
            break;

        case 'logout':
            $_SESSION = [];
            session_destroy();
            json_response(['success' => true]);
            break;

        case 'me':
            $u = current_user();
            if (!$u) json_response(['success' => false], 401);
            json_response(['success' => true, 'user' => [
                'id' => (int)$u['id'], 'username' => $u['username'], 'display_name' => $u['display_name'],
                'avatar' => $u['avatar'], 'role' => $u['role'] ?? 'user',
            ]]);
            break;

        default:
            json_response(['success' => false, 'message' => 'unknown_action'], 400);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
