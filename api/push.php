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
$action = $_POST['action'] ?? '';
$db = Database::getInstance();

try {
    switch ($action) {
        case 'subscribe':
            $token = sanitize($_POST['token'] ?? '');
            $platform = $_POST['platform'] ?? 'web';
            if (!$token) json_response(['success' => false, 'message' => 'no_token'], 400);
            $db->prepare("INSERT INTO push_subscriptions (user_id, token, platform, user_agent, created_at) VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), platform = VALUES(platform), last_seen = NOW()")
                ->execute([$uid, $token, $platform, $_SERVER['HTTP_USER_AGENT'] ?? '']);
            json_response(['success' => true]);
            break;

        case 'unsubscribe':
            $token = sanitize($_POST['token'] ?? '');
            $db->prepare("DELETE FROM push_subscriptions WHERE user_id = ? AND token = ?")->execute([$uid, $token]);
            json_response(['success' => true]);
            break;

        case 'send':
            $to = (int)($_POST['to_user_id'] ?? 0);
            $title = sanitize($_POST['title'] ?? '');
            $body = sanitize($_POST['body'] ?? '');
            $data = $_POST['data'] ?? [];
            if (!$to || !$title) json_response(['success' => false, 'message' => 'missing_fields'], 400);
            $stmt = $db->prepare("SELECT token, platform FROM push_subscriptions WHERE user_id = ? AND last_seen > DATE_SUB(NOW(), INTERVAL 90 DAY)");
            $stmt->execute([$to]);
            $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $sent = 0;
            foreach ($tokens as $t) {
                if (sendFCM($t['token'], $title, $body, $data, $t['platform'])) $sent++;
            }
            json_response(['success' => true, 'sent' => $sent, 'total' => count($tokens)]);
            break;

        default:
            json_response(['success' => false, 'message' => 'unknown_action'], 400);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}

function sendFCM($token, $title, $body, $data, $platform) {
    $serviceAccountPath = __DIR__ . '/../config/firebase-service-account.json';
    if (!is_file($serviceAccountPath)) return false;
    $sa = json_decode(file_get_contents($serviceAccountPath), true);
    $projectId = $sa['project_id'] ?? '';
    if (!$projectId) return false;
    $accessToken = getOrCacheAccessToken($sa);
    if (!$accessToken) return false;
    $payload = ['message' => [
        'token' => $token,
        'notification' => ['title' => $title, 'body' => $body],
        'data' => array_map('strval', (array)$data),
        'webpush' => ['fcm_options' => ['link' => $data['url'] ?? '/']],
    ]];
    $ch = curl_init("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send");
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken, 'Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5,
    ]);
    $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return $code === 200;
}

function getOrCacheAccessToken($sa) {
    $cacheFile = sys_get_temp_dir() . '/fcm_token_' . md5($sa['client_email']);
    if (is_file($cacheFile) && filemtime($cacheFile) > time() - 3000) return file_get_contents($cacheFile);
    $now = time();
    $header = base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $claim = base64UrlEncode(json_encode(['iss' => $sa['client_email'], 'scope' => 'https://www.googleapis.com/auth/firebase.messaging', 'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600]));
    $jwt = $header . '.' . $claim;
    openssl_sign($jwt, $sig, $sa['private_key'], OPENSSL_ALGO_SHA256);
    $jwt .= '.' . base64UrlEncode($sig);
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt]), CURLOPT_RETURNTRANSFER => true]);
    $r = curl_exec($ch); curl_close($ch);
    $d = json_decode($r, true);
    if (!isset($d['access_token'])) return null;
    file_put_contents($cacheFile, $d['access_token']);
    return $d['access_token'];
}

function base64UrlEncode($d) { return rtrim(strtr(base64_encode($d), '+/', '-_'), '='); }
