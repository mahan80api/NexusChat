<?php
/**
 * NexusChat - Upload API
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
$upload = new FileUpload();
$user   = new User();

try {
    switch ($action) {

        case 'avatar':
            if (empty($_FILES['avatar'])) {
                throw new Exception('فایلی ارسال نشده');
            }
            $path = $upload->uploadAvatar($_FILES['avatar'], $userId);
            $user->updateProfile($userId, ['avatar' => $path]);
            json_response(['success' => true, 'path' => $path, 'url' => UPLOAD_URL . $path]);
            break;

        case 'chat_file':
            if (empty($_FILES['file'])) {
                throw new Exception('فایلی ارسال نشده');
            }
            $result = $upload->uploadChatFile($_FILES['file'], $userId);
            json_response(['success' => true] + $result);
            break;

        default:
            json_response(['success' => false, 'error' => 'unknown_action'], 400);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 400);
}
