<?php
/**
 * NexusChat - Health Check Endpoint
 */
define('NEXUSCHAT_API', true);
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

$health = [
    'status' => 'ok',
    'version' => APP_VERSION,
    'timestamp' => time(),
    'uptime' => file_exists('/proc/uptime') ? (int)explode(' ', file_get_contents('/proc/uptime'))[0] : null,
];

try {
    $db = Database::getInstance();
    $db->query("SELECT 1");
    $health['db'] = 'connected';
} catch (Exception $e) {
    $health['db'] = 'error';
    $health['status'] = 'degraded';
}

$health['php'] = PHP_VERSION;
$health['extensions'] = [
    'pdo' => extension_loaded('pdo'),
    'pdo_mysql' => extension_loaded('pdo_mysql'),
    'mbstring' => extension_loaded('mbstring'),
    'curl' => extension_loaded('curl'),
    'openssl' => extension_loaded('openssl'),
    'gd' => extension_loaded('gd'),
];

echo json_encode($health, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
