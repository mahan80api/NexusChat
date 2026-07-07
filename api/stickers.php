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
        case 'packs':
            $stmt = $db->prepare("SELECT s.*, COUNT(st.id) as sticker_count
                FROM sticker_packs s LEFT JOIN stickers st ON st.pack_id = s.id
                WHERE s.is_public = 1 OR s.creator_id = ? GROUP BY s.id ORDER BY s.is_featured DESC, s.use_count DESC");
            $stmt->execute([$uid]);
            json_response(['success' => true, 'packs' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'pack':
            $packId = (int)($_GET['pack_id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM sticker_packs WHERE id = ?");
            $stmt->execute([$packId]);
            $pack = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt = $db->prepare("SELECT * FROM stickers WHERE pack_id = ? ORDER BY position");
            $stmt->execute([$packId]);
            $stickers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            json_response(['success' => true, 'pack' => $pack, 'stickers' => $stickers]);
            break;

        case 'create_pack':
            $name = sanitize($_POST['name'] ?? '');
            $desc = sanitize($_POST['description'] ?? '');
            $isPublic = (int)($_POST['is_public'] ?? 0);
            if (!$name) json_response(['success' => false, 'message' => 'no_name'], 400);
            $db->prepare("INSERT INTO sticker_packs (creator_id, name, description, is_public, created_at) VALUES (?, ?, ?, ?, NOW())")
                ->execute([$uid, $name, $desc, $isPublic]);
            json_response(['success' => true, 'pack_id' => (int)$db->lastInsertId()]);
            break;

        case 'add_sticker':
            $packId = (int)($_POST['pack_id'] ?? 0);
            if (!isset($_FILES['image'])) json_response(['success' => false, 'message' => 'no_image'], 400);
            $file = $_FILES['image'];
            if ($file['error'] !== UPLOAD_ERR_OK) json_response(['success' => false, 'message' => 'upload_error'], 400);
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['png','webp','gif','jpg','jpeg'])) json_response(['success' => false, 'message' => 'bad_format'], 400);
            $stmt = $db->prepare("SELECT creator_id FROM sticker_packs WHERE id = ?");
            $stmt->execute([$packId]);
            $p = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$p || $p['creator_id'] != $uid) json_response(['success' => false, 'message' => 'forbidden'], 403);
            $dir = UPLOAD_DIR . 'stickers/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $filename = 's_' . $packId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $path = $dir . $filename;
            if (!move_uploaded_file($file['tmp_name'], $path)) json_response(['success' => false, 'message' => 'move_failed'], 500);
            $url = UPLOAD_URL . 'stickers/' . $filename;
            $db->prepare("INSERT INTO stickers (pack_id, image_url, emoji, created_at) VALUES (?, ?, ?, NOW())")
                ->execute([$packId, $url, $_POST['emoji'] ?? '😀']);
            json_response(['success' => true, 'url' => $url]);
            break;

        case 'favorites':
            $stmt = $db->prepare("SELECT s.*, sp.name as pack_name FROM stickers s
                JOIN sticker_packs sp ON sp.id = s.pack_id
                JOIN sticker_favorites f ON f.sticker_id = s.id
                WHERE f.user_id = ? ORDER BY f.created_at DESC LIMIT 100");
            $stmt->execute([$uid]);
            json_response(['success' => true, 'stickers' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'favorite':
            $stickerId = (int)($_POST['sticker_id'] ?? 0);
            $db->prepare("INSERT IGNORE INTO sticker_favorites (user_id, sticker_id, created_at) VALUES (?, ?, NOW())")
                ->execute([$uid, $stickerId]);
            json_response(['success' => true]);
            break;

        default:
            json_response(['success' => false, 'message' => 'unknown_action'], 400);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
