<?php
define('NEXUSCHAT_API', true);
require_once __DIR__ . '/../config/config.php';

header('Access-Control-Allow-Origin: ' . APP_URL);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_auth();
$uid = current_user_id();

if (!isset($_FILES['file'])) json_response(['success' => false, 'message' => 'no_file'], 400);
$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) json_response(['success' => false, 'message' => 'upload_error'], 400);
if ($file['size'] > MAX_UPLOAD_SIZE) json_response(['success' => false, 'message' => 'file_too_large'], 400);

$type = $_POST['type'] ?? 'file';
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

$allowed = [];
if ($type === 'image') $allowed = ALLOWED_IMAGE_TYPES;
elseif ($type === 'video') $allowed = ALLOWED_VIDEO_TYPES;
elseif ($type === 'audio' || $type === 'voice') $allowed = ALLOWED_AUDIO_TYPES;
else $allowed = array_merge(ALLOWED_IMAGE_TYPES, ALLOWED_VIDEO_TYPES, ALLOWED_AUDIO_TYPES, ['pdf','doc','docx','zip','txt']);

if (!in_array($ext, $allowed)) json_response(['success' => false, 'message' => 'unsupported_format'], 400);

$subdir = in_array($type, ['image','video','audio','voice']) ? $type . 's' : 'files';
$dir = UPLOAD_DIR . $subdir . '/';
if (!is_dir($dir)) mkdir($dir, 0755, true);

$filename = $type . '_' . $uid . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$path = $dir . $filename;

if (!move_uploaded_file($file['tmp_name'], $path)) {
    json_response(['success' => false, 'message' => 'move_failed'], 500);
}

$url = UPLOAD_URL . $subdir . '/' . $filename;
json_response(['success' => true, 'url' => $url, 'filename' => $filename, 'size' => $file['size']]);
