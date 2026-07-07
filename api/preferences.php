<?php
/**
 * NexusChat - Preferences & DND API
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
$pref = new Preference();

try {
    switch ($action) {
        case 'get':
            json_response(['success' => true, 'preferences' => $pref->get($userId)]);
            break;

        case 'update':
            $data = $_POST;
            $pref->update($userId, $data);
            json_response(['success' => true, 'preferences' => $pref->get($userId)]);
            break;

        case 'dnd_enable':
            $duration = (int)($_POST['duration'] ?? 0);
            $until = $duration > 0 ? date('Y-m-d H:i:s', time() + $duration) : null;
            $data = ['dnd_enabled' => 1, 'dnd_until' => $until];
            if (isset($_POST['allow_mentions']))     $data['dnd_allow_mentions']     = (bool)$_POST['allow_mentions'];
            if (isset($_POST['allow_calls']))         $data['dnd_allow_calls']         = (bool)$_POST['allow_calls'];
            if (isset($_POST['allow_messages_from'])) $data['dnd_allow_messages_from'] = json_decode($_POST['allow_messages_from'], true) ?: [];
            $pref->update($userId, $data);
            json_response(['success' => true, 'dnd_until' => $until, 'message' => 'DND فعال شد 🔕']);
            break;

        case 'dnd_disable':
            $pref->update($userId, ['dnd_enabled' => 0, 'dnd_until' => null]);
            json_response(['success' => true, 'message' => 'DND غیرفعال شد']);
            break;

        case 'dnd_status':
            $p = $pref->get($userId);
            $active = $pref->isDndActive($userId);
            $remaining = null;
            if ($active && $p['dnd_until']) {
                $remaining = max(0, strtotime($p['dnd_until']) - time());
            }
            json_response([
                'success'   => true,
                'enabled'   => $p['dnd_enabled'] == 1,
                'active'    => $active,
                'until'     => $p['dnd_until'],
                'remaining' => $remaining,
            ]);
            break;

        case 'mute_chat':
            $chatId   = (int)$_POST['chat_id'];
            $duration = (int)($_POST['duration'] ?? 0);
            $pref->muteChat($userId, $chatId, $duration ?: null);
            json_response(['success' => true, 'message' => 'چت بی‌صدا شد 🔇']);
            break;

        case 'unmute_chat':
            $chatId = (int)$_POST['chat_id'];
            $pref->unmuteChat($userId, $chatId);
            json_response(['success' => true, 'message' => 'بی‌صدا برداشته شد']);
            break;

        case 'muted_chats':
            json_response(['success' => true, 'chats' => $pref->getMutedChats($userId)]);
            break;

        case 'notification_stats':
            $days = (int)($_GET['days'] ?? 7);
            json_response(['success' => true, 'stats' => $pref->getNotificationStats($userId, $days)]);
            break;

        case 'add_allowlist_user':
            $target = (int)$_POST['user_id'];
            $p = $pref->get($userId);
            $list = $p['dnd_allow_messages_from'] ?? [];
            if (!in_array($target, $list, true)) $list[] = $target;
            $pref->update($userId, ['dnd_allow_messages_from' => $list]);
            json_response(['success' => true, 'allowlist' => $list]);
            break;

        case 'remove_allowlist_user':
            $target = (int)$_POST['user_id'];
            $p = $pref->get($userId);
            $list = array_values(array_filter($p['dnd_allow_messages_from'] ?? [], fn($x) => $x != $target));
            $pref->update($userId, ['dnd_allow_messages_from' => $list]);
            json_response(['success' => true, 'allowlist' => $list]);
            break;

        case 'log_notification':
            $pref->logNotification(
                $userId,
                (int)($_POST['chat_id'] ?? 0),
                (int)($_POST['message_id'] ?? 0),
                $_POST['type'] ?? 'message',
                (bool)($_POST['delivered'] ?? true),
                $_POST['silenced_reason'] ?? null
            );
            json_response(['success' => true]);
            break;

        default:
            json_response(['success' => false, 'error' => 'unknown_action'], 400);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 400);
}
