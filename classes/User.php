<?php
/**
 * NexusChat - User Class
 */
class User {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Register a new user
     */
    public function register($username, $email, $password, $displayName = null, $phone = null) {
        // Validate
        if (strlen($username) < 3 || strlen($username) > 50) {
            throw new Exception('نام کاربری باید بین ۳ تا ۵۰ کاراکتر باشد');
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            throw new Exception('نام کاربری فقط شامل حروف انگلیسی، اعداد و _ باشد');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('ایمیل نامعتبر است');
        }
        if (strlen($password) < 6) {
            throw new Exception('رمز عبور باید حداقل ۶ کاراکتر باشد');
        }
        if ($this->findByUsername($username)) {
            throw new Exception('نام کاربری قبلاً ثبت شده');
        }
        if ($this->findByEmail($email)) {
            throw new Exception('ایمیل قبلاً ثبت شده');
        }

        // Generate keys for E2E
        $enc = new Encryption();
        $keys = $enc->generateKeyPair();
        $encryptedPriv = $enc->encryptPrivateKey($keys['private'], $password);

        $id = $this->db->insert('users', [
            'username'             => $username,
            'email'                => $email,
            'phone'                => $phone,
            'password_hash'        => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
            'display_name'         => $displayName ?: $username,
            'public_key'           => $keys['public'],
            'private_key_encrypted'=> $encryptedPriv,
        ]);

        return $this->findById($id);
    }

    /**
     * Login user
     */
    public function login($identifier, $password) {
        $user = $this->findByUsername($identifier) ?: $this->findByEmail($identifier);
        if (!$user) {
            throw new Exception('کاربری با این مشخصات یافت نشد');
        }
        if (!password_verify($password, $user['password_hash'])) {
            throw new Exception('رمز عبور اشتباه است');
        }
        $this->setOnline($user['id']);
        return $user;
    }

    public function findById($id) {
        return $this->db->fetch("SELECT * FROM users WHERE id = ?", [$id]);
    }

    public function findByUsername($username) {
        return $this->db->fetch("SELECT * FROM users WHERE username = ?", [$username]);
    }

    public function findByEmail($email) {
        return $this->db->fetch("SELECT * FROM users WHERE email = ?", [$email]);
    }

    public function setOnline($userId) {
        $this->db->update('users',
            ['is_online' => 1, 'last_seen' => date('Y-m-d H:i:s')],
            'id = :id',
            ['id' => $userId]
        );
    }

    public function setOffline($userId) {
        $this->db->update('users',
            ['is_online' => 0, 'last_seen' => date('Y-m-d H:i:s')],
            'id = :id',
            ['id' => $userId]
        );
    }

    public function search($query, $excludeId = null) {
        $sql = "SELECT id, username, display_name, avatar, status_text, is_online, is_verified
                FROM users
                WHERE (username LIKE ? OR display_name LIKE ? OR email LIKE ?)";
        $params = ["%$query%", "%$query%", "%$query%"];
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        $sql .= " LIMIT 30";
        return $this->db->fetchAll($sql, $params);
    }

    public function updateProfile($userId, $data) {
        $allowed = ['display_name', 'bio', 'status_text', 'avatar', 'theme', 'language'];
        $update = array_intersect_key($data, array_flip($allowed));
        if (empty($update)) return false;
        return $this->db->update('users', $update, 'id = :id', ['id' => $userId]);
    }

    public function block($userId, $blockedId) {
        try {
            $this->db->insert('blocked_users', [
                'user_id' => $userId,
                'blocked_user_id' => $blockedId,
            ]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function unblock($userId, $blockedId) {
        return $this->db->delete('blocked_users',
            'user_id = ? AND blocked_user_id = ?',
            [$userId, $blockedId]
        );
    }

    public function isBlocked($userId, $otherId) {
        return (bool) $this->db->fetch(
            "SELECT 1 FROM blocked_users WHERE user_id = ? AND blocked_user_id = ?",
            [$userId, $otherId]
        );
    }

    public function getPublicProfile($userId) {
        return $this->db->fetch(
            "SELECT id, username, display_name, bio, avatar, status_text, is_online, is_verified, last_seen, created_at
             FROM users WHERE id = ?",
            [$userId]
        );
    }
}
