<?php
/**
 * NexusChat - Link Preview API
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
$lp = new LinkPreview();
$userId = current_user_id();

try {
    switch ($action) {
        case 'get':
            $url = $_GET['url'] ?? '';
            if (!$url) throw new Exception('URL الزامی است');
            $preview = $lp->getPreview($url);
            json_response(array_merge(['success' => true], $preview));
            break;

        case 'extract':
            $text = $_POST['text'] ?? $_GET['text'] ?? '';
            $urls = $lp->extractUrls($text);
            $previews = [];
            foreach ($urls as $u) {
                $p = $lp->getPreview($u);
                $previews[] = array_merge(['original_url' => $u], $p);
            }
            json_response(['success' => true, 'urls' => $urls, 'previews' => $previews]);
            break;

        case 'click':
            $previewId = (int)($_POST['preview_id'] ?? 0);
            $lp->trackClick($previewId, $userId);
            json_response(['success' => true]);
            break;

        default:
            json_response(['success' => false, 'error' => 'unknown_action'], 400);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 400);
}
