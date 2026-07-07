<?php
/**
 * NexusChat - Statistics API
 */
define('NEXUSCHAT_API', true);
require_once __DIR__ . '/../config/config.php';
header('Access-Control-Allow-Origin: ' . APP_URL);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_auth();
$action = $_GET['action'] ?? $_POST['action'] ?? 'overall';
$userId = current_user_id();
$period = $_GET['period'] ?? 'all';
$stats  = new StatsManager();

try {
    switch ($action) {
        case 'overall':
            json_response(['success' => true, 'overall' => $stats->getOverall($userId, $period), 'comparison' => $stats->getComparison($userId)]);
            break;
        case 'hourly':
            json_response(['success' => true, 'hours' => $stats->getHourlyActivity($userId, $period)]);
            break;
        case 'weekday':
            json_response(['success' => true, 'days' => $stats->getWeekdayActivity($userId, $period)]);
            break;
        case 'daily':
            $days = (int)($_GET['days'] ?? 30);
            json_response(['success' => true, 'daily' => $stats->getDailyActivity($userId, $days)]);
            break;
        case 'heatmap':
            json_response(['success' => true, 'heatmap' => $stats->getYearHeatmap($userId)]);
            break;
        case 'top_chats':
            $limit = (int)($_GET['limit'] ?? 10);
            json_response(['success' => true, 'chats' => $stats->getTopChats($userId, $limit, $period)]);
            break;
        case 'top_people':
            $limit = (int)($_GET['limit'] ?? 10);
            json_response(['success' => true, 'people' => $stats->getTopPeople($userId, $limit, $period)]);
            break;
        case 'types':
            json_response(['success' => true, 'types' => $stats->getTypeDistribution($userId, $period)]);
            break;
        case 'peaks':
            json_response(['success' => true, 'peaks' => $stats->getPeakHours($userId, $period)]);
            break;
        case 'records':
            json_response(['success' => true, 'records' => $stats->getRecords($userId)]);
            break;
        case 'words':
            $limit = (int)($_GET['limit'] ?? 20);
            json_response(['success' => true, 'words' => $stats->getTopWords($userId, $limit, $period)]);
            break;
        case 'emoji':
            $limit = (int)($_GET['limit'] ?? 10);
            json_response(['success' => true, 'emoji' => $stats->getTopEmoji($userId, $limit, $period)]);
            break;
        case 'all':
            json_response([
                'success'     => true,
                'overall'     => $stats->getOverall($userId, $period),
                'comparison'  => $stats->getComparison($userId),
                'hourly'      => $stats->getHourlyActivity($userId, $period),
                'weekday'     => $stats->getWeekdayActivity($userId, $period),
                'daily'       => $stats->getDailyActivity($userId, 30),
                'heatmap'     => $stats->getYearHeatmap($userId),
                'top_chats'   => $stats->getTopChats($userId, 10, $period),
                'top_people'  => $stats->getTopPeople($userId, 10, $period),
                'types'       => $stats->getTypeDistribution($userId, $period),
                'peaks'       => $stats->getPeakHours($userId, $period),
                'records'     => $stats->getRecords($userId),
                'words'       => $stats->getTopWords($userId, 20, $period),
                'emoji'       => $stats->getTopEmoji($userId, 10, $period),
            ]);
            break;
        case 'export_csv':
            $csv = $stats->exportCSV($userId, $period);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="nexuschat_stats_' . date('Y-m-d') . '.csv"');
            echo "\xEF\xBB\xBF" . $csv; // UTF-8 BOM
            exit;
        default:
            json_response(['success' => false, 'message' => 'unknown_action'], 400);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
