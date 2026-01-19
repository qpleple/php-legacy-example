<?php
/**
 * Trial Balance report - Legacy style
 */

$page_title = 'Balance';
require_once __DIR__ . '/../../header.php';
require_role('accountant');

// Filters
$period_id = get('period_id', '');

// Get periods for filter
$periods = get_periods();

// Build query conditions
$where = "e.status = 'posted'";
if ($period_id) {
    $period_id = intval($period_id);
    $where .= " AND e.period_id = $period_id";
}

// Get trial balance data
$sql = "SELECT a.code, a.label, a.type,
               SUM(el.debit) as total_debit,
               SUM(el.credit) as total_credit
        FROM accounts a
        LEFT JOIN entry_lines el ON el.account_id = a.id
        LEFT JOIN entries e ON el.entry_id = e.id AND $where
        GROUP BY a.id, a.code, a.label, a.type
        HAVING total_debit > 0 OR total_credit > 0
        ORDER BY a.code";
$balance = db_fetch_all(db_query($sql));

// Calculate totals
$grand_debit = 0;
$grand_credit = 0;
$grand_solde_debit = 0;
$grand_solde_credit = 0;

foreach ($balance as &$row) {
    $row['solde'] = $row['total_debit'] - $row['total_credit'];
    $row['solde_debit'] = $row['solde'] > 0 ? $row['solde'] : 0;
    $row['solde_credit'] = $row['solde'] < 0 ? abs($row['solde']) : 0;

    $grand_debit += $row['total_debit'];
    $grand_credit += $row['total_credit'];
    $grand_solde_debit += $row['solde_debit'];
    $grand_solde_credit += $row['solde_credit'];
}
unset($row);
?>

<h2>Balance Generale</h2>

<!-- Filters -->
<div class="filters">
    <form method="get" action="">
        <label>Periode:</label>
        <select name="period_id">
            <option value="">Toutes periodes</option>
            <?php foreach ($periods as $p): ?>
            <option value="<?php echo $p['id']; ?>" <?php echo get('period_id') == $p['id'] ? 'selected' : ''; ?>>
                <?php echo format_date($p['start_date']) . ' - ' . format_date($p['end_date']); ?>
            </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn btn-small">Afficher</button>
        <a href="/modules/reports/trial_balance.php" class="btn btn-small">Reset</a>
        <a href="/modules/reports/pdf_trial_balance.php?<?php echo http_build_query($_GET); ?>" class="btn btn-small" target="_blank">PDF</a>
        <button type="button" class="btn btn-small btn-print">Imprimer</button>
    </form>
</div>

<!-- Summary -->
<div class="dashboard-row mb-20">
    <div class="stat-box">
        <h3>Total Mouvements Debit</h3>
        <p class="big-number"><?php echo format_money($grand_debit); ?></p>
    </div>
    <div class="stat-box">
        <h3>Total Mouvements Credit</h3>
        <p class="big-number"><?php echo format_money($grand_credit); ?></p>
    </div>
    <div class="stat-box">
        <h3>Total Soldes Debiteurs</h3>
        <p class="big-number"><?php echo format_money($grand_solde_debit); ?></p>
    </div>
    <div class="stat-box">
        <h3>Total Soldes Crediteurs</h3>
        <p class="big-number"><?php echo format_money($grand_solde_credit); ?></p>
    </div>
</div>

<!-- Balance table -->
<?php if (count($balance) > 0): ?>
<table class="data-table">
    <thead>
        <tr>
            <th>Compte</th>
            <th>Libelle</th>
            <th>Total Debit</th>
            <th>Total Credit</th>
            <th>Solde Debit</th>
            <th>Solde Credit</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($balance as $row): ?>
        <tr>
            <td><?php echo h($row['code']); ?></td>
            <td><?php echo h($row['label']); ?></td>
            <td class="number"><?php echo format_money($row['total_debit']); ?></td>
            <td class="number"><?php echo format_money($row['total_credit']); ?></td>
            <td class="number"><?php echo $row['solde_debit'] > 0 ? format_money($row['solde_debit']) : ''; ?></td>
            <td class="number"><?php echo $row['solde_credit'] > 0 ? format_money($row['solde_credit']) : ''; ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr class="report-totals">
            <td colspan="2">TOTAUX</td>
            <td class="number"><?php echo format_money($grand_debit); ?></td>
            <td class="number"><?php echo format_money($grand_credit); ?></td>
            <td class="number"><?php echo format_money($grand_solde_debit); ?></td>
            <td class="number"><?php echo format_money($grand_solde_credit); ?></td>
        </tr>
    </tfoot>
</table>

<p class="mt-10">
    <?php if (abs($grand_solde_debit - $grand_solde_credit) <= 0.01): ?>
    <span style="color: green;">Balance equilibree</span>
    <?php else: ?>
    <span style="color: red;">Attention: ecart de <?php echo format_money(abs($grand_solde_debit - $grand_solde_credit)); ?></span>
    <?php endif; ?>
</p>
<?php else: ?>
<p>Aucune donnee pour les criteres selectionnes.</p>
<?php endif; ?>

<?php require_once __DIR__ . '/../../footer.php'; ?>
