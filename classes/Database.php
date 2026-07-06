<?php
/**
 * NexusChat - Database Wrapper Class
 */
class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $this->pdo = getDB();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function pdo() {
        return $this->pdo;
    }

    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetch($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }

    public function insert($table, $data) {
        $cols = array_keys($data);
        $placeholders = array_map(fn($c) => ':' . $c, $cols);
        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $table,
            implode(',', $cols),
            implode(',', $placeholders)
        );
        $this->query($sql, $data);
        return $this->pdo->lastInsertId();
    }

    public function update($table, $data, $where, $whereParams = []) {
        $sets = array_map(fn($c) => "$c = :$c", array_keys($data));
        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s",
            $table,
            implode(',', $sets),
            $where
        );
        return $this->query($sql, array_merge($data, $whereParams))->rowCount();
    }

    public function delete($table, $where, $params = []) {
        $sql = sprintf("DELETE FROM %s WHERE %s", $table, $where);
        return $this->query($sql, $params)->rowCount();
    }

    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    public function commit() {
        return $this->pdo->commit();
    }

    public function rollBack() {
        return $this->pdo->rollBack();
    }
}
