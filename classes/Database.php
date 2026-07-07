<?php
/**
 * NexusChat - Database singleton (PDO)
 */
class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ]);
        } catch (PDOException $e) {
            if (APP_DEBUG) {
                json_response(['success' => false, 'message' => 'db_connect_failed', 'detail' => $e->getMessage()], 500);
            }
            json_response(['success' => false, 'message' => 'db_connect_failed'], 500);
        }
    }

    public static function getInstance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    public function prepare($sql) { return $this->pdo->prepare($sql); }
    public function query($sql) { return $this->pdo->query($sql); }
    public function exec($sql) { return $this->pdo->exec($sql); }
    public function lastInsertId() { return $this->pdo->lastInsertId(); }
    public function beginTransaction() { return $this->pdo->beginTransaction(); }
    public function commit() { return $this->pdo->commit(); }
    public function rollBack() { return $this->pdo->rollBack(); }
    public function pdo() { return $this->pdo; }
}
