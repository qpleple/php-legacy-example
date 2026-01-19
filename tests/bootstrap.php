<?php
/**
 * PHPUnit Test Bootstrap
 * Sets up the test environment for the legacy PHP application
 * Supports MySQL (preferred) or SQLite (fallback) for testing
 */

// Suppress session warnings in CLI
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Define base path
define('BASE_PATH', dirname(__DIR__));
define('WWW_PATH', BASE_PATH . '/www');
define('TESTS_PATH', __DIR__);

// Track which database type is being used
global $test_db_type;
$test_db_type = null;

/**
 * Reset the test database (MySQL version)
 */
function resetTestDatabaseMySQL() {
    global $db_link;

    // Load schema
    $schema = file_get_contents(BASE_PATH . '/sql/01_schema.sql');

    // Split by semicolon and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $schema)));
    foreach ($statements as $stmt) {
        if (!empty($stmt) && stripos($stmt, 'SET NAMES') === false) {
            @mysqli_query($db_link, $stmt);
        }
    }

    // Load seed data
    $seed = file_get_contents(BASE_PATH . '/sql/02_seed.sql');
    $statements = array_filter(array_map('trim', explode(';', $seed)));
    foreach ($statements as $stmt) {
        if (!empty($stmt) && stripos($stmt, 'SET NAMES') === false) {
            @mysqli_query($db_link, $stmt);
        }
    }
}

/**
 * Reset the test database (SQLite version)
 */
function resetTestDatabaseSQLite() {
    global $db_pdo;

    // Load schema
    $schema = file_get_contents(TESTS_PATH . '/sqlite_schema.sql');
    $statements = array_filter(array_map('trim', explode(';', $schema)));
    foreach ($statements as $stmt) {
        if (!empty($stmt)) {
            try {
                $db_pdo->exec($stmt);
            } catch (PDOException $e) {
                // Ignore errors for DROP TABLE IF NOT EXISTS on non-existent tables
            }
        }
    }

    // Load seed data
    $seed = file_get_contents(TESTS_PATH . '/sqlite_seed.sql');
    $statements = array_filter(array_map('trim', explode(';', $seed)));
    foreach ($statements as $stmt) {
        if (!empty($stmt)) {
            try {
                $db_pdo->exec($stmt);
            } catch (PDOException $e) {
                // Continue on error
            }
        }
    }
}

/**
 * Reset the test database (dispatches to correct implementation)
 */
function resetTestDatabase() {
    global $test_db_type;

    if ($test_db_type === 'mysql') {
        resetTestDatabaseMySQL();
    } elseif ($test_db_type === 'sqlite') {
        resetTestDatabaseSQLite();
    }
}

/**
 * Try to connect to MySQL test database
 */
function connectMySQLDatabase() {
    global $db_link, $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;

    $DB_HOST = getenv('DB_HOST') ?: 'localhost';
    $DB_USER = getenv('DB_USER') ?: 'compta';
    $DB_PASS = getenv('DB_PASS') ?: 'compta123';
    $DB_NAME = getenv('DB_NAME') ?: 'compta_test';

    try {
        $db_link = @mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

        if ($db_link) {
            mysqli_query($db_link, "SET NAMES utf8");
            return true;
        }
    } catch (mysqli_sql_exception $e) {
        $db_link = null;
    }

    return false;
}

/**
 * Connect to SQLite test database
 */
function connectSQLiteDatabase() {
    global $db_pdo;

    try {
        // Use in-memory database for fast tests
        $db_pdo = new PDO('sqlite::memory:');
        $db_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Initialize the database schema and seed data right away
        // since in-memory DB loses data if we close connection
        $schema = file_get_contents(TESTS_PATH . '/sqlite_schema.sql');
        $statements = array_filter(array_map('trim', explode(';', $schema)));
        foreach ($statements as $stmt) {
            if (!empty($stmt)) {
                $db_pdo->exec($stmt);
            }
        }

        $seed = file_get_contents(TESTS_PATH . '/sqlite_seed.sql');
        $statements = array_filter(array_map('trim', explode(';', $seed)));
        foreach ($statements as $stmt) {
            if (!empty($stmt)) {
                try {
                    $db_pdo->exec($stmt);
                } catch (PDOException $e) {
                    // Continue on seed errors (might be duplicate data)
                }
            }
        }

        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Connect to test database (tries MySQL first, falls back to SQLite)
 */
function connectTestDatabase() {
    global $test_db_type, $db_pdo;

    // Try MySQL first
    if (connectMySQLDatabase()) {
        $test_db_type = 'mysql';
        return true;
    }

    // Fall back to SQLite
    if (connectSQLiteDatabase()) {
        $test_db_type = 'sqlite';
        // Include SQLite db wrapper which will use the global $db_pdo
        require_once TESTS_PATH . '/db_sqlite.php';
        return true;
    }

    return false;
}

/**
 * Get the current database type
 */
function getTestDatabaseType() {
    global $test_db_type;
    return $test_db_type;
}

/**
 * Mock session for testing
 */
function mockSession($data = []) {
    $_SESSION = array_merge($_SESSION, $data);
}

/**
 * Clear session
 */
function clearSession() {
    $_SESSION = [];
}

/**
 * Mock POST data
 */
function mockPost($data = []) {
    $_POST = $data;
    $_SERVER['REQUEST_METHOD'] = 'POST';
}

/**
 * Clear POST data
 */
function clearPost() {
    $_POST = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
}
