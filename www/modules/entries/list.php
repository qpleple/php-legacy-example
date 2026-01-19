<?php
/**
 * Entries list page - Legacy style
 */

require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/utils.php';

require_login();
require_role('accountant');

// Handle delete draft
if (is_post() && post('action') === 'delete') {
    csrf_verify();
    $id = intval(post('id'));

    // Only delete drafts
    $sql = "SELECT status FROM entries WHERE id = $id";
    $result = db_query($sql);
    $entry = db_fetch_assoc($result);

    if ($entry && $entry['status'] === 'draft') {
        db_query("DELETE FROM entry_lines WHERE entry_id = $id");
        db_query("DELETE FROM attachments WHERE entry_id = $id");
        db_query("DELETE FROM entries WHERE id = $id");
        audit_log('DELETE', 'entries', $id, 'Draft entry deleted');
        set_flash('success', 'Brouillon supprime.');
    } else {
        set_flash('error', 'Impossible de supprimer une piece validee.');
    }
    redirect('/modules/entries/list.php');
}

// Handle duplicate
if (is_post() && post('action') === 'duplicate') {
    csrf_verify();
    $id = intval(post('id'));

    // Get original entry
    $sql = "SELECT * FROM entries WHERE id = $id";
    $entry = db_fetch_assoc(db_query($sql));

    if ($entry) {
        // Create new entry as draft
        $journal_id = $entry['journal_id'];
        $label = db_escape($entry['label'] . ' (copie)');
        $entry_date = date('Y-m-d');
        $user_id = auth_user_id();

        // Get period for new date
        $period = get_period_for_date($entry_date);
        $period_id = $period ? $period['id'] : 'NULL';

        $sql = "INSERT INTO entries (journal_id, entry_date, period_id, label, status, total_debit, total_credit, created_by, created_at)
                VALUES ($journal_id, '$entry_date', $period_id, '$label', 'draft', {$entry['total_debit']}, {$entry['total_credit']}, $user_id, NOW())";
        db_query($sql);
        $new_id = db_insert_id();

        // Copy lines
        $sql = "SELECT * FROM entry_lines WHERE entry_id = $id ORDER BY line_no";
        $lines = db_fetch_all(db_query($sql));

        foreach ($lines as $line) {
            $account_id = $line['account_id'];
            $third_party_id = $line['third_party_id'] ? $line['third_party_id'] : 'NULL';
            $line_label = db_escape($line['label']);
            $debit = $line['debit'];
            $credit = $line['credit'];
            $vat_rate_id = $line['vat_rate_id'] ? $line['vat_rate_id'] : 'NULL';
            $vat_base = $line['vat_base'] ? $line['vat_base'] : 'NULL';
            $vat_amount = $line['vat_amount'] ? $line['vat_amount'] : 'NULL';
            $due_date = $line['due_date'] ? "'" . $line['due_date'] . "'" : 'NULL';

            $sql = "INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit, vat_rate_id, vat_base, vat_amount, due_date)
                    VALUES ($new_id, {$line['line_no']}, $account_id, $third_party_id, '$line_label', $debit, $credit, $vat_rate_id, $vat_base, $vat_amount, $due_date)";
            db_query($sql);
        }

        audit_log('CREATE', 'entries', $new_id, "Entry duplicated from $id");
        set_flash('success', 'Piece dupliquee en brouillon.');
        redirect('/modules/entries/edit.php?id=' . $new_id);
    }
}

// Filters
$journal_id = get('journal_id', '');
$period_id = get('period_id', '');
$status = get('status', '');
$search = get('search', '');

// Build query
$where = "1=1";
if ($journal_id) {
    $journal_id = intval($journal_id);
    $where .= " AND e.journal_id = $journal_id";
}
if ($period_id) {
    $period_id = intval($period_id);
    $where .= " AND e.period_id = $period_id";
}
if ($status) {
    $status_esc = db_escape($status);
    $where .= " AND e.status = '$status_esc'";
}
if ($search) {
    $search_esc = db_escape($search);
    $where .= " AND (e.label LIKE '%$search_esc%' OR e.piece_number LIKE '%$search_esc%')";
}

// Pagination
$sql = "SELECT COUNT(*) as count FROM entries e WHERE $where";
$total = db_fetch_assoc(db_query($sql))['count'];
$page = max(1, intval(get('page', 1)));
$pagination = paginate($total, $page, 30);

// Get entries
$sql = "SELECT e.*, j.code as journal_code, j.label as journal_label, u.username as created_by_name
        FROM entries e
        LEFT JOIN journals j ON e.journal_id = j.id
        LEFT JOIN users u ON e.created_by = u.id
        WHERE $where
        ORDER BY e.entry_date DESC, e.id DESC
        LIMIT {$pagination['offset']}, {$pagination['per_page']}";
$entries = db_fetch_all(db_query($sql));

// Get journals and periods for filters
$journals = get_journals();
$periods = get_periods();

$page_title = 'Liste des Pieces';
require_once __DIR__ . '/../../header.php';
?>

<h2>Liste des Pieces Comptables</h2>

<div class="mb-10">
    <a href="/modules/entries/edit.php" class="btn btn-primary">Nouvelle piece</a>
    <a href="/modules/entries/import.php" class="btn">Import CSV</a>
</div>

<!-- Filters -->
<div class="filters">
    <form method="get" action="">
        <label>Journal:</label>
        <select name="journal_id">
            <option value="">Tous</option>
            <?php foreach ($journals as $j): ?>
            <option value="<?php echo $j['id']; ?>" <?php echo get('journal_id') == $j['id'] ? 'selected' : ''; ?>>
                <?php echo h($j['code'] . ' - ' . $j['label']); ?>
            </option>
            <?php endforeach; ?>
        </select>

        <label>Periode:</label>
        <select name="period_id">
            <option value="">Toutes</option>
            <?php foreach ($periods as $p): ?>
            <option value="<?php echo $p['id']; ?>" <?php echo get('period_id') == $p['id'] ? 'selected' : ''; ?>>
                <?php echo format_date($p['start_date']) . ' - ' . format_date($p['end_date']); ?>
            </option>
            <?php endforeach; ?>
        </select>

        <label>Statut:</label>
        <select name="status">
            <option value="">Tous</option>
            <option value="draft" <?php echo get('status') == 'draft' ? 'selected' : ''; ?>>Brouillon</option>
            <option value="posted" <?php echo get('status') == 'posted' ? 'selected' : ''; ?>>Valide</option>
        </select>

        <label>Recherche:</label>
        <input type="text" name="search" value="<?php echo h(get('search')); ?>" placeholder="Libelle ou numero">

        <button type="submit" class="btn btn-small">Filtrer</button>
        <a href="/modules/entries/list.php" class="btn btn-small">Reset</a>
    </form>
</div>

<!-- Entries table -->
<?php if (count($entries) > 0): ?>
<table class="data-table">
    <thead>
        <tr>
            <th>Date</th>
            <th>Journal</th>
            <th>N&deg; Piece</th>
            <th>Libelle</th>
            <th>Debit</th>
            <th>Credit</th>
            <th>Statut</th>
            <th>Cree par</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($entries as $entry): ?>
        <tr>
            <td><?php echo format_date($entry['entry_date']); ?></td>
            <td><?php echo h($entry['journal_code']); ?></td>
            <td>
                <a href="/modules/entries/edit.php?id=<?php echo $entry['id']; ?>">
                    <?php echo $entry['piece_number'] ? h($entry['piece_number']) : '<em>(brouillon)</em>'; ?>
                </a>
            </td>
            <td><?php echo h($entry['label']); ?></td>
            <td class="number"><?php echo format_money($entry['total_debit']); ?></td>
            <td class="number"><?php echo format_money($entry['total_credit']); ?></td>
            <td>
                <span class="status-<?php echo $entry['status']; ?>">
                    <?php echo $entry['status'] === 'draft' ? 'Brouillon' : 'Valide'; ?>
                </span>
            </td>
            <td><?php echo h($entry['created_by_name']); ?></td>
            <td class="actions">
                <a href="/modules/entries/edit.php?id=<?php echo $entry['id']; ?>" class="btn btn-small">
                    <?php echo $entry['status'] === 'draft' ? 'Editer' : 'Voir'; ?>
                </a>
                <a href="/modules/entries/pdf.php?id=<?php echo $entry['id']; ?>" class="btn btn-small" target="_blank">PDF</a>
                <form method="post" action="" style="display: inline;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="duplicate">
                    <input type="hidden" name="id" value="<?php echo $entry['id']; ?>">
                    <button type="submit" class="btn btn-small">Dupliquer</button>
                </form>
                <?php if ($entry['status'] === 'draft'): ?>
                <form method="post" action="" style="display: inline;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo $entry['id']; ?>">
                    <button type="submit" class="btn btn-small btn-danger confirm-action"
                            data-confirm="Supprimer ce brouillon ?">Supprimer</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php
$filter_params = 'journal_id=' . urlencode(get('journal_id')) .
    '&period_id=' . urlencode(get('period_id')) .
    '&status=' . urlencode(get('status')) .
    '&search=' . urlencode(get('search'));
echo pagination_links($pagination, '?' . $filter_params);
?>

<?php else: ?>
<p>Aucune piece trouvee.</p>
<?php endif; ?>

<p>Total: <?php echo $total; ?> pieces</p>

<?php require_once __DIR__ . '/../../footer.php'; ?>
