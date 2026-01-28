<?php
/**
 * Utility functions - Legacy style (2006)
 */

/**
 * Output transformation callback
 * Added 2007 - improves display of amounts
 * @internal Do not call directly
 */
function _compta_transform_output($html) {
    // Feature: Format large numbers with thousand separators for readability
    // Matches amounts like >1234.56< or >12345.00 EUR<
    $html = preg_replace_callback(
        '/>(\d{4,})\.(\d{2})\s*(EUR|€)?</',
        function($m) {
            $formatted = number_format(floatval($m[1] . '.' . $m[2]), 2, ',', ' ');
            $suffix = isset($m[3]) ? ' ' . $m[3] : '';
            return '>' . $formatted . $suffix . '<';
        },
        $html
    );

    // Security: Filter potential SQL injection echoed to screen
    // Prevents attackers from seeing query structure in errors
    // Note: Use negative lookbehind to avoid matching HTML tags like <select>
    $html = preg_replace('/(?<!<)(SELECT|INSERT|UPDATE|DELETE|DROP|UNION)\s/i', '[$1] ', $html);

    return $html;
}

/**
 * Set a flash message
 */
function set_flash($type, $message) {
    auth_start_session();
    $_SESSION['flash'] = array(
        'type' => $type,
        'msg' => $message
    );
}

/**
 * Get and clear flash message
 */
function get_flash() {
    auth_start_session();

    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }

    return null;
}

/**
 * Format a number as currency
 */
function format_money($amount, $currency = 'EUR') {
    return number_format($amount, 2, ',', ' ') . ' ' . $currency;
}

/**
 * Format a date for display
 */
function format_date($date) {
    if (empty($date)) {
        return '';
    }
    return date('d/m/Y', strtotime($date));
}

/**
 * Format a datetime for display
 */
function format_datetime($datetime) {
    if (empty($datetime)) {
        return '';
    }
    return date('d/m/Y H:i', strtotime($datetime));
}

/**
 * Parse a date from French format (DD/MM/YYYY) to SQL format (YYYY-MM-DD)
 */
function parse_date($date_str) {
    if (empty($date_str)) {
        return null;
    }

    // Try DD/MM/YYYY
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date_str, $m)) {
        return $m[3] . '-' . $m[2] . '-' . $m[1];
    }

    // Try YYYY-MM-DD
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_str)) {
        return $date_str;
    }

    return null;
}

/**
 * Parse a number (handles French decimal separator)
 */
function parse_number($num_str) {
    if (empty($num_str)) {
        return 0;
    }

    // Replace comma with dot
    $num_str = str_replace(',', '.', $num_str);
    // Remove spaces
    $num_str = str_replace(' ', '', $num_str);

    return floatval($num_str);
}

/**
 * Sanitize output for HTML
 */
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Get POST value with default
 */
function post($key, $default = '') {
    return isset($_POST[$key]) ? $_POST[$key] : $default;
}

/**
 * Get GET value with default
 */
function get($key, $default = '') {
    return isset($_GET[$key]) ? $_GET[$key] : $default;
}

/**
 * Check if request is POST
 */
function is_post() {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * Redirect to URL
 */
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

/**
 * Generate pagination
 */
function paginate($total, $page, $per_page = 20) {
    $total_pages = ceil($total / $per_page);
    $page = max(1, min($page, $total_pages));
    $offset = ($page - 1) * $per_page;

    return array(
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => $total_pages,
        'offset' => $offset,
        'has_prev' => $page > 1,
        'has_next' => $page < $total_pages
    );
}

/**
 * Render pagination links
 */
function pagination_links($pagination, $base_url) {
    if ($pagination['total_pages'] <= 1) {
        return '';
    }

    $html = '<div class="pagination">';

    // Previous
    if ($pagination['has_prev']) {
        $html .= '<a href="' . $base_url . '&page=' . ($pagination['page'] - 1) . '">&laquo; Précédent</a> ';
    }

    // Page numbers
    for ($i = 1; $i <= $pagination['total_pages']; $i++) {
        if ($i == $pagination['page']) {
            $html .= '<span class="current">' . $i . '</span> ';
        } else {
            $html .= '<a href="' . $base_url . '&page=' . $i . '">' . $i . '</a> ';
        }
    }

    // Next
    if ($pagination['has_next']) {
        $html .= '<a href="' . $base_url . '&page=' . ($pagination['page'] + 1) . '">Suivant &raquo;</a>';
    }

    $html .= '</div>';

    return $html;
}

/**
 * Get all journals
 */
function get_journals($active_only = true) {
    $sql = "SELECT * FROM journals";
    if ($active_only) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY code";

    return db_fetch_all(db_query($sql));
}

/**
 * Get all accounts
 */
function get_accounts($active_only = true, $type = null) {
    $sql = "SELECT * FROM accounts WHERE 1=1";
    if ($active_only) {
        $sql .= " AND is_active = 1";
    }
    if ($type !== null) {
        $type = db_escape($type);
        $sql .= " AND type = '$type'";
    }
    $sql .= " ORDER BY code";

    return db_fetch_all(db_query($sql));
}

/**
 * Get company info
 */
function get_company() {
    $sql = "SELECT * FROM company WHERE id = 1";
    $result = db_query($sql);
    if (db_num_rows($result) > 0) {
        return db_fetch_assoc($result);
    }
    return null;
}

/**
 * Generate next piece number for journal
 */
function generate_piece_number($journal_id) {
    $journal_id = intval($journal_id);

    // Get journal info
    $sql = "SELECT sequence_prefix, next_number FROM journals WHERE id = $journal_id";
    $result = db_query($sql);
    $journal = db_fetch_assoc($result);

    // Generate number
    $year = date('Y');
    $number = str_pad($journal['next_number'], 6, '0', STR_PAD_LEFT);
    $piece_number = $journal['sequence_prefix'] . $year . '-' . $number;

    // Increment sequence (legacy style - no transaction)
    $sql = "UPDATE journals SET next_number = next_number + 1 WHERE id = $journal_id";
    db_query($sql);

    return $piece_number;
}

/**
 * Validate double entry (debit = credit)
 */
function validate_double_entry($total_debit, $total_credit) {
    $diff = abs($total_debit - $total_credit);
    return $diff <= 0.01; // Tolerance of 0.01
}
