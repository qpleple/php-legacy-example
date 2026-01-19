<?php
/**
 * AJAX endpoint for account autocomplete - Legacy style
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
$where = "(code LIKE '%$term_esc%' OR label LIKE '%$term_esc%') AND is_active = 1";

if ($type) {
    $type_esc = db_escape($type);
    $where .= " AND type = '$type_esc'";
}

$sql = "SELECT id, code, label, type FROM accounts WHERE $where ORDER BY code LIMIT 20";
$result = db_query($sql);

$accounts = array();
while ($row = db_fetch_assoc($result)) {
    $accounts[] = array(
        'id' => $row['id'],
        'value' => $row['code'] . ' - ' . $row['label'],
        'code' => $row['code'],
        'label' => $row['label'],
        'type' => $row['type']
    );
}

echo json_encode($accounts);
