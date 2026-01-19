<?php
/**
 * Database connection - Legacy style (2006)
 * Uses mysql_* functions (deprecated but intentional for legacy simulation)
 */

// Database configuration
$DB_HOST = getenv('DB_HOST') ? getenv('DB_HOST') : 'localhost';
$DB_USER = getenv('DB_USER') ? getenv('DB_USER') : 'compta';
$DB_PASS = getenv('DB_PASS') ? getenv('DB_PASS') : 'compta123';
$DB_NAME = getenv('DB_NAME') ? getenv('DB_NAME') : 'compta';

// Global connection variable
$db_link = null;

/**
 * Connect to database
 */
function db_connect() {
    global $db_link, $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;

    if ($db_link !== null) {
        return $db_link;
    }

    // Use mysqli for PHP 5.6+ compatibility (mysql_* removed in PHP 7)
    $db_link = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

    if (!$db_link) {
        die('Database connection failed: ' . mysqli_connect_error());
    }

    // Set charset
    mysqli_query($db_link, "SET NAMES utf8");

    return $db_link;
}

/**
 * Execute a query
 */
function db_query($sql) {
    global $db_link;

    if ($db_link === null) {
        db_connect();
    }

    $result = mysqli_query($db_link, $sql);

    if ($result === false) {
        // In dev mode, show error
        die('SQL Error: ' . mysqli_error($db_link) . '<br>Query: ' . htmlspecialchars($sql));
    }

    return $result;
}

/**
 * Fetch associative array from result
 */
function db_fetch_assoc($result) {
    return mysqli_fetch_assoc($result);
}

/**
 * Fetch all rows as array
 */
function db_fetch_all($result) {
    $rows = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    return $rows;
}

/**
 * Get number of rows
 */
function db_num_rows($result) {
    return mysqli_num_rows($result);
}

/**
 * Get last insert ID
 */
function db_insert_id() {
    global $db_link;
    return mysqli_insert_id($db_link);
}

/**
 * Escape string for SQL (legacy style - no prepared statements)
 */
function db_escape($value) {
    global $db_link;

    if ($db_link === null) {
        db_connect();
    }

    if ($value === null) {
        return 'NULL';
    }

    return mysqli_real_escape_string($db_link, $value);
}

/**
 * Get affected rows
 */
function db_affected_rows() {
    global $db_link;
    return mysqli_affected_rows($db_link);
}

/**
 * Close connection
 */
function db_close() {
    global $db_link;

    if ($db_link !== null) {
        mysqli_close($db_link);
        $db_link = null;
    }
}

// Auto-connect on include
db_connect();
