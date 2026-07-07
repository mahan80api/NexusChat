<?php
/**
 * NexusChat - Users API
 */
define('NEXUSCHAT_API', true);
require_once __DIR__ . '/../config/config.php';

header('Access-Control-Allow-Origin: ' . APP_URL);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_auth();
$uid = current_user_id();
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$db = Database::getInstance();

try {
    switch ($action) {
        case 'search':
            $q = sanitize($_GET['q'] ?? '');
            if (strlen($q) < 2) json_response(['success' => true, 'users' => []]);
            $like = '%' . $q . '%';
            $stmt = $db->prepare("SELECT id, username, display_name, avatar, is_online, last_seen FROM users
                WHERE id != ? AND (username LIKE ? OR display_name LIKE ? OR phone LIKE ?) LIMIT 20");
            $stmt->execute([$uid, $like, $like, $like]);
            json_response(['success' => true, 'users' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'contacts':
            $stmt = $db->prepare("SELECT DISTINCT u.id, u.username, u.display_name, u.avatar, u.is_online, u.last_seen
                FROM users u JOIN chat_members cm ON cm.user_id = u.id
                WHERE cm.chat_id IN (SELECT chat_id FROM chat_members WHERE user_id = ?) AND u.id != ?
                LIMIT 100");
            $stmt->execute([$uid, $uid]);
            json_response(['success' => true, 'users' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'profile':
            $userId = (int)($_GET['user_id'] ?? $uid);
            $stmt = $db->prepare("SELECT id, username, display_name, avatar, bio, phone, is_online, last_seen, role, created_at FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$u) json_response(['success' => false, 'message' => 'not_found'], 404);
            json_response(['success' => true, 'user' => $u]);
            break;

        case 'update':
            $displayName = sanitize($_POST['display_name'] ?? '');
            $bio = sanitize($_POST['bio'] ?? '');
            $avatar = sanitize($_POST['avatar'] ?? '');
            $updates = []; $params = [];
            if ($displayName) { $updates[] = 'display_name = ?'; $params[] = $displayName; }
            if ($bio !== '') { $updates[] = 'bio = ?'; $params[] = $bio; }
            if ($avatar) { $updates[] = 'avatar = ?'; $params[] = $avatar; }
            if (!$updates) json_response(['success' => false, 'message' => 'nothing_to_update'], 400);
            $params[] = $uid;
            $db->prepare("UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?")->execute($params);
            json_response(['success' => true]);
            break;

        case 'set_dnd':
            $minutes = (int)($_POST['minutes'] ?? 0);
            $until = $minutes > 0 ? date('Y-m-d H:i:s', time() + $minutes * 60) : null;
            $db->prepare("INSERT INTO user_settings (user_id, dnd_until, updated_at) VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE dnd_until = VALUES(dnd_until), updated_at = NOW()")
                ->execute([$uid, $until]);
            json_response(['success' => true, 'dnd_until' => $until]);
            break;

        case 'set_theme':
            $theme = preg_replace('/[^a-z0-9-]/', '', $_POST['theme'] ?? 'cosmic');
            $db->prepare("INSERT INTO user_settings (user_id, theme, updated_at) VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE theme = VALUES(theme), updated_at = NOW()")
                ->execute([$uid, $theme]);
            json_response(['success' => true]);
            break;

        case 'lookup':
            $identifier = sanitize($_GET['identifier'] ?? '');
            $stmt = $db->prepare("SELECT id, username, display_name, avatar FROM users WHERE username = ? OR phone = ? OR email = ? LIMIT 1");
            $stmt->execute([$identifier, $identifier, $identifier]);
            json_response(['success' => true, 'user' => $stmt->fetch(PDO::FETCH_ASSOC)]);
            break;

        default:
            json_response(['success' => false, 'message' => 'unknown_action'], 400);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
