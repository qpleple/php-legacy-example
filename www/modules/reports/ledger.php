<?php
/**
 * General Ledger report - Legacy style
 */

$page_title = 'Grand Livre';
require_once __DIR__ . '/../../header.php';
require_login();

// Filters
$account_from = get('account_from', '');
$account_to = get('account_to', '');
$journal_id = get('journal_id', '');

// Get journals for filters
$journals = get_journals();

// Build query conditions
$where = "e.status = 'posted'";
if ($account_from) {
    $account_from_esc = db_escape($account_from);
    $where .= " AND a.code >= '$account_from_esc'";
}
if ($account_to) {
    $account_to_esc = db_escape($account_to);
    $where .= " AND a.code <= '$account_to_esc'";
}
if ($journal_id) {
    $journal_id = intval($journal_id);
    $where .= " AND e.journal_id = $journal_id";
}

// Get ledger data grouped by account
$sql = "SELECT a.id as account_id, a.code as account_code, a.label as account_label,
               el.id as line_id, e.entry_date, e.piece_number, j.code as journal_code,
               el.label as line_label, el.debit, el.credit
        FROM entry_lines el
        INNER JOIN entries e ON el.entry_id = e.id
        INNER JOIN accounts a ON el.account_id = a.id
        LEFT JOIN journals j ON e.journal_id = j.id
        WHERE $where
        ORDER BY a.code, e.entry_date, e.id, el.line_no";
$result = db_query($sql);

// Group by account
$ledger = array();
while ($row = db_fetch_assoc($result)) {
    $acc_code = $row['account_code'];
    if (!isset($ledger[$acc_code])) {
        $ledger[$acc_code] = array(
            'code' => $row['account_code'],
            'label' => $row['account_label'],
            'lines' => array(),
            'total_debit' => 0,
            'total_credit' => 0
        );
    }
    $ledger[$acc_code]['lines'][] = $row;
    $ledger[$acc_code]['total_debit'] += $row['debit'];
    $ledger[$acc_code]['total_credit'] += $row['credit'];
}

// Calculate grand totals
$grand_debit = 0;
$grand_credit = 0;
foreach ($ledger as $acc) {
    $grand_debit += $acc['total_debit'];
    $grand_credit += $acc['total_credit'];
}
?>

<h2>Grand Livre</h2>

<!-- Filters -->
<div class="filters">
    <form method="get" action="">
        <label>Compte de:</label>
        <input type="text" name="account_from" value="<?php echo h($account_from); ?>" style="width: 80px;" placeholder="101000">

        <label>à :</label>
        <input type="text" name="account_to" value="<?php echo h($account_to); ?>" style="width: 80px;" placeholder="999999">

        <label>Journal:</label>
        <select name="journal_id">
            <option value="">Tous</option>
            <?php foreach ($journals as $j): ?>
            <option value="<?php echo $j['id']; ?>" <?php echo get('journal_id') == $j['id'] ? 'selected' : ''; ?>>
                <?php echo h($j['code']); ?>
            </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn btn-small">Afficher</button>
        <a href="/modules/reports/ledger.php" class="btn btn-small">Reset</a>
    </form>
</div>

<!-- Grand totals -->
<div class="dashboard-row mb-20">
    <div class="stat-box">
        <h3>Total Débit</h3>
        <p class="big-number"><?php echo format_money($grand_debit); ?></p>
    </div>
    <div class="stat-box">
        <h3>Total Crédit</h3>
        <p class="big-number"><?php echo format_money($grand_credit); ?></p>
    </div>
    <div class="stat-box">
        <h3>Nb Comptes</h3>
        <p class="big-number"><?php echo count($ledger); ?></p>
    </div>
</div>

<!-- Ledger by account -->
<?php if (count($ledger) > 0): ?>
    <?php foreach ($ledger as $acc): ?>
    <div class="mb-20">
        <h3><?php echo h($acc['code'] . ' - ' . $acc['label']); ?></h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Journal</th>
                    <th>N° Pièce</th>
                    <th>Libellé</th>
                    <th>Débit</th>
                    <th>Crédit</th>
                    <th>Solde</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $running_balance = 0;
                foreach ($acc['lines'] as $line):
                    $running_balance += $line['debit'] - $line['credit'];
                ?>
                <tr>
                    <td><?php echo format_date($line['entry_date']); ?></td>
                    <td><?php echo h($line['journal_code']); ?></td>
                    <td><?php echo h($line['piece_number']); ?></td>
                    <td><?php echo h($line['line_label']); ?></td>
                    <td class="number"><?php echo $line['debit'] > 0 ? format_money($line['debit']) : ''; ?></td>
                    <td class="number"><?php echo $line['credit'] > 0 ? format_money($line['credit']) : ''; ?></td>
                    <td class="number"><?php echo format_money($running_balance); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="report-totals">
                    <td colspan="4">Total <?php echo h($acc['code']); ?></td>
                    <td class="number"><?php echo format_money($acc['total_debit']); ?></td>
                    <td class="number"><?php echo format_money($acc['total_credit']); ?></td>
                    <td class="number"><?php echo format_money($acc['total_debit'] - $acc['total_credit']); ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endforeach; ?>
<?php else: ?>
<p>Aucune donnée pour les critères sélectionnés.</p>
<?php endif; ?>

<?php require_once __DIR__ . '/../../footer.php'; ?>
