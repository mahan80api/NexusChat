<?php
/**
 * NexusChat - Wallet Manager
 * Digital wallet: multi-currency, transfers, escrow, transactions
 */
require_once __DIR__ . '/Database.php';

class WalletManager {
    private $db;
    public $currencies = ['IRR', 'USD', 'EUR', 'BTC', 'ETH', 'TON', 'GOLD'];

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getOrCreateWallet($userId, $currency = 'IRR') {
        $stmt = $this->db->prepare("SELECT * FROM wallets WHERE user_id = ? AND currency = ?");
        $stmt->execute([$userId, $currency]);
        $w = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$w) {
            $this->db->prepare("INSERT INTO wallets (user_id, currency, balance) VALUES (?, ?, 0)")
                ->execute([$userId, $currency]);
            return $this->getOrCreateWallet($userId, $currency);
        }
        return $w;
    }

    public function getUserWallets($userId) {
        $stmt = $this->db->prepare("SELECT * FROM wallets WHERE user_id = ? ORDER BY currency");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getBalance($userId, $currency) {
        $w = $this->getOrCreateWallet($userId, $currency);
        return (float)$w['balance'];
    }

    public function getTotalBalanceIRR($userId) {
        // Simple aggregator — for real production use live rates
        $rates = $this->getExchangeRates();
        $stmt = $this->db->prepare("SELECT currency, balance FROM wallets WHERE user_id = ?");
        $stmt->execute([$userId]);
        $total = 0;
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $total += (float)$r['balance'] * (float)($rates[$r['currency']] ?? 1);
        }
        return $total;
    }

    public function deposit($userId, $currency, $amount, $method = 'bank', $description = '') {
        $this->getOrCreateWallet($userId, $currency);
        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ? AND currency = ?")
                ->execute([$amount, $userId, $currency]);
            $txId = $this->recordTransaction($userId, null, $currency, $amount, 'deposit', $method, $description);
            $this->db->commit();
            return $txId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function withdraw($userId, $currency, $amount, $method = 'bank', $description = '') {
        $balance = $this->getBalance($userId, $currency);
        if ($balance < $amount) throw new Exception('insufficient_balance');
        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE wallets SET balance = balance - ? WHERE user_id = ? AND currency = ?")
                ->execute([$amount, $userId, $currency]);
            $txId = $this->recordTransaction($userId, null, $currency, -$amount, 'withdraw', $method, $description);
            $this->db->commit();
            return $txId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function transfer($fromUserId, $toUserId, $currency, $amount, $description = '', $pin = null) {
        if ($fromUserId == $toUserId) throw new Exception('cannot_transfer_self');
        if ($amount <= 0) throw new Exception('invalid_amount');
        $balance = $this->getBalance($fromUserId, $currency);
        if ($balance < $amount) throw new Exception('insufficient_balance');
        if ($pin && !$this->verifyPin($fromUserId, $pin)) throw new Exception('invalid_pin');
        $this->db->beginTransaction();
        try {
            $this->getOrCreateWallet($toUserId, $currency);
            $this->db->prepare("UPDATE wallets SET balance = balance - ? WHERE user_id = ? AND currency = ?")
                ->execute([$amount, $fromUserId, $currency]);
            $this->db->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ? AND currency = ?")
                ->execute([$amount, $toUserId, $currency]);
            $txId = $this->recordTransaction($fromUserId, $toUserId, $currency, -$amount, 'transfer', 'internal', $description);
            $this->db->commit();
            $this->notifyTransfer($toUserId, $fromUserId, $currency, $amount);
            return $txId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function pay($userId, $currency, $amount, $merchant, $description = '') {
        $balance = $this->getBalance($userId, $currency);
        if ($balance < $amount) throw new Exception('insufficient_balance');
        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE wallets SET balance = balance - ? WHERE user_id = ? AND currency = ?")
                ->execute([$amount, $userId, $currency]);
            $txId = $this->recordTransaction($userId, null, $currency, -$amount, 'payment', $merchant, $description);
            $this->db->commit();
            return $txId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function requestPayment($fromUserId, $toUserId, $currency, $amount, $description = '') {
        $stmt = $this->db->prepare("INSERT INTO payment_requests (from_user_id, to_user_id, currency, amount, description, status, created_at)
            VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
        $stmt->execute([$fromUserId, $toUserId, $currency, $amount, $description]);
        $id = $this->db->lastInsertId();
        $this->notifyPaymentRequest($toUserId, $fromUserId, $currency, $amount, $id);
        return $id;
    }

    public function approvePaymentRequest($requestId, $userId) {
        $stmt = $this->db->prepare("SELECT * FROM payment_requests WHERE id = ? AND to_user_id = ? AND status = 'pending'");
        $stmt->execute([$requestId, $userId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$r) throw new Exception('not_found');
        $this->transfer($userId, $r['from_user_id'], $r['currency'], $r['amount'], 'پرداخت درخواست #' . $requestId);
        $this->db->prepare("UPDATE payment_requests SET status = 'paid', paid_at = NOW() WHERE id = ?")
            ->execute([$requestId]);
    }

    public function rejectPaymentRequest($requestId, $userId) {
        $this->db->prepare("UPDATE payment_requests SET status = 'rejected', rejected_at = NOW() WHERE id = ? AND to_user_id = ?")
            ->execute([$requestId, $userId]);
    }

    public function getPaymentRequests($userId, $type = 'incoming') {
        $field = $type === 'incoming' ? 'to_user_id' : 'from_user_id';
        $stmt = $this->db->prepare("SELECT pr.*, u.display_name, u.username, u.avatar
            FROM payment_requests pr
            JOIN users u ON u.id = pr." . ($type === 'incoming' ? 'from_user_id' : 'to_user_id') . "
            WHERE pr.$field = ? ORDER BY pr.created_at DESC LIMIT 50");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ====== Escrow ======
    public function createEscrow($fromUserId, $toUserId, $currency, $amount, $description = '') {
        $balance = $this->getBalance($fromUserId, $currency);
        if ($balance < $amount) throw new Exception('insufficient_balance');
        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE wallets SET balance = balance - ? WHERE user_id = ? AND currency = ?")
                ->execute([$amount, $fromUserId, $currency]);
            $stmt = $this->db->prepare("INSERT INTO escrow_transactions (from_user_id, to_user_id, currency, amount, description, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'holding', NOW())");
            $stmt->execute([$fromUserId, $toUserId, $currency, $amount, $description]);
            $id = $this->db->lastInsertId();
            $this->db->commit();
            return $id;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function releaseEscrow($escrowId, $userId) {
        $stmt = $this->db->prepare("SELECT * FROM escrow_transactions WHERE id = ? AND status = 'holding'");
        $stmt->execute([$escrowId]);
        $e = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$e) throw new Exception('not_found');
        if ($e['from_user_id'] != $userId && $e['to_user_id'] != $userId) throw new Exception('not_authorized');
        $this->db->beginTransaction();
        try {
            $this->getOrCreateWallet($e['to_user_id'], $e['currency']);
            $this->db->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ? AND currency = ?")
                ->execute([$e['amount'], $e['to_user_id'], $e['currency']]);
            $this->db->prepare("UPDATE escrow_transactions SET status = 'released', released_at = NOW() WHERE id = ?")
                ->execute([$escrowId]);
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function refundEscrow($escrowId, $userId) {
        $stmt = $this->db->prepare("SELECT * FROM escrow_transactions WHERE id = ? AND status = 'holding'");
        $stmt->execute([$escrowId]);
        $e = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$e) throw new Exception('not_found');
        if ($e['from_user_id'] != $userId && $e['to_user_id'] != $userId) throw new Exception('not_authorized');
        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ? AND currency = ?")
                ->execute([$e['amount'], $e['from_user_id'], $e['currency']]);
            $this->db->prepare("UPDATE escrow_transactions SET status = 'refunded', refunded_at = NOW() WHERE id = ?")
                ->execute([$escrowId]);
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getEscrowTransactions($userId) {
        $stmt = $this->db->prepare("SELECT e.*, uf.display_name as from_name, ut.display_name as to_name
            FROM escrow_transactions e
            JOIN users uf ON uf.id = e.from_user_id
            JOIN users ut ON ut.id = e.to_user_id
            WHERE e.from_user_id = ? OR e.to_user_id = ?
            ORDER BY e.created_at DESC LIMIT 50");
        $stmt->execute([$userId, $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ====== Cards ======
    public function addCard($userId, $cardNumber, $cardHolder, $expiry, $nickname = '') {
        $last4 = substr(preg_replace('/\D/', '', $cardNumber), -4);
        $token = bin2hex(random_bytes(16));
        $stmt = $this->db->prepare("INSERT INTO wallet_cards (user_id, card_token, card_last4, card_holder, card_expiry, card_nickname, card_type, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$userId, $token, $last4, $cardHolder, $expiry, $nickname, $this->detectCardType($cardNumber)]);
        return $this->db->lastInsertId();
    }

    public function getCards($userId) {
        $stmt = $this->db->prepare("SELECT * FROM wallet_cards WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteCard($cardId, $userId) {
        $this->db->prepare("DELETE FROM wallet_cards WHERE id = ? AND user_id = ?")->execute([$cardId, $userId]);
    }

    public function setDefaultCard($cardId, $userId) {
        $this->db->prepare("UPDATE wallet_cards SET is_default = 0 WHERE user_id = ?")->execute([$userId]);
        $this->db->prepare("UPDATE wallet_cards SET is_default = 1 WHERE id = ? AND user_id = ?")->execute([$cardId, $userId]);
    }

    private function detectCardType($number) {
        $n = preg_replace('/\D/', '', $number);
        if (preg_match('/^4/', $n)) return 'visa';
        if (preg_match('/^5[1-5]/', $n)) return 'mastercard';
        if (preg_match('/^6/', $n)) return 'amex';
        if (preg_match('/^603799/', $n)) return 'meli';
        if (preg_match('/^589210/', $n)) return 'sepah';
        return 'unknown';
    }

    // ====== Crypto (TON-like) ======
    public function generateCryptoAddress($userId, $currency) {
        if (!in_array($currency, ['BTC', 'ETH', 'TON'])) throw new Exception('not_a_crypto_currency');
        $addr = $this->generateAddressForCurrency($currency);
        $stmt = $this->db->prepare("INSERT INTO crypto_addresses (user_id, currency, address, label, created_at)
            VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$userId, $currency, $addr, 'کیف پول ' . $currency]);
        return $addr;
    }

    private function generateAddressForCurrency($currency) {
        $chars = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $len = $currency === 'BTC' ? 34 : ($currency === 'ETH' ? 42 : 48);
        $addr = $currency === 'ETH' ? '0x' : ($currency === 'TON' ? 'UQ' : '1');
        for ($i = strlen($addr); $i < $len; $i++) $addr .= $chars[random_int(0, strlen($chars) - 1)];
        return $addr;
    }

    public function getCryptoAddresses($userId) {
        $stmt = $this->db->prepare("SELECT * FROM crypto_addresses WHERE user_id = ? ORDER BY currency");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getExchangeRates() {
        return [
            'IRR' => 1,
            'USD' => 42000,
            'EUR' => 45500,
            'BTC' => 42000 * 60000,
            'ETH' => 42000 * 3500,
            'TON' => 42000 * 2.5,
            'GOLD' => 42000 * 0.025,
        ];
    }

    public function convert($amount, $from, $to) {
        $rates = $this->getExchangeRates();
        if (!isset($rates[$from]) || !isset($rates[$to])) throw new Exception('unsupported_currency');
        return ($amount * $rates[$from]) / $rates[$to];
    }

    public function exchange($userId, $fromCurrency, $toCurrency, $amount) {
        if ($fromCurrency === $toCurrency) throw new Exception('same_currency');
        $fromBalance = $this->getBalance($userId, $fromCurrency);
        if ($fromBalance < $amount) throw new Exception('insufficient_balance');
        $converted = $this->convert($amount, $fromCurrency, $toCurrency);
        $this->db->beginTransaction();
        try {
            $this->getOrCreateWallet($userId, $toCurrency);
            $this->db->prepare("UPDATE wallets SET balance = balance - ? WHERE user_id = ? AND currency = ?")
                ->execute([$amount, $userId, $fromCurrency]);
            $this->db->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ? AND currency = ?")
                ->execute([$converted, $userId, $toCurrency]);
            $txId = $this->recordTransaction($userId, null, $fromCurrency, -$amount, 'exchange', 'system', "تبدیل به $toCurrency");
            $this->db->commit();
            return ['tx_id' => $txId, 'converted' => $converted];
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function setPin($userId, $pin) {
        $hash = password_hash($pin, PASSWORD_BCRYPT);
        $this->db->prepare("UPDATE users SET wallet_pin = ? WHERE id = ?")->execute([$hash, $userId]);
    }

    public function verifyPin($userId, $pin) {
        $stmt = $this->db->prepare("SELECT wallet_pin FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r && $r['wallet_pin'] && password_verify($pin, $r['wallet_pin']);
    }

    public function recordTransaction($fromId, $toId, $currency, $amount, $type, $method, $description) {
        $stmt = $this->db->prepare("INSERT INTO wallet_transactions
            (from_user_id, to_user_id, currency, amount, type, method, description, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', NOW())");
        $stmt->execute([$fromId, $toId, $currency, $amount, $type, $method, $description]);
        return $this->db->lastInsertId();
    }

    public function getTransactions($userId, $limit = 50, $type = null, $currency = null) {
        $sql = "SELECT t.*,
            uf.display_name as from_name, uf.username as from_username,
            ut.display_name as to_name, ut.username as to_username
            FROM wallet_transactions t
            LEFT JOIN users uf ON uf.id = t.from_user_id
            LEFT JOIN users ut ON ut.id = t.to_user_id
            WHERE (t.from_user_id = ? OR t.to_user_id = ?)";
        $params = [$userId, $userId];
        if ($type) { $sql .= " AND t.type = ?"; $params[] = $type; }
        if ($currency) { $sql .= " AND t.currency = ?"; $params[] = $currency; }
        $sql .= " ORDER BY t.created_at DESC LIMIT ?";
        $params[] = $limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStats($userId) {
        $stmt = $this->db->prepare("SELECT
            (SELECT COUNT(*) FROM wallet_transactions WHERE (from_user_id = ? OR to_user_id = ?) AND amount > 0) as income_count,
            (SELECT COUNT(*) FROM wallet_transactions WHERE (from_user_id = ? OR to_user_id = ?) AND amount < 0) as expense_count,
            (SELECT COALESCE(SUM(amount), 0) FROM wallet_transactions WHERE to_user_id = ? AND amount > 0) as total_in,
            (SELECT COALESCE(ABS(SUM(amount)), 0) FROM wallet_transactions WHERE from_user_id = ? AND amount < 0) as total_out,
            (SELECT COUNT(*) FROM wallets WHERE user_id = ? AND balance > 0) as active_currencies");
        $stmt->execute([$userId, $userId, $userId, $userId, $userId, $userId, $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function notifyTransfer($toUserId, $fromUserId, $currency, $amount) {
        $msg = "💰 شما $amount $currency دریافت کردید";
        $this->db->prepare("INSERT INTO wallet_notifications (user_id, type, title, body, created_at) VALUES (?, 'transfer', ?, ?, NOW())")
            ->execute([$toUserId, 'دریافت وجه', $msg]);
    }

    private function notifyPaymentRequest($toUserId, $fromUserId, $currency, $amount, $requestId) {
        $this->db->prepare("INSERT INTO wallet_notifications (user_id, type, title, body, related_id, created_at)
            VALUES (?, 'request', ?, ?, ?, NOW())")
            ->execute([$toUserId, 'درخواست پرداخت', "درخواست $amount $currency", $requestId]);
    }
}
