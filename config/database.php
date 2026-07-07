<?php
/**
 * Database config
 * Real values are read from config.php's constants (DB_HOST, DB_NAME, etc.)
 * This file is kept for backward compatibility.
 */
require_once __DIR__ . '/config.php';

return [
    'host' => DB_HOST,
    'name' => DB_NAME,
    'user' => DB_USER,
    'pass' => DB_PASS,
    'charset' => DB_CHARSET,
];
