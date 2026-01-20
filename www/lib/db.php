<?php
/**
 * Database connection - SQLite version
 * Uses PDO with SQLite for simple deployment
 */

// Global connection variable (preserve if already set for testing)
if (!isset($db_pdo)) {
    $db_pdo = null;
}
if (!isset($db_last_result)) {
    $db_last_result = null;
}

/**
 * Result wrapper class that allows counting rows without consuming the result
 */
if (!class_exists('SQLiteResult')) {
class SQLiteResult {
    private $rows = [];
    private $position = 0;
    private $rowCount = 0;

    public function __construct(PDOStatement $stmt) {
        $this->rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->rowCount = count($this->rows);
        $this->position = 0;
    }

    public function fetch() {
        if ($this->position >= $this->rowCount) {
            return false;
        }
        return $this->rows[$this->position++];
    }

    public function fetchAll() {
        $remaining = array_slice($this->rows, $this->position);
        $this->position = $this->rowCount;
        return $remaining;
    }

    public function numRows() {
        return $this->rowCount;
    }

    public function rowCount() {
        return $this->rowCount;
    }
}
}

/**
 * Check if running in test mode (via X-Test-Mode header)
 */
if (!function_exists('is_test_mode')) {
function is_test_mode() {
    // Check for X-Test-Mode header
    if (isset($_SERVER['HTTP_X_TEST_MODE']) && $_SERVER['HTTP_X_TEST_MODE'] === '1') {
        return true;
    }
    return false;
}
}

/**
 * Get database path based on mode (test or production)
 */
if (!function_exists('get_db_path')) {
function get_db_path() {
    $base_path = getenv('SQLITE_DB') ?: '/var/www/html/data/compta.db';

    if (is_test_mode()) {
        // Use test database in same directory
        $dir = dirname($base_path);
        return $dir . '/compta_test.db';
    }

    return $base_path;
}
}

/**
 * Connect to SQLite database
 */
if (!function_exists('db_connect')) {
function db_connect() {
    global $db_pdo;

    if ($db_pdo !== null) {
        return $db_pdo;
    }

    $db_path = get_db_path();

    // Ensure directory exists
    $dir = dirname($db_path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    try {
        $db_pdo = new PDO('sqlite:' . $db_path);
        $db_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Enable foreign keys
        $db_pdo->exec('PRAGMA foreign_keys = ON');

        return $db_pdo;
    } catch (PDOException $e) {
        die('Database connection failed: ' . $e->getMessage());
    }
}
}

/**
 * Execute a query
 */
if (!function_exists('db_query')) {
function db_query($sql) {
    global $db_pdo, $db_last_result;

    if ($db_pdo === null) {
        db_connect();
    }

    try {
        // Handle MySQL-specific syntax for SQLite compatibility
        $sql = str_replace('NOW()', "datetime('now')", $sql);

        $stmt = $db_pdo->query($sql);

        // Wrap SELECT results so we can use num_rows and fetch
        if (stripos(trim($sql), 'SELECT') === 0) {
            $result = new SQLiteResult($stmt);
            $db_last_result = $result;
            return $result;
        }

        $db_last_result = $stmt;
        return $stmt;
    } catch (PDOException $e) {
        die('SQL Error: ' . $e->getMessage() . '<br>Query: ' . htmlspecialchars($sql));
    }
}
}

/**
 * Fetch associative array from result
 */
if (!function_exists('db_fetch_assoc')) {
function db_fetch_assoc($result) {
    if ($result === false || $result === null) {
        return null;
    }

    if ($result instanceof SQLiteResult) {
        return $result->fetch();
    }

    return $result->fetch(PDO::FETCH_ASSOC);
}
}

/**
 * Fetch all rows as array
 */
if (!function_exists('db_fetch_all')) {
function db_fetch_all($result) {
    if ($result === false || $result === null) {
        return [];
    }

    if ($result instanceof SQLiteResult) {
        return $result->fetchAll();
    }

    return $result->fetchAll(PDO::FETCH_ASSOC);
}
}

/**
 * Get number of rows
 */
if (!function_exists('db_num_rows')) {
function db_num_rows($result) {
    if ($result === false || $result === null) {
        return 0;
    }

    if ($result instanceof SQLiteResult) {
        return $result->numRows();
    }

    return 0;
}
}

/**
 * Get last insert ID
 */
if (!function_exists('db_insert_id')) {
function db_insert_id() {
    global $db_pdo;
    return $db_pdo->lastInsertId();
}
}

/**
 * Escape string for SQL
 */
if (!function_exists('db_escape')) {
function db_escape($value) {
    global $db_pdo;

    if ($db_pdo === null) {
        db_connect();
    }

    if ($value === null) {
        return 'NULL';
    }

    // PDO::quote includes the quotes, so we need to strip them
    $quoted = $db_pdo->quote($value);
    return substr($quoted, 1, -1);
}
}

/**
 * Get affected rows
 */
if (!function_exists('db_affected_rows')) {
function db_affected_rows() {
    global $db_last_result;
    if ($db_last_result === null) {
        return 0;
    }

    if ($db_last_result instanceof SQLiteResult) {
        return $db_last_result->rowCount();
    }

    return $db_last_result->rowCount();
}
}

/**
 * Close connection
 */
if (!function_exists('db_close')) {
function db_close() {
    global $db_pdo;
    $db_pdo = null;
}
}

// Auto-connect on include (skip if already connected, e.g., in tests)
if ($db_pdo === null) {
    db_connect();
}
