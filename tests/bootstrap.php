<?php
/**
 * PHPUnit Test Bootstrap
 * Sets up the test environment for the legacy PHP application
 */

// Suppress session warnings in CLI
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Define base path
define('BASE_PATH', dirname(__DIR__));
define('WWW_PATH', BASE_PATH . '/www');

// Include library files (but not db.php which auto-connects)
// We'll include them individually in tests as needed

/**
 * Reset the test database
 */
function resetTestDatabase() {
    global $db_link;

    // Load schema
    $schema = file_get_contents(BASE_PATH . '/sql/01_schema.sql');

    // Split by semicolon and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $schema)));
    foreach ($statements as $stmt) {
        if (!empty($stmt)) {
            mysqli_query($db_link, $stmt);
        }
    }

    // Load seed data
    $seed = file_get_contents(BASE_PATH . '/sql/02_seed.sql');
    $statements = array_filter(array_map('trim', explode(';', $seed)));
    foreach ($statements as $stmt) {
        if (!empty($stmt)) {
            mysqli_query($db_link, $stmt);
        }
    }
}

/**
 * Connect to test database
 */
function connectTestDatabase() {
    global $db_link, $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;

    $DB_HOST = getenv('DB_HOST') ?: 'localhost';
    $DB_USER = getenv('DB_USER') ?: 'compta';
    $DB_PASS = getenv('DB_PASS') ?: 'compta123';
    $DB_NAME = getenv('DB_NAME') ?: 'compta_test';

    $db_link = @mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

    if ($db_link) {
        mysqli_query($db_link, "SET NAMES utf8");
    }

    return $db_link !== false;
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
