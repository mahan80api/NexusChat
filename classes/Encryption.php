<?php
/**
 * NexusChat - End-to-End Encryption
 * RSA-2048 for key exchange, AES-256-GCM for message content
 */
class Encryption {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Generate RSA-2048 key pair for a new user
     */
    public function generateKeyPair() {
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        $res = openssl_pkey_new($config);
        openssl_pkey_export($res, $privateKey);
        $details = openssl_pkey_get_details($res);
        $publicKey = $details['key'];
        return [
            'public'  => $publicKey,
            'private' => $privateKey,
        ];
    }

    /**
     * Encrypt private key with user's password (symmetric AES)
     */
    public function encryptPrivateKey($privateKey, $password) {
        $salt = random_bytes(16);
        $iv = random_bytes(16);
        $key = hash_pbkdf2('sha256', $password, $salt, 10000, 32, true);
        $cipher = openssl_encrypt(
            $privateKey,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
        return base64_encode(json_encode([
            'salt' => base64_encode($salt),
            'iv'   => base64_encode($iv),
            'tag'  => base64_encode($tag),
            'data' => base64_encode($cipher),
        ]));
    }

    /**
     * Decrypt private key with user's password
     */
    public function decryptPrivateKey($encryptedData, $password) {
        $raw = json_decode(base64_decode($encryptedData), true);
        if (!$raw) return null;
        $salt = base64_decode($raw['salt']);
        $iv = base64_decode($raw['iv']);
        $tag = base64_decode($raw['tag']);
        $data = base64_decode($raw['data']);
        $key = hash_pbkdf2('sha256', $password, $salt, 10000, 32, true);
        return openssl_decrypt($data, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    }

    /**
     * Encrypt message for a recipient using their public key
     */
    public function encryptForRecipient($message, $publicKey) {
        $key = random_bytes(32);
        $iv = random_bytes(12);
        $cipher = openssl_encrypt($message, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

        openssl_public_encrypt($key, $encryptedKey, $publicKey);

        return base64_encode(json_encode([
            'key' => base64_encode($encryptedKey),
            'iv'  => base64_encode($iv),
            'tag' => base64_encode($tag),
            'data'=> base64_encode($cipher),
        ]));
    }

    /**
     * Decrypt message using private key
     */
    public function decryptFromSender($encryptedData, $privateKey) {
        $raw = json_decode(base64_decode($encryptedData), true);
        if (!$raw) return null;
        openssl_private_decrypt(base64_decode($raw['key']), $key, $privateKey);
        if (!$key) return null;
        return openssl_decrypt(
            base64_decode($raw['data']),
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            base64_decode($raw['iv']),
            base64_decode($raw['tag'])
        );
    }
}
