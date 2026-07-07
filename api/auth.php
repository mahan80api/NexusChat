<?php
/**
 * NexusChat - Auth API
 * Endpoints: register, login, logout, me
 */
define('NEXUSCHAT_API', true);
require_once __DIR__ . '/../config/config.php';

header('Access-Control-Allow-Origin: ' . APP_URL);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$user = new User();

try {
    switch ($action) {
        case 'register':
            $username = sanitize($_POST['username'] ?? '');
            $email = sanitize($_POST['email'] ?? '');
            $phone = sanitize($_POST['phone'] ?? '');
            $password = $_POST['password'] ?? '';
            $displayName = sanitize($_POST['display_name'] ?? '');

            if (!$username || !$password) json_response(['success' => false, 'message' => 'missing_fields'], 400);
            if (strlen($username) < 3) json_response(['success' => false, 'message' => 'username_too_short'], 400);
            if (strlen($password) < 6) json_response(['success' => false, 'message' => 'password_too_short'], 400);

            rate_limit('register_' . ($_SERVER['REMOTE_ADDR'] ?? ''), 5);

            $userId = $user->register($username, $email, $password, $displayName, $phone);
            $_SESSION['user_id'] = $userId;
            $user->setOnline($userId);

            $u = $user->getPublicProfile($userId);
            json_response(['success' => true, 'message' => 'ثبت‌نام موفقیت‌آمیز بود!', 'user' => $u]);
            break;

        case 'login':
            $identifier = sanitize($_POST['identifier'] ?? $_POST['username'] ?? $_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if (!$identifier || !$password) json_response(['success' => false, 'message' => 'missing_fields'], 400);

            rate_limit('login_' . ($_SERVER['REMOTE_ADDR'] ?? ''), RATE_LIMIT_LOGIN);

            $u = $user->login($identifier, $password);
            if (!$u) json_response(['success' => false, 'message' => 'invalid_credentials'], 401);

            $_SESSION['user_id'] = $u['id'];
            $user->setOnline($u['id']);
            $profile = $user->getPublicProfile($u['id']);
            json_response(['success' => true, 'message' => 'خوش آمدید!', 'user' => $profile]);
            break;

        case 'logout':
            $uid = current_user_id();
            if ($uid) $user->setOnline($uid, false);
            $_SESSION = [];
            session_destroy();
            json_response(['success' => true, 'message' => 'خروج موفقیت‌آمیز']);
            break;

        case 'me':
            $uid = current_user_id();
            if (!$uid) json_response(['success' => false, 'message' => 'unauthorized'], 401);
            json_response(['success' => true, 'user' => $user->getPublicProfile($uid)]);
            break;

        case 'update_profile':
            require_auth();
            $uid = current_user_id();
            $updates = [];
            foreach (['display_name', 'bio', 'status_text', 'avatar', 'theme', 'language'] as $f) {
                if (isset($_POST[$f])) $updates[$f] = sanitize($_POST[$f]);
            }
            $user->update($uid, $updates);
            json_response(['success' => true, 'user' => $user->getPublicProfile($uid)]);
            break;

        case 'change_password':
            require_auth();
            $uid = current_user_id();
            $old = $_POST['old_password'] ?? '';
            $new = $_POST['new_password'] ?? '';
            if (!$user->verifyPassword($uid, $old)) json_response(['success' => false, 'message' => 'wrong_old_password'], 400);
            if (strlen($new) < 6) json_response(['success' => false, 'message' => 'password_too_short'], 400);
            $hash = password_hash($new, PASSWORD_BCRYPT);
            $stmt = Database::getInstance()->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$hash, $uid]);
            json_response(['success' => true, 'message' => 'رمز عوض شد']);
            break;

        default:
            json_response(['success' => false, 'message' => 'unknown_action'], 400);
    }
} catch (Exception $e) {
    $msg = $e->getMessage();
    $map = [
        'username_taken' => 'این نام کاربری قبلاً گرفته شده',
        'email_taken' => 'این ایمیل قبلاً ثبت شده',
        'phone_taken' => 'این شماره قبلاً ثبت شده',
    ];
    json_response(['success' => false, 'message' => $map[$msg] ?? $msg], 400);
}
