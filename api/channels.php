<?php
/**
 * NexusChat - Channels API
 */
define('NEXUSCHAT_API', true);
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . APP_URL);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_auth();
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$userId = current_user_id();
$cm = new ChannelManager();

try {
    switch ($action) {
        case 'create':
            $name = $_POST['name'] ?? '';
            $username = $_POST['username'] ?? '';
            $description = $_POST['description'] ?? '';
            $isPublic = !empty($_POST['is_public']) ? 1 : 0;
            $avatar = $_POST['avatar'] ?? null;
            $id = $cm->createChannel($userId, $name, $username, $description, $isPublic, $avatar);
            json_response(['success' => true, 'channel_id' => $id]);
            break;

        case 'discover':
            $q = $_GET['q'] ?? '';
            $channels = $cm->discoverChannels(30, $q);
            json_response(['success' => true, 'channels' => $channels]);
            break;

        case 'my_channels':
            json_response(['success' => true, 'channels' => $cm->getMyChannels($userId)]);
            break;

        case 'owned':
            json_response(['success' => true, 'channels' => $cm->getOwnedChannels($userId)]);
            break;

        case 'info':
            $channelId = (int)($_GET['channel_id'] ?? 0);
            $info = $cm->getChannelById($channelId);
            if ($info) {
                $info['is_subscribed'] = $cm->isSubscribed($channelId, $userId);
                $info['is_admin'] = $cm->isAdmin($channelId, $userId);
                $info['stats'] = $cm->getStats($channelId);
            }
            json_response(['success' => true, 'channel' => $info]);
            break;

        case 'update':
            $channelId = (int)($_POST['channel_id'] ?? 0);
            $data = [];
            foreach (['name', 'description', 'avatar', 'is_public', 'slow_mode_seconds', 'sign_messages'] as $f) {
                if (isset($_POST[$f])) $data[$f] = $_POST[$f];
            }
            $cm->updateChannel($channelId, $userId, $data);
            json_response(['success' => true]);
            break;

        case 'delete':
            $channelId = (int)($_POST['channel_id'] ?? 0);
            $cm->deleteChannel($channelId, $userId);
            json_response(['success' => true]);
            break;

        // ====== Subscribers ======
        case 'subscribe':
            $channelId = (int)($_POST['channel_id'] ?? 0);
            $cm->subscribe($channelId, $userId);
            json_response(['success' => true]);
            break;

        case 'unsubscribe':
            $channelId = (int)($_POST['channel_id'] ?? 0);
            $cm->unsubscribe($channelId, $userId);
            json_response(['success' => true]);
            break;

        case 'subscribers':
            $channelId = (int)($_GET['channel_id'] ?? 0);
            json_response(['success' => true, 'subscribers' => $cm->getSubscribers($channelId)]);
            break;

        // ====== Admins ======
        case 'add_admin':
            $channelId = (int)($_POST['channel_id'] ?? 0);
            $adminUserId = (int)($_POST['user_id'] ?? 0);
            $role = $_POST['role'] ?? 'admin';
            $cm->addAdmin($channelId, $adminUserId, $role, $userId);
            json_response(['success' => true]);
            break;

        case 'remove_admin':
            $channelId = (int)($_POST['channel_id'] ?? 0);
            $adminUserId = (int)($_POST['user_id'] ?? 0);
            $cm->removeAdmin($channelId, $adminUserId, $userId);
            json_response(['success' => true]);
            break;

        case 'admins':
            $channelId = (int)($_GET['channel_id'] ?? 0);
            json_response(['success' => true, 'admins' => $cm->getChannelAdmins($channelId)]);
            break;

        // ====== Posts ======
        case 'publish':
            $channelId = (int)($_POST['channel_id'] ?? 0);
            $content = trim($_POST['content'] ?? '');
            $media = $_FILES['media'] ?? null;
            $pinned = !empty($_POST['pinned']);
            $mediaPath = null;
            $mediaType = 'text';
            if ($media && $media['error'] === UPLOAD_ERR_OK) {
                $dir = 'assets/uploads/channel/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $ext = strtolower(pathinfo($media['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm'];
                if (!in_array($ext, $allowed)) throw new Exception('invalid_file_type');
                $name = uniqid() . '.' . $ext;
                move_uploaded_file($media['tmp_name'], $dir . $name);
                $mediaPath = 'channel/' . $name;
                $mediaType = in_array($ext, ['mp4', 'webm']) ? 'video' : 'image';
            }
            $postId = $cm->publishPost($channelId, $userId, $content, $mediaPath, $mediaType, ['pinned' => $pinned]);
            json_response(['success' => true, 'post_id' => $postId]);
            break;

        case 'posts':
            $channelId = (int)($_GET['channel_id'] ?? 0);
            $limit = min(50, (int)($_GET['limit'] ?? 20));
            $offset = (int)($_GET['offset'] ?? 0);
            $posts = $cm->getPosts($channelId, $limit, $offset);
            foreach ($posts as &$p) $cm->recordView($p['id'], $userId);
            json_response(['success' => true, 'posts' => $posts]);
            break;

        case 'delete_post':
            $postId = (int)($_POST['post_id'] ?? 0);
            $cm->deletePost($postId, $userId);
            json_response(['success' => true]);
            break;

        case 'pin_post':
            $postId = (int)($_POST['post_id'] ?? 0);
            $pin = !empty($_POST['pin']);
            $cm->pinPost($postId, $userId, $pin);
            json_response(['success' => true]);
            break;

        case 'react':
            $postId = (int)($_POST['post_id'] ?? 0);
            $emoji = $_POST['emoji'] ?? '';
            $cm->reactToPost($postId, $userId, $emoji);
            json_response(['success' => true, 'reactions' => $cm->getPostReactions($postId)]);
            break;

        case 'unreact':
            $postId = (int)($_POST['post_id'] ?? 0);
            $emoji = $_POST['emoji'] ?? '';
            $cm->unreactToPost($postId, $userId, $emoji);
            json_response(['success' => true]);
            break;

        // ====== Analytics ======
        case 'stats':
            $channelId = (int)($_GET['channel_id'] ?? 0);
            json_response(['success' => true, 'stats' => $cm->getStats($channelId), 'growth' => $cm->getGrowthData($channelId)]);
            break;

        // ====== Feed ======
        case 'feed':
            $limit = min(50, (int)($_GET['limit'] ?? 30));
            json_response(['success' => true, 'feed' => $cm->getMyFeed($userId, $limit)]);
            break;

        default:
            json_response(['success' => false, 'message' => 'unknown_action'], 400);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
