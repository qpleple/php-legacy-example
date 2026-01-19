<?php
/**
 * Journal report - Legacy style
 */

$page_title = 'Journal';
require_once __DIR__ . '/../../header.php';
require_role('accountant');

// Filters
$journal_id = get('journal_id', '');
$period_id = get('period_id', '');

// Get journals and periods for filters
$journals = get_journals();
$periods = get_periods();

// Build query conditions
$where = "e.status = 'posted'";
if ($journal_id) {
    $journal_id_int = intval($journal_id);
    $where .= " AND e.journal_id = $journal_id_int";
}
if ($period_id) {
    $period_id_int = intval($period_id);
    $where .= " AND e.period_id = $period_id_int";
}

// Get entries with lines
$sql = "SELECT e.*, j.code as journal_code, j.label as journal_label
        FROM entries e
        LEFT JOIN journals j ON e.journal_id = j.id
        WHERE $where
        ORDER BY j.code, e.entry_date, e.id";
$entries = db_fetch_all(db_query($sql));

// Get lines for each entry
foreach ($entries as &$entry) {
    $entry_id = $entry['id'];
    $sql = "SELECT el.*, a.code as account_code, a.label as account_label
            FROM entry_lines el
            LEFT JOIN accounts a ON el.account_id = a.id
            WHERE el.entry_id = $entry_id
            ORDER BY el.line_no";
    $entry['lines'] = db_fetch_all(db_query($sql));
}
unset($entry);

// Calculate totals
$grand_debit = 0;
$grand_credit = 0;
foreach ($entries as $entry) {
    $grand_debit += $entry['total_debit'];
    $grand_credit += $entry['total_credit'];
}
?>

<h2>Etat du Journal</h2>

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

        <button type="submit" class="btn btn-small">Afficher</button>
        <a href="/modules/reports/journal.php" class="btn btn-small">Reset</a>
        <a href="/modules/reports/pdf_journal.php?<?php echo http_build_query($_GET); ?>" class="btn btn-small" target="_blank">PDF</a>
    </form>
</div>

<!-- Summary -->
<div class="dashboard-row mb-20">
    <div class="stat-box">
        <h3>Nb Pieces</h3>
        <p class="big-number"><?php echo count($entries); ?></p>
    </div>
    <div class="stat-box">
        <h3>Total Debit</h3>
        <p class="big-number"><?php echo format_money($grand_debit); ?></p>
    </div>
    <div class="stat-box">
        <h3>Total Credit</h3>
        <p class="big-number"><?php echo format_money($grand_credit); ?></p>
    </div>
</div>

<!-- Journal entries -->
<?php if (count($entries) > 0): ?>
    <?php
    $current_journal = '';
    foreach ($entries as $entry):
        if ($entry['journal_code'] !== $current_journal):
            if ($current_journal !== ''):
                echo '</tbody></table></div>';
            endif;
            $current_journal = $entry['journal_code'];
    ?>
    <div class="mb-20">
        <h3>Journal <?php echo h($entry['journal_code'] . ' - ' . $entry['journal_label']); ?></h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>N&deg; Piece</th>
                    <th>Compte</th>
                    <th>Libelle</th>
                    <th>Debit</th>
                    <th>Credit</th>
                </tr>
            </thead>
            <tbody>
    <?php endif; ?>

                <tr style="background: #e5e5e5;">
                    <td colspan="6">
                        <strong><?php echo format_date($entry['entry_date']); ?> -
                        <?php echo h($entry['piece_number']); ?> -
                        <?php echo h($entry['label']); ?></strong>
                    </td>
                </tr>
                <?php foreach ($entry['lines'] as $line): ?>
                <tr>
                    <td></td>
                    <td></td>
                    <td><?php echo h($line['account_code']); ?></td>
                    <td><?php echo h($line['label']); ?></td>
                    <td class="number"><?php echo $line['debit'] > 0 ? format_money($line['debit']) : ''; ?></td>
                    <td class="number"><?php echo $line['credit'] > 0 ? format_money($line['credit']) : ''; ?></td>
                </tr>
                <?php endforeach; ?>
                <tr>
                    <td colspan="4" class="text-right"><em>Total piece:</em></td>
                    <td class="number"><em><?php echo format_money($entry['total_debit']); ?></em></td>
                    <td class="number"><em><?php echo format_money($entry['total_credit']); ?></em></td>
                </tr>

    <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="report-totals" style="padding: 10px; background: #003366; color: white;">
        <strong>TOTAL GENERAL:</strong>
        Debit: <?php echo format_money($grand_debit); ?> |
        Credit: <?php echo format_money($grand_credit); ?>
    </div>
<?php else: ?>
<p>Aucune donnee pour les criteres selectionnes.</p>
<?php endif; ?>

<?php require_once __DIR__ . '/../../footer.php'; ?>
