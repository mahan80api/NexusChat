<?php
/**
 * NexusChat - Wallet API
 */
define('NEXUSCHAT_API', true);
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . APP_URL);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_auth();
$action = $_GET['action'] ?? $_POST['action'] ?? 'wallets';
$userId = current_user_id();
$wm = new WalletManager();

try {
    switch ($action) {
        case 'wallets':
            $wallets = $wm->getUserWallets($userId);
            $total = $wm->getTotalBalanceIRR($userId);
            $rates = $wm->getExchangeRates();
            foreach ($wallets as &$w) {
                $w['balance_formatted'] = number_format($w['balance'], 4);
                $w['value_irr'] = $w['balance'] * ($rates[$w['currency']] ?? 1);
            }
            json_response(['success' => true, 'wallets' => $wallets, 'total_balance_irr' => $total, 'rates' => $rates]);
            break;

        case 'balance':
            $currency = $_GET['currency'] ?? 'IRR';
            $balance = $wm->getBalance($userId, $currency);
            json_response(['success' => true, 'currency' => $currency, 'balance' => $balance]);
            break;

        case 'deposit':
            $currency = $_POST['currency'] ?? 'IRR';
            $amount = (float)($_POST['amount'] ?? 0);
            $method = $_POST['method'] ?? 'bank';
            $desc = $_POST['description'] ?? '';
            if ($amount <= 0) throw new Exception('invalid_amount');
            $txId = $wm->deposit($userId, $currency, $amount, $method, $desc);
            json_response(['success' => true, 'tx_id' => $txId, 'new_balance' => $wm->getBalance($userId, $currency)]);
            break;

        case 'withdraw':
            $currency = $_POST['currency'] ?? 'IRR';
            $amount = (float)($_POST['amount'] ?? 0);
            $method = $_POST['method'] ?? 'bank';
            $desc = $_POST['description'] ?? '';
            if ($amount <= 0) throw new Exception('invalid_amount');
            $txId = $wm->withdraw($userId, $currency, $amount, $method, $desc);
            json_response(['success' => true, 'tx_id' => $txId, 'new_balance' => $wm->getBalance($userId, $currency)]);
            break;

        case 'transfer':
            $to = (int)($_POST['to_user_id'] ?? 0);
            $currency = $_POST['currency'] ?? 'IRR';
            $amount = (float)($_POST['amount'] ?? 0);
            $desc = $_POST['description'] ?? '';
            $pin = $_POST['pin'] ?? null;
            $txId = $wm->transfer($userId, $to, $currency, $amount, $desc, $pin);
            json_response(['success' => true, 'tx_id' => $txId]);
            break;

        case 'transfer_by_username':
            $username = $_POST['username'] ?? '';
            $stmt = $GLOBALS['db']->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $target = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$target) throw new Exception('user_not_found');
            $currency = $_POST['currency'] ?? 'IRR';
            $amount = (float)($_POST['amount'] ?? 0);
            $desc = $_POST['description'] ?? '';
            $pin = $_POST['pin'] ?? null;
            $txId = $wm->transfer($userId, $target['id'], $currency, $amount, $desc, $pin);
            json_response(['success' => true, 'tx_id' => $txId]);
            break;

        case 'pay':
            $currency = $_POST['currency'] ?? 'IRR';
            $amount = (float)($_POST['amount'] ?? 0);
            $merchant = $_POST['merchant'] ?? '';
            $desc = $_POST['description'] ?? '';
            $txId = $wm->pay($userId, $currency, $amount, $merchant, $desc);
            json_response(['success' => true, 'tx_id' => $txId]);
            break;

        case 'request':
            $to = (int)($_POST['to_user_id'] ?? 0);
            $currency = $_POST['currency'] ?? 'IRR';
            $amount = (float)($_POST['amount'] ?? 0);
            $desc = $_POST['description'] ?? '';
            $id = $wm->requestPayment($userId, $to, $currency, $amount, $desc);
            json_response(['success' => true, 'request_id' => $id]);
            break;

        case 'approve_request':
            $reqId = (int)($_POST['request_id'] ?? 0);
            $wm->approvePaymentRequest($reqId, $userId);
            json_response(['success' => true]);
            break;

        case 'reject_request':
            $reqId = (int)($_POST['request_id'] ?? 0);
            $wm->rejectPaymentRequest($reqId, $userId);
            json_response(['success' => true]);
            break;

        case 'requests':
            $type = $_GET['type'] ?? 'incoming';
            $list = $wm->getPaymentRequests($userId, $type);
            json_response(['success' => true, 'requests' => $list]);
            break;

        // ====== Escrow ======
        case 'escrow_create':
            $to = (int)($_POST['to_user_id'] ?? 0);
            $currency = $_POST['currency'] ?? 'IRR';
            $amount = (float)($_POST['amount'] ?? 0);
            $desc = $_POST['description'] ?? '';
            $id = $wm->createEscrow($userId, $to, $currency, $amount, $desc);
            json_response(['success' => true, 'escrow_id' => $id]);
            break;

        case 'escrow_release':
            $id = (int)($_POST['escrow_id'] ?? 0);
            $wm->releaseEscrow($id, $userId);
            json_response(['success' => true]);
            break;

        case 'escrow_refund':
            $id = (int)($_POST['escrow_id'] ?? 0);
            $wm->refundEscrow($id, $userId);
            json_response(['success' => true]);
            break;

        case 'escrow_list':
            json_response(['success' => true, 'list' => $wm->getEscrowTransactions($userId)]);
            break;

        // ====== Cards ======
        case 'cards':
            json_response(['success' => true, 'cards' => $wm->getCards($userId)]);
            break;

        case 'add_card':
            $cn = preg_replace('/\s+/', '', $_POST['card_number'] ?? '');
            $holder = $_POST['card_holder'] ?? '';
            $expiry = $_POST['expiry'] ?? '';
            $nick = $_POST['nickname'] ?? '';
            if (strlen($cn) < 13) throw new Exception('invalid_card_number');
            $id = $wm->addCard($userId, $cn, $holder, $expiry, $nick);
            json_response(['success' => true, 'card_id' => $id]);
            break;

        case 'delete_card':
            $id = (int)($_POST['card_id'] ?? 0);
            $wm->deleteCard($id, $userId);
            json_response(['success' => true]);
            break;

        case 'set_default_card':
            $id = (int)($_POST['card_id'] ?? 0);
            $wm->setDefaultCard($id, $userId);
            json_response(['success' => true]);
            break;

        // ====== Crypto ======
        case 'generate_address':
            $currency = $_POST['currency'] ?? 'BTC';
            $addr = $wm->generateCryptoAddress($userId, $currency);
            json_response(['success' => true, 'address' => $addr]);
            break;

        case 'addresses':
            json_response(['success' => true, 'addresses' => $wm->getCryptoAddresses($userId)]);
            break;

        case 'exchange':
            $from = $_POST['from'] ?? 'IRR';
            $to = $_POST['to'] ?? 'USD';
            $amount = (float)($_POST['amount'] ?? 0);
            $r = $wm->exchange($userId, $from, $to, $amount);
            json_response(['success' => true] + $r);
            break;

        case 'rates':
            json_response(['success' => true, 'rates' => $wm->getExchangeRates()]);
            break;

        case 'convert':
            $amount = (float)($_GET['amount'] ?? 1);
            $from = $_GET['from'] ?? 'USD';
            $to = $_GET['to'] ?? 'IRR';
            $r = $wm->convert($amount, $from, $to);
            json_response(['success' => true, 'converted' => $r]);
            break;

        // ====== PIN ======
        case 'set_pin':
            $pin = $_POST['pin'] ?? '';
            if (strlen($pin) < 4) throw new Exception('pin_too_short');
            $wm->setPin($userId, $pin);
            json_response(['success' => true]);
            break;

        // ====== Transactions ======
        case 'transactions':
            $limit = (int)($_GET['limit'] ?? 50);
            $type = $_GET['type'] ?? null;
            $currency = $_GET['currency'] ?? null;
            $list = $wm->getTransactions($userId, $limit, $type, $currency);
            json_response(['success' => true, 'transactions' => $list]);
            break;

        case 'stats':
            $stats = $wm->getStats($userId);
            $stats['total_balance_irr'] = $wm->getTotalBalanceIRR($userId);
            json_response(['success' => true, 'stats' => $stats]);
            break;

        default:
            json_response(['success' => false, 'message' => 'unknown_action'], 400);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
