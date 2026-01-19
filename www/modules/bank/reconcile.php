<?php
/**
 * Bank reconciliation page - Legacy style
 */

require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/utils.php';

require_login();
require_role('accountant');

$statement_id = intval(get('statement_id', 0));
$statement = null;
$bank_account = null;

// Handle matching
if (is_post() && post('action') === 'match') {
    csrf_verify();

    $line_id = intval(post('line_id'));
    $entry_line_id = intval(post('entry_line_id'));

    if ($line_id > 0 && $entry_line_id > 0) {
        $sql = "UPDATE bank_statement_lines SET matched_entry_line_id = $entry_line_id, status = 'matched'
                WHERE id = $line_id";
        db_query($sql);
        audit_log('MATCH', 'bank_statement_lines', $line_id, "Matched with entry_line $entry_line_id");
        set_flash('success', 'Ligne rapprochee.');
    }

    redirect('/modules/bank/reconcile.php?statement_id=' . intval(post('statement_id')));
}

// Handle unmatch
if (is_post() && post('action') === 'unmatch') {
    csrf_verify();

    $line_id = intval(post('line_id'));

    $sql = "UPDATE bank_statement_lines SET matched_entry_line_id = NULL, status = 'unmatched'
            WHERE id = $line_id";
    db_query($sql);
    audit_log('UNMATCH', 'bank_statement_lines', $line_id, "Unmatched");
    set_flash('success', 'Rapprochement annule.');

    redirect('/modules/bank/reconcile.php?statement_id=' . intval(post('statement_id')));
}

// Get statement info
if ($statement_id > 0) {
    $sql = "SELECT bs.*, ba.label as bank_label, ba.account_id
            FROM bank_statements bs
            LEFT JOIN bank_accounts ba ON bs.bank_account_id = ba.id
            WHERE bs.id = $statement_id";
    $result = db_query($sql);
    if (db_num_rows($result) > 0) {
        $statement = db_fetch_assoc($result);
    }
}

// Get all statements for dropdown
$sql = "SELECT bs.*, ba.label as bank_label
        FROM bank_statements bs
        LEFT JOIN bank_accounts ba ON bs.bank_account_id = ba.id
        ORDER BY bs.imported_at DESC";
$statements = db_fetch_all(db_query($sql));

$page_title = 'Rapprochement Bancaire';
require_once __DIR__ . '/../../header.php';
?>

<h2>Rapprochement Bancaire</h2>

<!-- Statement selector -->
<div class="filters mb-20">
    <form method="get" action="">
        <label>Releve:</label>
        <select name="statement_id" onchange="this.form.submit()">
            <option value="">-- Choisir un releve --</option>
            <?php foreach ($statements as $stmt): ?>
            <option value="<?php echo $stmt['id']; ?>" <?php echo $statement_id == $stmt['id'] ? 'selected' : ''; ?>>
                <?php echo h($stmt['bank_label'] . ' - ' . format_date($stmt['imported_at']) . ' (' . $stmt['source_filename'] . ')'); ?>
            </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<?php if ($statement): ?>
<?php
// Get statement lines
$sql = "SELECT bsl.*, el.id as matched_line_id, e.piece_number as matched_piece
        FROM bank_statement_lines bsl
        LEFT JOIN entry_lines el ON bsl.matched_entry_line_id = el.id
        LEFT JOIN entries e ON el.entry_id = e.id
        WHERE bsl.statement_id = $statement_id
        ORDER BY bsl.line_date, bsl.id";
$lines = db_fetch_all(db_query($sql));

// Calculate totals
$total_credit = 0;
$total_debit = 0;
$matched_credit = 0;
$matched_debit = 0;

foreach ($lines as $line) {
    if ($line['amount'] >= 0) {
        $total_credit += $line['amount'];
        if ($line['status'] === 'matched') $matched_credit += $line['amount'];
    } else {
        $total_debit += abs($line['amount']);
        if ($line['status'] === 'matched') $matched_debit += abs($line['amount']);
    }
}

// Get unmatched entry lines for matching
$account_id = $statement['account_id'];
$sql = "SELECT el.*, e.piece_number, e.entry_date, e.label as entry_label
        FROM entry_lines el
        INNER JOIN entries e ON el.entry_id = e.id
        WHERE el.account_id = $account_id
          AND e.status = 'posted'
          AND el.id NOT IN (SELECT matched_entry_line_id FROM bank_statement_lines WHERE matched_entry_line_id IS NOT NULL)
        ORDER BY e.entry_date DESC, el.id
        LIMIT 100";
$available_entries = db_fetch_all(db_query($sql));
?>

<div class="dashboard-row mb-20">
    <div class="stat-box">
        <h3>Total Credits</h3>
        <p class="big-number"><?php echo format_money($total_credit); ?></p>
    </div>
    <div class="stat-box">
        <h3>Total Debits</h3>
        <p class="big-number"><?php echo format_money($total_debit); ?></p>
    </div>
    <div class="stat-box">
        <h3>Rapproches (Credits)</h3>
        <p class="big-number"><?php echo format_money($matched_credit); ?></p>
    </div>
    <div class="stat-box">
        <h3>Rapproches (Debits)</h3>
        <p class="big-number"><?php echo format_money($matched_debit); ?></p>
    </div>
</div>

<h3>Lignes du releve</h3>
<table class="data-table">
    <thead>
        <tr>
            <th>Date</th>
            <th>Libelle</th>
            <th>Reference</th>
            <th>Montant</th>
            <th>Statut</th>
            <th>Piece rapprochee</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($lines as $line): ?>
        <tr class="bank-line <?php echo $line['status']; ?>"
            data-id="<?php echo $line['id']; ?>"
            data-amount="<?php echo $line['amount']; ?>">
            <td><?php echo format_date($line['line_date']); ?></td>
            <td><?php echo h($line['label']); ?></td>
            <td><?php echo h($line['ref']); ?></td>
            <td class="number" style="<?php echo $line['amount'] >= 0 ? 'color: green;' : 'color: red;'; ?>">
                <?php echo format_money($line['amount']); ?>
            </td>
            <td>
                <span class="status-<?php echo $line['status'] === 'matched' ? 'posted' : 'draft'; ?>">
                    <?php echo $line['status'] === 'matched' ? 'Rapproche' : 'Non rapproche'; ?>
                </span>
            </td>
            <td>
                <?php if ($line['matched_piece']): ?>
                <?php echo h($line['matched_piece']); ?>
                <?php else: ?>
                -
                <?php endif; ?>
            </td>
            <td>
                <?php if ($line['status'] === 'unmatched'): ?>
                <form method="post" action="" style="display: inline;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="match">
                    <input type="hidden" name="statement_id" value="<?php echo $statement_id; ?>">
                    <input type="hidden" name="line_id" value="<?php echo $line['id']; ?>">
                    <select name="entry_line_id" required style="width: 200px;">
                        <option value="">-- Choisir ecriture --</option>
                        <?php foreach ($available_entries as $entry): ?>
                        <?php
                        $entry_amount = $entry['debit'] > 0 ? -$entry['debit'] : $entry['credit'];
                        // Show entries with similar amount
                        if (abs(abs($entry_amount) - abs($line['amount'])) < 0.01 || true):
                        ?>
                        <option value="<?php echo $entry['id']; ?>">
                            <?php echo h($entry['piece_number'] . ' ' . format_date($entry['entry_date']) .
                                        ' D:' . $entry['debit'] . ' C:' . $entry['credit']); ?>
                        </option>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-small btn-success">Rapprocher</button>
                </form>
                <?php else: ?>
                <form method="post" action="" style="display: inline;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="unmatch">
                    <input type="hidden" name="statement_id" value="<?php echo $statement_id; ?>">
                    <input type="hidden" name="line_id" value="<?php echo $line['id']; ?>">
                    <button type="submit" class="btn btn-small btn-danger confirm-action"
                            data-confirm="Annuler ce rapprochement ?">Annuler</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php else: ?>
<p>Veuillez selectionner un releve ou <a href="/modules/bank/import.php">importer un nouveau releve</a>.</p>
<?php endif; ?>

<?php require_once __DIR__ . '/../../footer.php'; ?>
