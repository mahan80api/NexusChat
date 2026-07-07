<?php
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
        case 'list':
            $lat = (float)($_GET['lat'] ?? 0);
            $lng = (float)($_GET['lng'] ?? 0);
            $radius = (int)($_GET['radius'] ?? 50);
            $cat = sanitize($_GET['category'] ?? '');
            $sql = "SELECT c.*, u.display_name as owner_name,
                (SELECT COUNT(*) FROM chat_members WHERE chat_id = c.id) as member_count
                FROM chats c
                LEFT JOIN users u ON u.id = c.owner_id
                WHERE c.type = 'channel' AND c.is_public = 1";
            $params = [];
            if ($cat) { $sql .= " AND c.category = ?"; $params[] = $cat; }
            $sql .= " ORDER BY c.updated_at DESC LIMIT 50";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            json_response(['success' => true, 'channels' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'create':
            $name = sanitize($_POST['name'] ?? '');
            $desc = sanitize($_POST['description'] ?? '');
            $cat = sanitize($_POST['category'] ?? 'general');
            $lat = $_POST['lat'] ?? null;
            $lng = $_POST['lng'] ?? null;
            if (!$name) json_response(['success' => false, 'message' => 'no_name'], 400);
            $db->prepare("INSERT INTO chats (type, name, description, owner_id, is_public, category, lat, lng) VALUES ('channel', ?, ?, ?, 1, ?, ?, ?)")
                ->execute([$name, $desc, $uid, $cat, $lat, $lng]);
            $chatId = (int)$db->lastInsertId();
            $db->prepare("INSERT INTO chat_members (chat_id, user_id, role) VALUES (?, ?, 'owner')")
                ->execute([$chatId, $uid]);
            json_response(['success' => true, 'chat_id' => $chatId]);
            break;

        case 'subscribe':
            $chatId = (int)($_POST['chat_id'] ?? 0);
            $stmt = $db->prepare("SELECT 1 FROM chat_members WHERE chat_id = ? AND user_id = ?");
            $stmt->execute([$chatId, $uid]);
            if (!$stmt->fetch()) {
                $db->prepare("INSERT INTO chat_members (chat_id, user_id) VALUES (?, ?)")->execute([$chatId, $uid]);
            }
            json_response(['success' => true]);
            break;

        case 'unsubscribe':
            $chatId = (int)($_POST['chat_id'] ?? 0);
            $db->prepare("DELETE FROM chat_members WHERE chat_id = ? AND user_id = ?")->execute([$chatId, $uid]);
            json_response(['success' => true]);
            break;

        case 'nearby':
            $lat = (float)($_GET['lat'] ?? 0);
            $lng = (float)($_GET['lng'] ?? 0);
            $radius = (int)($_GET['radius'] ?? 10);
            if (!$lat || !$lng) json_response(['success' => false, 'message' => 'no_location'], 400);
            $stmt = $db->prepare("SELECT c.*,
                (6371 * acos(cos(radians(?)) * cos(radians(c.lat)) * cos(radians(c.lng) - radians(?)) + sin(radians(?)) * sin(radians(c.lat)))) as distance_km
                FROM chats c WHERE c.type = 'channel' AND c.is_public = 1 AND c.lat IS NOT NULL
                HAVING distance_km < ? ORDER BY distance_km ASC LIMIT 50");
            $stmt->execute([$lat, $lng, $lat, $radius]);
            json_response(['success' => true, 'channels' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        default:
            json_response(['success' => false, 'message' => 'unknown_action'], 400);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
