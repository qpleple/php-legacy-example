<?php
/**
 * VAT Summary report - Legacy style
 */

$page_title = 'Synthese TVA';
require_once __DIR__ . '/../../header.php';
require_role('accountant');

// Filters
$period_id = get('period_id', '');

// Get periods for filter
$periods = get_periods();

// Build query conditions
$where = "e.status = 'posted'";
if ($period_id) {
    $period_id_int = intval($period_id);
    $where .= " AND e.period_id = $period_id_int";
}

// Get VAT rates
$vat_rates = get_vat_rates(false); // Include inactive for historical data

// Calculate VAT for each rate
$vat_data = array();
foreach ($vat_rates as $vr) {
    $vat_id = $vr['id'];

    // VAT collected (sales) - credit on VAT account
    $sql = "SELECT SUM(el.vat_amount) as total
            FROM entry_lines el
            INNER JOIN entries e ON el.entry_id = e.id
            INNER JOIN accounts a ON el.account_id = a.id
            WHERE $where
              AND el.vat_rate_id = $vat_id
              AND el.credit > 0
              AND a.code LIKE '7%'"; // Revenue accounts
    $result = db_query($sql);
    $collected = db_fetch_assoc($result)['total'] ?: 0;

    // VAT deductible (purchases) - debit on VAT account
    $sql = "SELECT SUM(el.vat_amount) as total
            FROM entry_lines el
            INNER JOIN entries e ON el.entry_id = e.id
            INNER JOIN accounts a ON el.account_id = a.id
            WHERE $where
              AND el.vat_rate_id = $vat_id
              AND el.debit > 0
              AND a.code LIKE '6%'"; // Expense accounts
    $result = db_query($sql);
    $deductible = db_fetch_assoc($result)['total'] ?: 0;

    // VAT base amounts
    $sql = "SELECT SUM(el.vat_base) as total
            FROM entry_lines el
            INNER JOIN entries e ON el.entry_id = e.id
            INNER JOIN accounts a ON el.account_id = a.id
            WHERE $where
              AND el.vat_rate_id = $vat_id
              AND el.credit > 0
              AND a.code LIKE '7%'";
    $result = db_query($sql);
    $base_collected = db_fetch_assoc($result)['total'] ?: 0;

    $sql = "SELECT SUM(el.vat_base) as total
            FROM entry_lines el
            INNER JOIN entries e ON el.entry_id = e.id
            INNER JOIN accounts a ON el.account_id = a.id
            WHERE $where
              AND el.vat_rate_id = $vat_id
              AND el.debit > 0
              AND a.code LIKE '6%'";
    $result = db_query($sql);
    $base_deductible = db_fetch_assoc($result)['total'] ?: 0;

    $vat_data[] = array(
        'label' => $vr['label'],
        'rate' => $vr['rate'],
        'base_collected' => $base_collected,
        'collected' => $collected,
        'base_deductible' => $base_deductible,
        'deductible' => $deductible,
        'balance' => $collected - $deductible
    );
}

// Calculate totals
$total_base_collected = 0;
$total_collected = 0;
$total_base_deductible = 0;
$total_deductible = 0;

foreach ($vat_data as $vd) {
    $total_base_collected += $vd['base_collected'];
    $total_collected += $vd['collected'];
    $total_base_deductible += $vd['base_deductible'];
    $total_deductible += $vd['deductible'];
}

$total_balance = $total_collected - $total_deductible;
?>

<h2>Synthese TVA</h2>

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
        <a href="/modules/reports/vat_summary.php" class="btn btn-small">Reset</a>
        <a href="/modules/reports/pdf_vat.php?<?php echo http_build_query($_GET); ?>" class="btn btn-small" target="_blank">PDF</a>
    </form>
</div>

<!-- Summary boxes -->
<div class="dashboard-row mb-20">
    <div class="stat-box">
        <h3>TVA Collectee</h3>
        <p class="big-number"><?php echo format_money($total_collected); ?></p>
    </div>
    <div class="stat-box">
        <h3>TVA Deductible</h3>
        <p class="big-number"><?php echo format_money($total_deductible); ?></p>
    </div>
    <div class="stat-box">
        <h3>TVA a reverser</h3>
        <p class="big-number" style="<?php echo $total_balance >= 0 ? 'color: red;' : 'color: green;'; ?>">
            <?php echo format_money($total_balance); ?>
        </p>
    </div>
</div>

<!-- VAT by rate -->
<h3>Detail par taux</h3>
<table class="data-table">
    <thead>
        <tr>
            <th>Taux</th>
            <th>Base HT Ventes</th>
            <th>TVA Collectee</th>
            <th>Base HT Achats</th>
            <th>TVA Deductible</th>
            <th>Solde</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($vat_data as $vd): ?>
        <?php if ($vd['collected'] > 0 || $vd['deductible'] > 0): ?>
        <tr>
            <td><?php echo h($vd['label']); ?> (<?php echo number_format($vd['rate'], 2); ?>%)</td>
            <td class="number"><?php echo format_money($vd['base_collected']); ?></td>
            <td class="number"><?php echo format_money($vd['collected']); ?></td>
            <td class="number"><?php echo format_money($vd['base_deductible']); ?></td>
            <td class="number"><?php echo format_money($vd['deductible']); ?></td>
            <td class="number" style="<?php echo $vd['balance'] >= 0 ? 'color: red;' : 'color: green;'; ?>">
                <?php echo format_money($vd['balance']); ?>
            </td>
        </tr>
        <?php endif; ?>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr class="report-totals">
            <td>TOTAUX</td>
            <td class="number"><?php echo format_money($total_base_collected); ?></td>
            <td class="number"><?php echo format_money($total_collected); ?></td>
            <td class="number"><?php echo format_money($total_base_deductible); ?></td>
            <td class="number"><?php echo format_money($total_deductible); ?></td>
            <td class="number" style="<?php echo $total_balance >= 0 ? 'color: red;' : 'color: green;'; ?>">
                <?php echo format_money($total_balance); ?>
            </td>
        </tr>
    </tfoot>
</table>

<div class="mt-20">
    <p><strong>Note:</strong> Un solde positif indique une TVA a reverser a l'Etat.
    Un solde negatif indique un credit de TVA.</p>
</div>

<?php require_once __DIR__ . '/../../footer.php'; ?>
