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

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$user = new User();

try {
    switch ($action) {

        case 'register':
            $username    = sanitize($_POST['username'] ?? '');
            $email       = sanitize($_POST['email'] ?? '');
            $phone       = sanitize($_POST['phone'] ?? '');
            $password    = $_POST['password'] ?? '';
            $displayName = sanitize($_POST['display_name'] ?? '');

            $userData = $user->register($username, $email, $password, $displayName, $phone);

            $_SESSION['user_id'] = $userData['id'];
            $user->setOnline($userData['id']);

            json_response([
                'success' => true,
                'message' => 'ثبت‌نام با موفقیت انجام شد ✨',
                'user' => [
                    'id'           => $userData['id'],
                    'username'     => $userData['username'],
                    'display_name' => $userData['display_name'],
                    'avatar'       => $userData['avatar'],
                ],
            ]);
            break;

        case 'login':
            $identifier = sanitize($_POST['identifier'] ?? $_POST['username'] ?? $_POST['email'] ?? '');
            $password   = $_POST['password'] ?? '';

            $userData = $user->login($identifier, $password);

            $_SESSION['user_id'] = $userData['id'];
            $user->setOnline($userData['id']);

            json_response([
                'success' => true,
                'message' => 'خوش آمدید ✨',
                'user' => [
                    'id'           => $userData['id'],
                    'username'     => $userData['username'],
                    'display_name' => $userData['display_name'],
                    'avatar'       => $userData['avatar'],
                ],
            ]);
            break;

        case 'logout':
            $uid = current_user_id();
            if ($uid) $user->setOffline($uid);
            session_destroy();
            json_response(['success' => true, 'message' => 'خارج شدید']);
            break;

        case 'me':
            require_auth();
            $uid = current_user_id();
            $profile = $user->getPublicProfile($uid);
            json_response(['success' => true, 'user' => $profile]);
            break;

        default:
            json_response(['success' => false, 'error' => 'unknown_action'], 400);
    }
} catch (Exception $e) {
    json_response([
        'success' => false,
        'error'   => 'auth_error',
        'message' => $e->getMessage(),
    ], 400);
}
