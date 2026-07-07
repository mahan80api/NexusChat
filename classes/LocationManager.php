<?php
/**
 * NexusChat - Location Sharing Manager
 * Live locations, static locations, nearby places
 */
require_once __DIR__ . '/Database.php';

class LocationManager {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Share static location
     */
    public function shareLocation($userId, $chatId, $latitude, $longitude, $accuracy = null, $placeName = null, $expiresAt = null) {
        $stmt = $this->db->prepare("INSERT INTO location_shares
            (user_id, chat_id, latitude, longitude, accuracy, place_name, is_live, expires_at, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $isLive = $expiresAt ? 1 : 0;
        $stmt->execute([$userId, $chatId, $latitude, $longitude, $accuracy, $placeName, $isLive, $expiresAt]);
        return $this->db->lastInsertId();
    }

    /**
     * Update live location
     */
    public function updateLiveLocation($locationId, $latitude, $longitude, $accuracy = null) {
        $stmt = $this->db->prepare("UPDATE location_shares
            SET latitude = ?, longitude = ?, accuracy = ?, updated_at = NOW()
            WHERE id = ?");
        $stmt->execute([$latitude, $longitude, $accuracy, $locationId]);
    }

    /**
     * Stop sharing location
     */
    public function stopSharing($locationId, $userId) {
        $this->db->prepare("UPDATE location_shares SET expires_at = NOW() WHERE id = ? AND user_id = ?")
                 ->execute([$locationId, $userId]);
    }

    /**
     * Get location by id
     */
    public function getLocation($locationId) {
        $stmt = $this->db->prepare("SELECT l.*, u.display_name, u.username, u.avatar
            FROM location_shares l
            JOIN users u ON u.id = l.user_id
            WHERE l.id = ?");
        $stmt->execute([$locationId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get active live locations for a chat
     */
    public function getActiveLiveLocations($chatId) {
        $stmt = $this->db->prepare("SELECT l.*, u.display_name, u.username, u.avatar
            FROM location_shares l
            JOIN users u ON u.id = l.user_id
            WHERE l.chat_id = ? AND l.is_live = 1 AND l.expires_at > NOW()
            ORDER BY l.updated_at DESC");
        $stmt->execute([$chatId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get user's location history
     */
    public function getUserHistory($userId, $limit = 30) {
        $stmt = $this->db->prepare("SELECT l.*, c.name as chat_name
            FROM location_shares l
            LEFT JOIN chats c ON c.id = l.chat_id
            WHERE l.user_id = ? AND l.is_live = 0
            ORDER BY l.created_at DESC LIMIT ?");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Save a place (favorite)
     */
    public function savePlace($userId, $name, $latitude, $longitude, $category = 'other', $address = null) {
        $stmt = $this->db->prepare("INSERT INTO saved_places
            (user_id, name, latitude, longitude, category, address, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$userId, $name, $latitude, $longitude, $category, $address]);
        return $this->db->lastInsertId();
    }

    public function getSavedPlaces($userId) {
        $stmt = $this->db->prepare("SELECT * FROM saved_places WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteSavedPlace($placeId, $userId) {
        $this->db->prepare("DELETE FROM saved_places WHERE id = ? AND user_id = ?")
                 ->execute([$placeId, $userId]);
    }

    /**
     * Find nearby places (using OpenStreetMap Nominatim or custom)
     * Returns mock data based on coordinates if no external API
     */
    public function findNearby($latitude, $longitude, $radiusKm = 1.0, $category = null) {
        // For demo, generate nearby places based on lat/lng
        $places = [];
        $categories = [
            'restaurant' => ['🍽', 'رستوران'],
            'cafe' => ['☕', 'کافه'],
            'shop' => ['🛍', 'فروشگاه'],
            'park' => ['🌳', 'پارک'],
            'hospital' => ['🏥', 'بیمارستان'],
            'gas' => ['⛽', 'پمپ بنزین'],
            'bank' => ['🏦', 'بانک'],
            'hotel' => ['🏨', 'هتل'],
        ];

        // Mock data: generate 8 nearby places
        $names = [
            'restaurant' => ['رستوران سنتی', 'پیتزا فروشی', 'کبابی', 'فست فود'],
            'cafe' => ['کافه لاله', 'کافه گلستان', 'قهوه خانه'],
            'shop' => ['سوپرمارکت', 'فروشگاه پوشاک', 'موبایل فروشی'],
            'park' => ['پارک لاله', 'پارک ملت', 'بوستان'],
            'hospital' => ['بیمارستان', 'درمانگاه', 'مرکز سلامت'],
            'gas' => ['پمپ بنزین', 'جایگاه سوخت'],
            'bank' => ['بانک', 'خودپرداز'],
            'hotel' => ['هتل', 'مهمانپذیر'],
        ];

        if ($category && isset($names[$category])) {
            $catList = [$category];
        } else {
            $catList = array_keys($names);
        }

        foreach ($catList as $cat) {
            if (isset($names[$cat])) {
                foreach ($names[$cat] as $i => $name) {
                    $lat = $latitude + (mt_rand(-100, 100) / 1000) * 0.1;
                    $lng = $longitude + (mt_rand(-100, 100) / 1000) * 0.1;
                    $distance = $this->haversineDistance($latitude, $longitude, $lat, $lng);
                    $places[] = [
                        'name' => $name . ' ' . ($i + 1),
                        'icon' => $categories[$cat][0],
                        'category' => $cat,
                        'category_label' => $categories[$cat][1],
                        'latitude' => $lat,
                        'longitude' => $lng,
                        'distance_m' => round($distance),
                        'distance_label' => $this->formatDistance($distance),
                    ];
                }
            }
        }

        usort($places, fn($a, $b) => $a['distance_m'] <=> $b['distance_m']);
        return array_slice($places, 0, 12);
    }

    /**
     * Reverse geocode (mock or using external API)
     */
    public function reverseGeocode($latitude, $longitude) {
        // For real implementation, use Nominatim:
        // $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat={$latitude}&lon={$longitude}";
        // $data = json_decode(file_get_contents($url), true);
        // return $data['display_name'] ?? null;

        return sprintf('موقعیت (%.4f, %.4f)', $latitude, $longitude);
    }

    /**
     * Calculate distance in meters (Haversine formula)
     */
    private function haversineDistance($lat1, $lng1, $lat2, $lng2) {
        $R = 6371000;
        $phi1 = deg2rad($lat1);
        $phi2 = deg2rad($lat2);
        $dphi = deg2rad($lat2 - $lat1);
        $dlam = deg2rad($lng2 - $lng1);
        $a = sin($dphi/2) * sin($dphi/2) + cos($phi1) * cos($phi2) * sin($dlam/2) * sin($dlam/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $R * $c;
    }

    private function formatDistance($meters) {
        if ($meters < 1000) return round($meters) . ' متر';
        return round($meters / 1000, 1) . ' کیلومتر';
    }

    /**
     * Get location statistics for a user
     */
    public function getUserStats($userId) {
        $stmt = $this->db->prepare("SELECT
            COUNT(*) as total_shared,
            SUM(is_live) as live_count,
            COUNT(DISTINCT DATE(created_at)) as active_days
            FROM location_shares WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
