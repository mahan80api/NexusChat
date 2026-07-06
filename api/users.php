<?php
/**
 * NexusChat - Users API
 */
define('NEXUSCHAT_API', true);
require_once __DIR__ . '/../config/config.php';

header('Access-Control-Allow-Origin: ' . APP_URL);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_auth();
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$userId = current_user_id();
$user   = new User();
$notif  = new Notification();

try {
    switch ($action) {

        case 'search':
            $query = sanitize($_GET['q'] ?? '');
            if (mb_strlen($query) < 2) {
                json_response(['success' => true, 'users' => []]);
            }
            $results = $user->search($query, $userId);
            json_response(['success' => true, 'users' => $results]);
            break;

        case 'profile':
            $targetId = (int)($_GET['user_id'] ?? $_GET['id'] ?? 0);
            $profile = $user->getPublicProfile($targetId);
            if (!$profile) throw new Exception('کاربر یافت نشد');
            $profile['is_blocked'] = $user->isBlocked($userId, $targetId);
            json_response(['success' => true, 'user' => $profile]);
            break;

        case 'update':
            $data = [];
            if (!empty($_POST['display_name'])) $data['display_name'] = sanitize($_POST['display_name']);
            if (isset($_POST['bio'])) $data['bio'] = sanitize($_POST['bio']);
            if (isset($_POST['status_text'])) $data['status_text'] = sanitize($_POST['status_text']);
            if (!empty($_POST['theme'])) $data['theme'] = sanitize($_POST['theme']);
            if (!empty($_POST['language'])) $data['language'] = sanitize($_POST['language']);

            if (!empty($data)) {
                $user->updateProfile($userId, $data);
            }
            json_response(['success' => true, 'message' => 'پروفایل به‌روز شد']);
            break;

        case 'block':
            $targetId = (int)($_POST['user_id'] ?? 0);
            $user->block($userId, $targetId);
            json_response(['success' => true]);
            break;

        case 'unblock':
            $targetId = (int)($_POST['user_id'] ?? 0);
            $user->unblock($userId, $targetId);
            json_response(['success' => true]);
            break;

        case 'notifications':
            $items = $notif->getForUser($userId);
            $unread = $notif->unreadCount($userId);
            json_response(['success' => true, 'notifications' => $items, 'unread_count' => $unread]);
            break;

        case 'notifications_read':
            $notif->markAllRead($userId);
            json_response(['success' => true]);
            break;

        case 'heartbeat':
            $user->setOnline($userId);
            json_response(['success' => true, 'time' => time()]);
            break;

        case 'logout_all':
            $user->setOffline($userId);
            session_destroy();
            json_response(['success' => true]);
            break;

        default:
            json_response(['success' => false, 'error' => 'unknown_action'], 400);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 400);
}
