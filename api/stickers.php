<?php
/**
 * NexusChat - Stickers API
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
$sticker = new Sticker();

try {
    switch ($action) {
        case 'available_packs':
            json_response(['success' => true, 'packs' => $sticker->getAvailablePacks($userId)]);
            break;

        case 'pack_stickers':
            $packId = (int)($_GET['pack_id'] ?? 0);
            json_response(['success' => true, 'stickers' => $sticker->getPackStickers($userId, $packId)]);
            break;

        case 'install':
            $packId = (int)($_POST['pack_id'] ?? 0);
            $sticker->installPack($userId, $packId);
            json_response(['success' => true, 'message' => 'پک نصب شد ✨']);
            break;

        case 'uninstall':
            $packId = (int)($_POST['pack_id'] ?? 0);
            $sticker->uninstallPack($userId, $packId);
            json_response(['success' => true, 'message' => 'پک حذف شد']);
            break;

        case 'toggle_fav_pack':
            $packId = (int)($_POST['pack_id'] ?? 0);
            $fav = $sticker->toggleFavoritePack($userId, $packId);
            json_response(['success' => true, 'is_favorite' => $fav]);
            break;

        case 'toggle_fav_sticker':
            $stickerId = (int)($_POST['sticker_id'] ?? 0);
            $fav = $sticker->toggleFavoriteSticker($userId, $stickerId);
            json_response(['success' => true, 'is_favorite' => $fav]);
            break;

        case 'favorites':
            json_response(['success' => true, 'stickers' => $sticker->getFavorites($userId)]);
            break;

        case 'trending':
            $days = (int)($_GET['days'] ?? 7);
            json_response(['success' => true, 'stickers' => $sticker->getTrending($userId, $days)]);
            break;

        case 'search':
            $q = $_GET['q'] ?? '';
            json_response(['success' => true, 'stickers' => $sticker->search($userId, $q)]);
            break;

        case 'send':
            $chatId = (int)($_POST['chat_id'] ?? 0);
            $stickerId = (int)($_POST['sticker_id'] ?? 0);
            $message = $sticker->sendSticker($userId, $chatId, $stickerId);
            json_response(['success' => true, 'message' => $message]);
            break;

        case 'create_pack':
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $icon = trim($_POST['icon'] ?? '😀');
            $isPublic = (bool)($_POST['is_public'] ?? false);
            if (!$name) throw new Exception('نام پک الزامی است');
            $packId = $sticker->createCustomPack($userId, $name, $description, $icon, $isPublic);
            json_response(['success' => true, 'pack_id' => $packId, 'message' => 'پک ساخته شد ✨']);
            break;

        case 'upload_sticker':
            $packId = (int)($_POST['pack_id'] ?? 0);
            $emoji = $_POST['emoji'] ?? '😀';
            if (empty($_FILES['file'])) throw new Exception('فایلی انتخاب نشده');
            $stickerId = $sticker->uploadSticker($userId, $packId, $_FILES['file'], $emoji);
            json_response(['success' => true, 'sticker_id' => $stickerId, 'message' => 'استیکر آپلود شد ✨']);
            break;

        case 'my_packs':
            json_response(['success' => true, 'packs' => $sticker->getMyPacks($userId)]);
            break;

        default:
            json_response(['success' => false, 'error' => 'unknown_action'], 400);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 400);
}
