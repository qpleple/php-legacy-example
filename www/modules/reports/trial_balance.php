<?php
/**
 * Trial Balance report - Legacy style
 */

$page_title = 'Balance';
require_once __DIR__ . '/../../header.php';
require_login();

// Build query conditions
$where = "e.status = 'posted'";

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

<h2>Balance Générale</h2>

<!-- Summary -->
<div class="dashboard-row mb-20">
    <div class="stat-box">
        <h3>Total Mouvements Débit</h3>
        <p class="big-number"><?php echo format_money($grand_debit); ?></p>
    </div>
    <div class="stat-box">
        <h3>Total Mouvements Crédit</h3>
        <p class="big-number"><?php echo format_money($grand_credit); ?></p>
    </div>
    <div class="stat-box">
        <h3>Total Soldes Débiteurs</h3>
        <p class="big-number"><?php echo format_money($grand_solde_debit); ?></p>
    </div>
    <div class="stat-box">
        <h3>Total Soldes Créditeurs</h3>
        <p class="big-number"><?php echo format_money($grand_solde_credit); ?></p>
    </div>
</div>

<!-- Balance table -->
<?php if (count($balance) > 0): ?>
<table class="data-table">
    <thead>
        <tr>
            <th>Compte</th>
            <th>Libellé</th>
            <th>Total Débit</th>
            <th>Total Crédit</th>
            <th>Solde Débit</th>
            <th>Solde Crédit</th>
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
    <span style="color: green;">Balance équilibrée</span>
    <?php else: ?>
    <span style="color: red;">Attention : écart de <?php echo format_money(abs($grand_solde_debit - $grand_solde_credit)); ?></span>
    <?php endif; ?>
</p>
<?php else: ?>
<p>Aucune donnée pour les critères sélectionnés.</p>
<?php endif; ?>

<?php require_once __DIR__ . '/../../footer.php'; ?>
