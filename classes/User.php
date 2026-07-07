<?php
/**
 * NexusChat - User Class
 */
class User {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function findById($id) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findByUsername($username) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findByEmail($email) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findByPhone($phone) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getPublicProfile($id) {
        $stmt = $this->db->prepare("SELECT id, username, display_name, avatar, bio, status_text, is_online, last_seen
            FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($data) {
        $stmt = $this->db->prepare("INSERT INTO users (username, email, phone, password_hash, display_name)
            VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['username'],
            $data['email'] ?? null,
            $data['phone'] ?? null,
            password_hash($data['password'], PASSWORD_BCRYPT),
            $data['display_name'] ?? $data['username'],
        ]);
        return $this->db->lastInsertId();
    }

    public function update($userId, $data) {
        $fields = [];
        $params = [];
        $allowed = ['display_name', 'bio', 'status_text', 'theme', 'language', 'avatar'];
        foreach ($allowed as $k) {
            if (isset($data[$k])) {
                $fields[] = "$k = ?";
                $params[] = $data[$k];
            }
        }
        if (empty($fields)) return false;
        $params[] = $userId;
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function verifyPassword($userId, $password) {
        $u = $this->findById($userId);
        if (!$u) return false;
        return password_verify($password, $u['password_hash']);
    }

    public function setOnline($userId, $online = true) {
        $stmt = $this->db->prepare("UPDATE users SET is_online = ?, last_seen = NOW() WHERE id = ?");
        $stmt->execute([$online ? 1 : 0, $userId]);
    }

    /**
     * Login by username, email, or phone
     */
    public function login($identifier, $password) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ? OR email = ? OR phone = ? LIMIT 1");
        $stmt->execute([$identifier, $identifier, $identifier]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$u) return null;
        if (!password_verify($password, $u['password_hash'])) return null;
        return $u;
    }

    public function register($username, $email, $password, $displayName = '', $phone = '') {
        if ($this->findByUsername($username)) throw new Exception('username_taken');
        if ($email && $this->findByEmail($email)) throw new Exception('email_taken');
        if ($phone && $this->findByPhone($phone)) throw new Exception('phone_taken');
        return $this->create([
            'username' => $username,
            'email' => $email,
            'phone' => $phone,
            'password' => $password,
            'display_name' => $displayName ?: $username,
        ]);
    }

    public function search($query, $limit = 20) {
        $stmt = $this->db->prepare("SELECT id, username, display_name, avatar, status_text, is_online, last_seen
            FROM users
            WHERE username LIKE ? OR display_name LIKE ? OR email LIKE ?
            ORDER BY is_online DESC, username ASC
            LIMIT " . (int)$limit);
        $like = '%' . $query . '%';
        $stmt->execute([$like, $like, $like]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function searchContacts($userId, $query = '') {
        $sql = "SELECT DISTINCT u.id, u.username, u.display_name, u.avatar, u.status_text, u.is_online, u.last_seen
            FROM users u
            JOIN chat_members cm1 ON cm1.user_id = u.id
            JOIN chat_members cm2 ON cm2.chat_id = cm1.chat_id
            WHERE cm2.user_id = ? AND u.id != ?";
        $params = [$userId, $userId];
        if ($query) {
            $sql .= " AND (u.username LIKE ? OR u.display_name LIKE ?)";
            $like = '%' . $query . '%';
            $params[] = $like;
            $params[] = $like;
        }
        $sql .= " ORDER BY u.is_online DESC, u.last_seen DESC LIMIT 50";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
