<?php
/**
 * PHPUnit Test Bootstrap
 * Sets up the test environment with SQLite in-memory database
 */

// Suppress session warnings in CLI
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Define base paths
define('BASE_PATH', dirname(__DIR__));
define('WWW_PATH', BASE_PATH . '/www');
define('TESTS_PATH', __DIR__);

// Global PDO connection
global $db_pdo;
$db_pdo = null;

/**
 * Reset the test database by re-running schema and seed
 */
function resetTestDatabase() {
    global $db_pdo;

    // Load schema
    $schema = file_get_contents(TESTS_PATH . '/sqlite_schema.sql');
    $statements = array_filter(array_map('trim', explode(';', $schema)));
    foreach ($statements as $stmt) {
        if (!empty($stmt)) {
            try {
                $db_pdo->exec($stmt);
            } catch (PDOException $e) {
                // Ignore errors (e.g., DROP TABLE on non-existent tables)
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
                // Continue on seed errors
            }
        }
    }
}

/**
 * Connect to SQLite test database (in-memory for speed)
 */
function connectTestDatabase() {
    global $db_pdo;

    try {
        $db_pdo = new PDO('sqlite::memory:');
        $db_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Initialize schema and seed data
        resetTestDatabase();

        // Include SQLite db wrapper
        require_once TESTS_PATH . '/db_sqlite.php';

        return true;
    } catch (PDOException $e) {
        return false;
    }
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
