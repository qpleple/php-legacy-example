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
    $html = preg_replace('/(SELECT|INSERT|UPDATE|DELETE|DROP|UNION)\s/i', '[$1] ', $html);

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
        $html .= '<a href="' . $base_url . '&page=' . ($pagination['page'] - 1) . '">&laquo; Precedent</a> ';
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
 * Get period for a date
 */
function get_period_for_date($date) {
    $date = db_escape($date);
    $sql = "SELECT id, status FROM periods WHERE '$date' BETWEEN start_date AND end_date LIMIT 1";
    $result = db_query($sql);

    if (db_num_rows($result) > 0) {
        return db_fetch_assoc($result);
    }

    return null;
}

/**
 * Check if period is open
 */
function is_period_open($period_id) {
    $period_id = intval($period_id);
    $sql = "SELECT status FROM periods WHERE id = $period_id";
    $result = db_query($sql);

    if (db_num_rows($result) > 0) {
        $row = db_fetch_assoc($result);
        return $row['status'] === 'open';
    }

    return false;
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
 * Get all third parties
 */
function get_third_parties($type = null) {
    $sql = "SELECT tp.*, a.code as account_code FROM third_parties tp
            LEFT JOIN accounts a ON tp.account_id = a.id WHERE 1=1";
    if ($type !== null) {
        $type = db_escape($type);
        $sql .= " AND tp.type = '$type'";
    }
    $sql .= " ORDER BY tp.name";

    return db_fetch_all(db_query($sql));
}

/**
 * Get all VAT rates
 */
function get_vat_rates($active_only = true) {
    $sql = "SELECT * FROM vat_rates";
    if ($active_only) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY rate DESC";

    return db_fetch_all(db_query($sql));
}

/**
 * Get all periods
 */
function get_periods() {
    $sql = "SELECT * FROM periods ORDER BY start_date";
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

/**
 * Handle file upload
 */
function handle_upload($file_input, $entry_id) {
    if (!isset($_FILES[$file_input]) || $_FILES[$file_input]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $file = $_FILES[$file_input];

    // Check size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        return array('error' => 'Fichier trop volumineux (max 5MB)');
    }

    // Check extension
    $allowed = array('pdf', 'jpg', 'jpeg', 'png', 'gif');
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        return array('error' => 'Type de fichier non autorise');
    }

    // Prevent PHP file upload
    if (strtolower($ext) === 'php') {
        return array('error' => 'Type de fichier interdit');
    }

    // Generate filename
    $filename = 'entry_' . $entry_id . '_' . time() . '.' . $ext;
    $dest_path = '/var/www/html/uploads/' . $filename;

    if (move_uploaded_file($file['tmp_name'], $dest_path)) {
        return array(
            'filename' => $file['name'],
            'stored_path' => $filename
        );
    }

    return array('error' => 'Erreur lors de l\'upload');
}
