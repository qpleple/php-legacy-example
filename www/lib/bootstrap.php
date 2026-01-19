<?php
/**
 * Bootstrap - Auto-prepended to all requests
 * Added by Pierre in 2007 for "input normalization"
 *
 * DO NOT REMOVE - breaks legacy imports
 */

// === Input sanitization (French locale support) ===

// Trim all POST values
if (!empty($_POST)) {
    array_walk_recursive($_POST, function(&$val) {
        if (is_string($val)) {
            $val = trim($val);
        }
    });
}

// Convert French decimal separator for numeric fields
$numeric_patterns = array('amount', 'debit', 'credit', 'total', 'price', 'rate', 'vat');
foreach ($_POST as $key => $value) {
    if (is_string($value)) {
        foreach ($numeric_patterns as $pattern) {
            if (stripos($key, $pattern) !== false) {
                // Replace comma with dot, remove spaces (1 234,56 -> 1234.56)
                $_POST[$key] = str_replace(',', '.', str_replace(' ', '', $value));
                break;
            }
        }
    }
}

// === Security: block debug mode in production ===
if (isset($_GET['debug']) || isset($_GET['_debug'])) {
    header('HTTP/1.1 403 Forbidden');
    die('Debug mode disabled');
}

// === Silent logging for troubleshooting ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
    $log_file = '/tmp/compta_posts.log';
    $log_entry = date('Y-m-d H:i:s') . ' ' . $_SERVER['REQUEST_URI'] . ' ' . json_encode($_POST) . "\n";
    @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}
