<?php
/**
 * NexusChat - Stories API
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
$story  = new Story();
$upload = new FileUpload();

try {
    switch ($action) {

        case 'list':
            $stories = $story->getActiveStories($userId);
            json_response(['success' => true, 'stories' => $stories]);
            break;

        case 'create':
            if (empty($_FILES['media'])) {
                throw new Exception('فایلی ارسال نشده');
            }
            $caption = sanitize($_POST['caption'] ?? '');
            $result = $upload->uploadStory($_FILES['media'], $userId);
            $storyId = $story->create($userId, $result['path'], $result['type'], $caption);
            json_response(['success' => true, 'story_id' => $storyId, 'path' => $result['path'], 'type' => $result['type']]);
            break;

        case 'view':
            $storyId = (int)($_POST['story_id'] ?? 0);
            $story->markViewed($storyId, $userId);
            json_response(['success' => true]);
            break;

        case 'cleanup':
            $count = $story->cleanup();
            json_response(['success' => true, 'cleaned' => $count]);
            break;

        default:
            json_response(['success' => false, 'error' => 'unknown_action'], 400);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 400);
}
