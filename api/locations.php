<?php
/**
 * NexusChat - Locations API
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
$lm = new LocationManager();

try {
    switch ($action) {
        case 'share':
            $chatId = (int)($_POST['chat_id'] ?? 0);
            $lat = (float)$_POST['latitude'];
            $lng = (float)$_POST['longitude'];
            $accuracy = isset($_POST['accuracy']) ? (float)$_POST['accuracy'] : null;
            $placeName = $_POST['place_name'] ?? null;
            $expiresAt = !empty($_POST['live_duration'])
                ? date('Y-m-d H:i:s', time() + (int)$_POST['live_duration'] * 60)
                : null;
            $id = $lm->shareLocation($userId, $chatId, $lat, $lng, $accuracy, $placeName, $expiresAt);
            json_response(['success' => true, 'location_id' => $id, 'expires_at' => $expiresAt]);
            break;

        case 'update_live':
            $locationId = (int)($_POST['location_id'] ?? 0);
            $lat = (float)$_POST['latitude'];
            $lng = (float)$_POST['longitude'];
            $accuracy = isset($_POST['accuracy']) ? (float)$_POST['accuracy'] : null;
            $lm->updateLiveLocation($locationId, $lat, $lng, $accuracy);
            json_response(['success' => true]);
            break;

        case 'stop':
            $locationId = (int)($_POST['location_id'] ?? 0);
            $lm->stopSharing($locationId, $userId);
            json_response(['success' => true]);
            break;

        case 'active_in_chat':
            $chatId = (int)($_GET['chat_id'] ?? 0);
            $locations = $lm->getActiveLiveLocations($chatId);
            json_response(['success' => true, 'locations' => $locations]);
            break;

        case 'history':
            $limit = min(100, (int)($_GET['limit'] ?? 30));
            json_response(['success' => true, 'history' => $lm->getUserHistory($userId, $limit)]);
            break;

        case 'nearby':
            $lat = (float)$_GET['lat'];
            $lng = (float)$_GET['lng'];
            $radius = (float)($_GET['radius'] ?? 1.0);
            $category = $_GET['category'] ?? null;
            $places = $lm->findNearby($lat, $lng, $radius, $category);
            json_response(['success' => true, 'places' => $places]);
            break;

        case 'reverse_geocode':
            $lat = (float)$_GET['lat'];
            $lng = (float)$_GET['lng'];
            $address = $lm->reverseGeocode($lat, $lng);
            json_response(['success' => true, 'address' => $address]);
            break;

        case 'save_place':
            $name = $_POST['name'] ?? '';
            $lat = (float)$_POST['latitude'];
            $lng = (float)$_POST['longitude'];
            $category = $_POST['category'] ?? 'other';
            $address = $_POST['address'] ?? null;
            $id = $lm->savePlace($userId, $name, $lat, $lng, $category, $address);
            json_response(['success' => true, 'place_id' => $id]);
            break;

        case 'saved_places':
            json_response(['success' => true, 'places' => $lm->getSavedPlaces($userId)]);
            break;

        case 'delete_place':
            $placeId = (int)($_POST['place_id'] ?? 0);
            $lm->deleteSavedPlace($placeId, $userId);
            json_response(['success' => true]);
            break;

        case 'stats':
            json_response(['success' => true, 'stats' => $lm->getUserStats($userId)]);
            break;

        default:
            json_response(['success' => false, 'message' => 'unknown_action'], 400);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
