<?php
/**
 * NexusChat - Themes API
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
$tm = new ThemeManager();

try {
    switch ($action) {
        case 'presets':
            json_response(['success' => true, 'presets' => $tm->getPresets(), 'categories' => $tm->getCategories()]);
            break;

        case 'list':
            json_response(['success' => true, 'themes' => $tm->getUserThemes($userId)]);
            break;

        case 'active':
            $theme = $tm->getActiveUserTheme($userId);
            if (!isset($theme['is_preset']) && !empty($theme['id'])) {
                $theme['css'] = $tm->generateCSS($theme);
            }
            json_response(['success' => true, 'theme' => $theme]);
            break;

        case 'info':
            $themeId = (int)($_GET['theme_id'] ?? 0);
            $theme = $tm->getThemeById($themeId);
            if ($theme) {
                $theme['css'] = $tm->generateCSS($theme);
                $theme['user_rating'] = $tm->getUserRating($themeId, $userId);
            }
            json_response(['success' => true, 'theme' => $theme]);
            break;

        case 'create':
            $data = [];
            foreach (['name', 'description', 'primary', 'secondary', 'background', 'surface', 'text', 'text_dim', 'accent', 'border', 'gradient', 'background_image', 'font_family', 'border_radius', 'animation_speed', 'is_public', 'is_dark', 'category'] as $f) {
                if (isset($_POST[$f])) $data[$f] = $_POST[$f];
            }
            if (empty($data['name'])) $data['name'] = 'تم سفارشی';
            $id = $tm->createTheme($userId, $data);
            json_response(['success' => true, 'theme_id' => $id]);
            break;

        case 'update':
            $themeId = (int)($_POST['theme_id'] ?? 0);
            $data = [];
            foreach (['name', 'description', 'primary', 'secondary', 'background', 'surface', 'text', 'text_dim', 'accent', 'border', 'gradient', 'background_image', 'font_family', 'border_radius', 'animation_speed', 'is_public', 'is_dark', 'category'] as $f) {
                if (isset($_POST[$f])) $data[$f] = $_POST[$f];
            }
            $tm->updateTheme($themeId, $userId, $data);
            json_response(['success' => true]);
            break;

        case 'delete':
            $themeId = (int)($_POST['theme_id'] ?? 0);
            $tm->deleteTheme($themeId, $userId);
            json_response(['success' => true]);
            break;

        case 'duplicate':
            $themeId = (int)($_POST['theme_id'] ?? 0);
            $newName = $_POST['new_name'] ?? '';
            $newId = $tm->duplicateTheme($themeId, $userId, $newName);
            json_response(['success' => true, 'theme_id' => $newId]);
            break;

        case 'apply':
            $themeId = $_POST['theme_id'] ?? null;
            if ($themeId === 'null' || $themeId === '') $themeId = null;
            $r = $tm->setActiveTheme($userId, $themeId);
            if (!empty($r['theme_id'])) $tm->incrementUseCount($r['theme_id']);
            json_response(['success' => true, 'result' => $r]);
            break;

        // ====== Marketplace ======
        case 'marketplace':
            $cat = $_GET['category'] ?? null;
            $sort = $_GET['sort'] ?? 'popular';
            $themes = $tm->browseMarketplace($cat, $sort);
            json_response(['success' => true, 'themes' => $themes, 'categories' => $tm->getCategories()]);
            break;

        case 'rate':
            $themeId = (int)($_POST['theme_id'] ?? 0);
            $rating = (int)($_POST['rating'] ?? 0);
            if ($rating > 0) $tm->rateTheme($themeId, $userId, $rating);
            else $tm->unrateTheme($themeId, $userId);
            json_response(['success' => true]);
            break;

        // ====== Import/Export ======
        case 'export':
            $themeId = (int)($_GET['theme_id'] ?? 0);
            $theme = $tm->getThemeById($themeId);
            if (!$theme) throw new Exception('not_found');
            if ($theme['user_id'] != $userId) throw new Exception('not_authorized');
            $json = $tm->exportTheme($theme);
            header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $theme['name']) . '.json"');
            header('Content-Type: application/json; charset=utf-8');
            echo $json;
            exit;

        case 'import':
            $file = $_FILES['file'] ?? null;
            if (!$file || $file['error'] !== UPLOAD_ERR_OK) throw new Exception('no_file');
            $json = file_get_contents($file['tmp_name']);
            $id = $tm->importTheme($userId, $json);
            json_response(['success' => true, 'theme_id' => $id]);
            break;

        case 'css':
            $themeId = (int)($_GET['theme_id'] ?? 0);
            $theme = $tm->getThemeById($themeId);
            if (!$theme) throw new Exception('not_found');
            header('Content-Type: text/css; charset=utf-8');
            echo $tm->generateCSS($theme);
            exit;

        case 'stats':
            json_response(['success' => true, 'stats' => $tm->getStats($userId)]);
            break;

        default:
            json_response(['success' => false, 'message' => 'unknown_action'], 400);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
