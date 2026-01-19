<?php
/**
 * AJAX endpoint for third party autocomplete - Legacy style
 */

require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/utils.php';

header('Content-Type: application/json');

if (!auth_is_logged_in()) {
    echo json_encode(array('error' => 'Not authenticated'));
    exit;
}

$term = isset($_GET['term']) ? trim($_GET['term']) : '';
$type = isset($_GET['type']) ? trim($_GET['type']) : '';

if (strlen($term) < 1) {
    echo json_encode(array());
    exit;
}

$term_esc = db_escape($term);
$where = "name LIKE '%$term_esc%'";

if ($type) {
    $type_esc = db_escape($type);
    $where .= " AND type = '$type_esc'";
}

$sql = "SELECT tp.id, tp.name, tp.type, a.code as account_code
        FROM third_parties tp
        LEFT JOIN accounts a ON tp.account_id = a.id
        WHERE $where ORDER BY tp.name LIMIT 20";
$result = db_query($sql);

$third_parties = array();
while ($row = db_fetch_assoc($result)) {
    $third_parties[] = array(
        'id' => $row['id'],
        'value' => $row['name'],
        'name' => $row['name'],
        'type' => $row['type'],
        'account_code' => $row['account_code']
    );
}

echo json_encode($third_parties);
