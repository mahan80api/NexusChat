<?php
/**
 * NexusChat - User API
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
        case 'me':
            $u = $user->findById($userId);
            json_response(['success' => true, 'user' => $u]);
            break;

        case 'profile':
            $id  = (int)($_GET['id'] ?? 0);
            $u   = $user->getPublicProfile($id);
            json_response(['success' => true, 'user' => $u]);
            break;

        case 'update':
            $allowed = ['display_name', 'bio', 'status_text', 'theme', 'language', 'avatar'];
            $data = [];
            foreach ($allowed as $f) {
                if (isset($_POST[$f])) $data[$f] = $_POST[$f];
            }
            if (!empty($_FILES['avatar'])) {
                $url = handle_upload('avatar', ALLOWED_IMAGE_EXTS, 2 * 1024 * 1024);
                if ($url) $data['avatar'] = $url;
            }
            $user->update($userId, $data);
            json_response(['success' => true]);
            break;

        case 'update_theme':
            $theme = $_POST['theme'] ?? 'galaxy';
            $allowed_themes = ['galaxy', 'light', 'dark', 'purple', 'ocean', 'custom'];
            if (!in_array($theme, $allowed_themes, true)) $theme = 'galaxy';
            $user->update($userId, ['theme' => $theme]);
            json_response(['success' => true, 'theme' => $theme]);
            break;

        case 'search':
            $q = trim($_GET['q'] ?? '');
            $users = $user->search($q, 20);
            json_response(['success' => true, 'users' => $users]);
            break;

        case 'contacts':
            $q = trim($_GET['q'] ?? '');
            $contacts = $user->searchContacts($userId, $q);
            json_response(['success' => true, 'contacts' => $contacts]);
            break;

        case 'set_status':
            $status = $_POST['status_text'] ?? '';
            $user->update($userId, ['status_text' => $status]);
            json_response(['success' => true]);
            break;

        case 'change_password':
            $old = $_POST['old_password'] ?? '';
            $new = $_POST['new_password'] ?? '';
            if (strlen($new) < 6) throw new Exception('رمز جدید باید حداقل ۶ کاراکتر باشد');
            if (!$user->verifyPassword($userId, $old)) throw new Exception('رمز فعلی اشتباه');
            $stmt = Database::getInstance()->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([password_hash($new, PASSWORD_BCRYPT), $userId]);
            json_response(['success' => true]);
            break;

        case 'delete_account':
            if (($_POST['confirm'] ?? '') !== 'DELETE') throw new Exception('تأیید نشد');
            $db = Database::getInstance();
            $db->beginTransaction();
            $db->prepare("DELETE FROM messages WHERE sender_id = ?")->execute([$userId]);
            $db->prepare("DELETE FROM chat_members WHERE user_id = ?")->execute([$userId]);
            $db->prepare("DELETE FROM sessions WHERE user_id = ?")->execute([$userId]);
            $db->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
            $db->commit();
            session_destroy();
            json_response(['success' => true]);
            break;

        default:
            json_response(['success' => false, 'error' => 'unknown_action'], 400);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 400);
}
