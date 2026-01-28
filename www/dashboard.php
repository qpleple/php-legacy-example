<?php
/**
 * Dashboard - Legacy style (Simplified)
 */

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/utils.php';

require_login();

$page_title = 'Tableau de bord';
require_once __DIR__ . '/header.php';

// Get company info
$company = get_company();

// Statistics
$sql = "SELECT COUNT(*) as count FROM entries";
$result = db_query($sql);
$total_entries = db_fetch_assoc($result)['count'];

$month_start = date('Y-m-01');
$month_end = date('Y-m-t');
$sql = "SELECT COUNT(*) as count FROM entries WHERE entry_date BETWEEN '$month_start' AND '$month_end'";
$result = db_query($sql);
$entries_this_month = db_fetch_assoc($result)['count'];

// Recent entries
$sql = "SELECT e.*, j.code as journal_code, u.username as created_by_name
        FROM entries e
        LEFT JOIN journals j ON e.journal_id = j.id
        LEFT JOIN users u ON e.created_by = u.id
        ORDER BY e.created_at DESC LIMIT 10";
$recent_entries = db_fetch_all(db_query($sql));
?>

<h2>Tableau de bord</h2>

<div class="dashboard">
    <div class="dashboard-row">
        <div class="stat-box">
            <h3>Exercice</h3>
            <?php if ($company): ?>
            <p><?php echo format_date($company['fiscal_year_start']); ?> - <?php echo format_date($company['fiscal_year_end']); ?></p>
            <?php else: ?>
            <p>Non configuré</p>
            <?php endif; ?>
        </div>

        <div class="stat-box">
            <h3>Total écritures</h3>
            <p class="big-number"><?php echo $total_entries; ?></p>
        </div>

        <div class="stat-box">
            <h3>Ce mois</h3>
            <p class="big-number"><?php echo $entries_this_month; ?></p>
        </div>
    </div>

    <div class="dashboard-section">
        <h3>Actions rapides</h3>
        <ul class="quick-actions">
            <li><a href="/modules/entries/edit.php" class="btn">Nouvelle écriture</a></li>
            <li><a href="/modules/reports/trial_balance.php" class="btn">Balance</a></li>
            <li><a href="/modules/reports/ledger.php" class="btn">Grand livre</a></li>
        </ul>
    </div>

    <div class="dashboard-section">
        <h3>Dernières écritures</h3>
        <?php if (count($recent_entries) > 0): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Journal</th>
                    <th>N° Pièce</th>
                    <th>Libellé</th>
                    <th>Débit</th>
                    <th>Crédit</th>
                    <th>Par</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_entries as $entry): ?>
                <tr>
                    <td><?php echo format_date($entry['entry_date']); ?></td>
                    <td><?php echo h($entry['journal_code']); ?></td>
                    <td>
                        <a href="/modules/entries/edit.php?id=<?php echo $entry['id']; ?>">
                            <?php echo h($entry['piece_number']); ?>
                        </a>
                    </td>
                    <td><?php echo h($entry['label']); ?></td>
                    <td class="number"><?php echo format_money($entry['total_debit']); ?></td>
                    <td class="number"><?php echo format_money($entry['total_credit']); ?></td>
                    <td><?php echo h($entry['created_by_name']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>Aucune écriture pour le moment.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
