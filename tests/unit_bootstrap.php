<?php
/**
 * Bootstrap for Unit Tests
 * Defines mock database functions for testing without a database
 */

require_once dirname(__DIR__) . '/tests/bootstrap.php';

// Only define mocks if not already defined (by integration tests)
// The mock functions return minimal values for unit test compatibility
// Note: auth_start_session is defined in auth.php, so don't mock it

if (!function_exists('db_escape')) {
    function db_escape($value) {
        return addslashes($value);
    }
}

if (!function_exists('db_query')) {
    function db_query($sql) {
        return false;
    }
}

if (!function_exists('db_num_rows')) {
    function db_num_rows($result) {
        return 0;
    }
}

if (!function_exists('db_fetch_assoc')) {
    function db_fetch_assoc($result) {
        return null;
    }
}

if (!function_exists('db_fetch_all')) {
    function db_fetch_all($result) {
        return [];
    }
}
