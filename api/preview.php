<?php
define('NEXUSCHAT_API', true);
require_once __DIR__ . '/../config/config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$url = $_GET['url'] ?? '';
if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
    json_response(['success' => false, 'message' => 'invalid_url'], 400);
}
$host = parse_url($url, PHP_URL_HOST);
if (!$host) json_response(['success' => false, 'message' => 'invalid_url'], 400);

$cacheKey = md5($url);
$cacheFile = sys_get_temp_dir() . '/preview_' . $cacheKey;
if (is_file($cacheFile) && filemtime($cacheFile) > time() - 3600) {
    json_response(['success' => true] + json_decode(file_get_contents($cacheFile), true));
}

$ctx = stream_context_create(['http' => ['timeout' => 5, 'header' => "User-Agent: Mozilla/5.0 NexusChat\r\n"]]);
$html = @file_get_contents($url, false, $ctx);
if (!$html) json_response(['success' => false, 'message' => 'fetch_failed'], 502);

function getMetaLocal($html, $name, $attr = 'property') {
    if (preg_match('/<meta[^>]+' . $attr . '=["\']' . preg_quote($name, '/') . '["\'][^>]+content=["\']([^"\']+)/i', $html, $m)) {
        return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    if (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+' . $attr . '=["\']' . preg_quote($name, '/') . '["\']/i', $html, $m)) {
        return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return null;
}

$title = getMetaLocal($html, 'og:title') ?: getMetaLocal($html, 'twitter:title', 'name') ?: getMetaLocal($html, 'title', 'name');
$desc = getMetaLocal($html, 'og:description') ?: getMetaLocal($html, 'twitter:description', 'name') ?: getMetaLocal($html, 'description', 'name');
$image = getMetaLocal($html, 'og:image') ?: getMetaLocal($html, 'twitter:image', 'name');
$siteName = getMetaLocal($html, 'og:site_name');
$type = getMetaLocal($html, 'og:type') ?: 'website';
$video = getMetaLocal($html, 'og:video') ?: getMetaLocal($html, 'og:video:url');

$ytId = null;
if (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/)|youtu\.be/)([\w-]{11})~', $url, $m)) {
    $ytId = $m[1];
    $title = $title ?: 'YouTube Video';
    $image = $image ?: "https://i.ytimg.com/vi/{$ytId}/hqdefault.jpg";
}

$oembedUrl = null;
if (strpos($host, 'youtube.com') !== false || strpos($host, 'youtu.be') !== false) {
    $oembedUrl = 'https://www.youtube.com/oembed?url=' . urlencode($url) . '&format=json';
} elseif (strpos($host, 'vimeo.com') !== false) {
    $oembedUrl = 'https://vimeo.com/api/oembed.json?url=' . urlencode($url);
}
if ($oembedUrl) {
    $oembedJson = @file_get_contents($oembedUrl, false, $ctx);
    if ($oembedJson) {
        $o = json_decode($oembedJson, true);
        if ($o) {
            $title = $o['title'] ?? $title;
            $image = $o['thumbnail_url'] ?? $image;
            $desc = $desc ?: ($o['author_name'] ?? '');
        }
    }
}

$data = [
    'url' => $url, 'host' => $host, 'title' => $title, 'description' => $desc,
    'image' => $image, 'site_name' => $siteName, 'type' => $type,
    'video' => $video, 'youtube_id' => $ytId,
];
file_put_contents($cacheFile, json_encode($data));
json_response(['success' => true] + $data);
