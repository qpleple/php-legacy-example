<?php
/**
 * Dashboard / Index page - Legacy style
 */

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/utils.php';

require_login();

$page_title = 'Tableau de bord';
require_once __DIR__ . '/header.php';

// Get company info
$company = get_company();

// Get current period
$today = date('Y-m-d');
$current_period = get_period_for_date($today);

// Statistics
// Draft entries
$sql = "SELECT COUNT(*) as count FROM entries WHERE status = 'draft'";
$result = db_query($sql);
$draft_count = db_fetch_assoc($result)['count'];

// Posted entries this month
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');
$sql = "SELECT COUNT(*) as count FROM entries WHERE status = 'posted' AND entry_date BETWEEN '$month_start' AND '$month_end'";
$result = db_query($sql);
$posted_this_month = db_fetch_assoc($result)['count'];

// Unmatched bank lines
$sql = "SELECT COUNT(*) as count FROM bank_statement_lines WHERE status = 'unmatched'";
$result = db_query($sql);
$unmatched_bank = db_fetch_assoc($result)['count'];

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
            <p>Non configure</p>
            <?php endif; ?>
        </div>

        <div class="stat-box">
            <h3>Periode courante</h3>
            <?php if ($current_period): ?>
            <p>
                <?php echo $current_period['status'] === 'open' ? 'Ouverte' : 'Verrouillee'; ?>
            </p>
            <?php else: ?>
            <p>Aucune periode active</p>
            <?php endif; ?>
        </div>

        <div class="stat-box">
            <h3>Brouillons</h3>
            <p class="big-number"><?php echo $draft_count; ?></p>
            <?php if ($draft_count > 0): ?>
            <a href="/modules/entries/list.php?status=draft">Voir</a>
            <?php endif; ?>
        </div>

        <div class="stat-box">
            <h3>Ecritures ce mois</h3>
            <p class="big-number"><?php echo $posted_this_month; ?></p>
        </div>

        <div class="stat-box">
            <h3>Lignes banque non pointees</h3>
            <p class="big-number"><?php echo $unmatched_bank; ?></p>
            <?php if ($unmatched_bank > 0): ?>
            <a href="/modules/bank/reconcile.php">Pointer</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="dashboard-section">
        <h3>Actions rapides</h3>
        <ul class="quick-actions">
            <li><a href="/modules/entries/edit.php" class="btn">Nouvelle ecriture</a></li>
            <li><a href="/modules/entries/import.php" class="btn">Import CSV</a></li>
            <li><a href="/modules/bank/import.php" class="btn">Import releve bancaire</a></li>
            <li><a href="/modules/reports/trial_balance.php" class="btn">Balance</a></li>
        </ul>
    </div>

    <div class="dashboard-section">
        <h3>Dernieres ecritures</h3>
        <?php if (count($recent_entries) > 0): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Journal</th>
                    <th>N&deg;</th>
                    <th>Libelle</th>
                    <th>Debit</th>
                    <th>Credit</th>
                    <th>Statut</th>
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
                            <?php echo $entry['piece_number'] ? h($entry['piece_number']) : '(brouillon)'; ?>
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
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>Aucune ecriture pour le moment.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
