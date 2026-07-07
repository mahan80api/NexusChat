<?php
/**
 * NexusChat - Wallet API
 */
define('NEXUSCHAT_API', true);
require_once __DIR__ . '/../config/config.php';

header('Access-Control-Allow-Origin: ' . APP_URL);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_auth();
$uid = current_user_id();
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$db = Database::getInstance();

$SUPPORTED = ['IRR', 'USD', 'EUR', 'GBP', 'BTC', 'ETH', 'TON', 'USDT'];
$RATES = ['IRR'=>1, 'USD'=>420000, 'EUR'=>460000, 'GBP'=>530000, 'BTC'=>2500000e3, 'ETH'=>110e6, 'TON'=>2500e3, 'USDT'=>420000];

try {
    switch ($action) {
        case 'list':
            $stmt = $db->prepare("SELECT * FROM wallets WHERE user_id = ?");
            $stmt->execute([$uid]);
            $wallets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!$wallets) {
                $ins = $db->prepare("INSERT INTO wallets (user_id, currency, balance, wallet_number, created_at) VALUES (?, ?, 0, ?, NOW())");
                foreach (['IRR', 'USD', 'EUR', 'BTC', 'ETH', 'TON', 'USDT'] as $cur) {
                    $num = 'WAL' . str_pad($uid, 8, '0', STR_PAD_LEFT) . strtoupper(substr(md5($uid.$cur), 0, 6));
                    $ins->execute([$uid, $cur, $num]);
                }
                $stmt->execute([$uid]);
                $wallets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            foreach ($wallets as &$w) $w['balance'] = (float)$w['balance'];
            json_response(['success' => true, 'wallets' => $wallets]);
            break;

        case 'balance':
            $walletId = (int)($_GET['wallet_id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM wallets WHERE id = ? AND user_id = ?");
            $stmt->execute([$walletId, $uid]);
            json_response(['success' => true, 'wallet' => $stmt->fetch(PDO::FETCH_ASSOC)]);
            break;

        case 'topup':
            $walletId = (int)($_POST['wallet_id'] ?? 0);
            $amount = (float)($_POST['amount'] ?? 0);
            if ($amount <= 0) json_response(['success' => false, 'message' => 'invalid_amount'], 400);
            $db->beginTransaction();
            $db->prepare("UPDATE wallets SET balance = balance + ? WHERE id = ? AND user_id = ?")->execute([$amount, $walletId, $uid]);
            $db->prepare("INSERT INTO wallet_transactions (wallet_id, type, amount, note, created_at) VALUES (?, 'topup', ?, 'شارژ', NOW())")
                ->execute([$walletId, $amount]);
            $db->commit();
            json_response(['success' => true]);
            break;

        case 'transfer':
            $currency = $_POST['currency'] ?? '';
            $to = sanitize($_POST['to'] ?? '');
            $amount = (float)($_POST['amount'] ?? 0);
            $note = sanitize($_POST['note'] ?? '');
            if (!in_array($currency, $SUPPORTED)) json_response(['success' => false, 'message' => 'invalid_currency'], 400);
            if ($amount <= 0) json_response(['success' => false, 'message' => 'invalid_amount'], 400);
            $db->beginTransaction();
            $stmt = $db->prepare("SELECT id, balance FROM wallets WHERE user_id = ? AND currency = ? FOR UPDATE");
            $stmt->execute([$uid, $currency]);
            $from = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$from) { $db->rollBack(); json_response(['success' => false, 'message' => 'no_wallet'], 404); }
            if ($from['balance'] < $amount) { $db->rollBack(); json_response(['success' => false, 'message' => 'insufficient_balance'], 400); }

            $stmt = $db->prepare("SELECT id, user_id FROM wallets WHERE wallet_number = ? OR user_id = (SELECT id FROM users WHERE username = ? OR phone = ?)");
            $stmt->execute([$to, $to, $to]);
            $dest = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$dest) { $db->rollBack(); json_response(['success' => false, 'message' => 'recipient_not_found'], 404); }
            if ($dest['id'] == $from['id']) { $db->rollBack(); json_response(['success' => false, 'message' => 'self_transfer'], 400); }

            $db->prepare("UPDATE wallets SET balance = balance - ? WHERE id = ?")->execute([$amount, $from['id']]);
            $db->prepare("UPDATE wallets SET balance = balance + ? WHERE id = ?")->execute([$amount, $dest['id']]);
            $db->prepare("INSERT INTO wallet_transactions (wallet_id, type, amount, counterparty_id, note, created_at) VALUES (?, 'out', ?, ?, ?, NOW())")
                ->execute([$from['id'], $amount, $dest['user_id'], $note]);
            $db->prepare("INSERT INTO wallet_transactions (wallet_id, type, amount, counterparty_id, note, created_at) VALUES (?, 'in', ?, ?, ?, NOW())")
                ->execute([$dest['id'], $amount, $uid, $note]);
            $db->commit();
            json_response(['success' => true]);
            break;

        case 'rate':
            $from = $_GET['from'] ?? 'IRR';
            $to = $_GET['to'] ?? 'USD';
            $amount = (float)($_GET['amount'] ?? 1);
            if (!isset($RATES[$from]) || !isset($RATES[$to])) json_response(['success' => false, 'message' => 'invalid_currency'], 400);
            $result = ($amount * $RATES[$from]) / $RATES[$to];
            json_response(['success' => true, 'result' => $result, 'rate' => $RATES[$from] / $RATES[$to]]);
            break;

        case 'exchange':
            $from = $_POST['from'] ?? '';
            $to = $_POST['to'] ?? '';
            $amount = (float)($_POST['amount'] ?? 0);
            if (!isset($RATES[$from]) || !isset($RATES[$to]) || $amount <= 0) json_response(['success' => false, 'message' => 'invalid'], 400);
            $result = ($amount * $RATES[$from]) / $RATES[$to];
            $fee = $result * 0.005;
            $final = $result - $fee;
            $db->beginTransaction();
            $db->prepare("UPDATE wallets SET balance = balance - ? WHERE user_id = ? AND currency = ?")->execute([$amount, $uid, $from]);
            $db->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ? AND currency = ?")->execute([$final, $uid, $to]);
            $db->prepare("INSERT INTO wallet_exchanges (user_id, from_currency, to_currency, from_amount, to_amount, fee, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())")
                ->execute([$uid, $from, $to, $amount, $final, $fee]);
            $db->commit();
            json_response(['success' => true, 'result' => $final, 'fee' => $fee]);
            break;

        case 'cards':
            $stmt = $db->prepare("SELECT * FROM bank_cards WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->execute([$uid]);
            json_response(['success' => true, 'cards' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'add_card':
            $cardNumber = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
            $cardHolder = sanitize($_POST['card_holder'] ?? '');
            $bankName = sanitize($_POST['bank_name'] ?? '');
            $expiry = sanitize($_POST['expiry'] ?? '');
            if (strlen($cardNumber) !== 16) json_response(['success' => false, 'message' => 'invalid_card'], 400);
            if (!$cardHolder) json_response(['success' => false, 'message' => 'no_holder'], 400);
            $db->prepare("INSERT INTO bank_cards (user_id, card_number, card_holder, bank_name, expiry, created_at) VALUES (?, ?, ?, ?, ?, NOW())")
                ->execute([$uid, $cardNumber, $cardHolder, $bankName, $expiry]);
            json_response(['success' => true, 'card_id' => (int)$db->lastInsertId()]);
            break;

        case 'crypto_send':
            $currency = $_POST['currency'] ?? '';
            $toAddress = sanitize($_POST['to_address'] ?? '');
            $amount = (float)($_POST['amount'] ?? 0);
            if (!in_array($currency, ['BTC','ETH','TON','USDT'])) json_response(['success' => false, 'message' => 'invalid_crypto'], 400);
            $db->beginTransaction();
            $db->prepare("UPDATE wallets SET balance = balance - ? WHERE user_id = ? AND currency = ?")->execute([$amount, $uid, $currency]);
            $db->prepare("INSERT INTO crypto_transactions (user_id, currency, to_address, amount, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())")
                ->execute([$uid, $currency, $toAddress, $amount]);
            $db->commit();
            json_response(['success' => true, 'tx_hash' => bin2hex(random_bytes(16))]);
            break;

        case 'escrow_create':
            $toUser = sanitize($_POST['to_user'] ?? '');
            $amount = (float)($_POST['amount'] ?? 0);
            $desc = sanitize($_POST['description'] ?? '');
            $db->prepare("INSERT INTO escrow_deals (from_user_id, to_user_id, amount, description, status, created_at) VALUES (?, (SELECT id FROM users WHERE username = ?), ?, ?, 'pending', NOW())")
                ->execute([$uid, $toUser, $amount, $desc]);
            json_response(['success' => true, 'deal_id' => (int)$db->lastInsertId()]);
            break;

        case 'escrow_release':
            $dealId = (int)($_POST['deal_id'] ?? 0);
            $db->prepare("UPDATE escrow_deals SET status = 'released', released_at = NOW() WHERE id = ? AND from_user_id = ?")
                ->execute([$dealId, $uid]);
            json_response(['success' => true]);
            break;

        case 'history':
            $walletId = (int)($_GET['wallet_id'] ?? 0);
            $stmt = $db->prepare("SELECT t.*, u.display_name as counterparty_name FROM wallet_transactions t
                LEFT JOIN users u ON u.id = t.counterparty_id
                WHERE t.wallet_id IN (SELECT id FROM wallets WHERE user_id = ?) ORDER BY t.created_at DESC LIMIT 50");
            $stmt->execute([$uid]);
            json_response(['success' => true, 'transactions' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        default:
            json_response(['success' => false, 'message' => 'unknown_action'], 400);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
