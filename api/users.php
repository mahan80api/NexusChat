<?php
/**
 * NexusChat - Users API (search, contacts, profile)
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
$uid = current_user_id();
if (!$uid) json_response(['success' => false, 'message' => 'unauthorized'], 401);

try {
    switch ($action) {
        case 'search':
            $q = sanitize($_GET['q'] ?? '');
            if (!$q) json_response(['success' => true, 'users' => []]);
            json_response(['success' => true, 'users' => $user->search($q, 20)]);
            break;

        case 'contacts':
            $q = sanitize($_GET['q'] ?? '');
            json_response(['success' => true, 'users' => $user->searchContacts($uid, $q)]);
            break;

        case 'profile':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) json_response(['success' => false, 'message' => 'missing_id'], 400);
            json_response(['success' => true, 'user' => $user->getPublicProfile($id)]);
            break;

        case 'lookup':
            $username = sanitize($_GET['username'] ?? '');
            if (!$username) json_response(['success' => false, 'message' => 'missing_username'], 400);
            $u = $user->findByUsername($username);
            if (!$u) json_response(['success' => false, 'message' => 'not_found'], 404);
            json_response(['success' => true, 'user' => $user->getPublicProfile($u['id'])]);
            break;

        case 'update':
            $updates = [];
            foreach (['display_name', 'bio', 'status_text', 'avatar', 'theme', 'language'] as $f) {
                if (isset($_POST[$f])) $updates[$f] = sanitize($_POST[$f]);
            }
            $user->update($uid, $updates);
            json_response(['success' => true, 'user' => $user->getPublicProfile($uid)]);
            break;

        case 'upload_avatar':
            if (!isset($_FILES['avatar'])) json_response(['success' => false, 'message' => 'no_file'], 400);
            $file = $_FILES['avatar'];
            if ($file['error'] !== UPLOAD_ERR_OK) json_response(['success' => false, 'message' => 'upload_error'], 400);
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ALLOWED_IMAGE_TYPES)) json_response(['success' => false, 'message' => 'bad_type'], 400);
            if ($file['size'] > 5 * 1024 * 1024) json_response(['success' => false, 'message' => 'too_large'], 400);
            if (!is_dir(UPLOAD_DIR . 'avatars')) mkdir(UPLOAD_DIR . 'avatars', 0755, true);
            $name = 'avatar_' . $uid . '_' . time() . '.' . $ext;
            $path = UPLOAD_DIR . 'avatars/' . $name;
            if (move_uploaded_file($file['tmp_name'], $path)) {
                $url = UPLOAD_URL . 'avatars/' . $name;
                $user->update($uid, ['avatar' => $url]);
                json_response(['success' => true, 'avatar' => $url]);
            }
            json_response(['success' => false, 'message' => 'move_failed'], 500);
            break;

        case 'set_dnd':
            $minutes = (int)($_POST['minutes'] ?? 0);
            $until = $minutes > 0 ? date('Y-m-d H:i:s', time() + $minutes * 60) : null;
            Database::getInstance()->prepare("UPDATE users SET dnd_until = ? WHERE id = ?")->execute([$until, $uid]);
            json_response(['success' => true, 'dnd_until' => $until]);
            break;

        case 'set_theme':
            $theme = sanitize($_POST['theme'] ?? 'cosmic');
            Database::getInstance()->prepare("UPDATE users SET theme = ? WHERE id = ?")->execute([$theme, $uid]);
            json_response(['success' => true, 'theme' => $theme]);
            break;

        default:
            json_response(['success' => false, 'message' => 'unknown_action'], 400);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
