<?php
/**
 * Lettering history page - Legacy style
 *
 * Shows:
 * - History of all letterings (by account or all)
 * - Details of a specific lettering group
 * - Ability to unletter (if period not locked)
 */

require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/utils.php';

require_login();
require_role('accountant');

$account_id = intval(get('account_id', 0));
$group_id = intval(get('group_id', 0));
$date_from = get('date_from', '');
$date_to = get('date_to', '');

// Handle unletter action
if (is_post() && post('action') === 'unletter') {
    csrf_verify();

    $delete_group_id = intval(post('group_id'));

    // Check if any line is in a locked period
    $sql = "SELECT lg.letter_code, lg.account_id, p.status as period_status
            FROM lettering_groups lg
            INNER JOIN lettering_items li ON li.group_id = lg.id
            INNER JOIN entry_lines el ON el.id = li.entry_line_id
            INNER JOIN entries e ON e.id = el.entry_id
            LEFT JOIN periods p ON p.id = e.period_id
            WHERE lg.id = $delete_group_id
            LIMIT 1";
    $result = db_query($sql);
    $group = db_fetch_assoc($result);

    if (!$group) {
        set_flash('error', 'Groupe de lettrage non trouve.');
    } elseif ($group['period_status'] == 'locked') {
        set_flash('error', 'Impossible de delettrer: une ecriture est en periode verrouillee.');
    } else {
        $letter_code = $group['letter_code'];
        $acc_id = $group['account_id'];

        db_query("DELETE FROM lettering_items WHERE group_id = $delete_group_id");
        db_query("DELETE FROM lettering_groups WHERE id = $delete_group_id");

        audit_log('DELETE', 'lettering_groups', $delete_group_id, 'Delettrage ' . $letter_code);
        set_flash('success', 'Lettrage ' . h($letter_code) . ' supprime.');

        // Redirect to same page or letter page
        if ($group_id > 0) {
            redirect('/modules/letters/history.php?account_id=' . $acc_id);
        }
    }

    redirect($_SERVER['REQUEST_URI']);
}

// If viewing a specific group
$group_detail = null;
$group_lines = array();

if ($group_id > 0) {
    $sql = "SELECT lg.*, u.username as created_by_name, a.code as account_code, a.label as account_label,
                   tp.name as third_party_name
            FROM lettering_groups lg
            LEFT JOIN users u ON lg.created_by = u.id
            LEFT JOIN accounts a ON lg.account_id = a.id
            LEFT JOIN third_parties tp ON lg.third_party_id = tp.id
            WHERE lg.id = $group_id";
    $result = db_query($sql);
    $group_detail = db_fetch_assoc($result);

    if ($group_detail) {
        // Get lines in this group
        $sql = "SELECT li.*, el.label as line_label, el.debit, el.credit,
                       e.entry_date, e.piece_number, e.label as entry_label,
                       tp.name as third_party_name
                FROM lettering_items li
                INNER JOIN entry_lines el ON el.id = li.entry_line_id
                INNER JOIN entries e ON e.id = el.entry_id
                LEFT JOIN third_parties tp ON el.third_party_id = tp.id
                WHERE li.group_id = $group_id
                ORDER BY e.entry_date, el.id";
        $group_lines = db_fetch_all(db_query($sql));
    }
}

// Get history list
$where = "1=1";
if ($account_id > 0) {
    $where .= " AND lg.account_id = $account_id";
}
if ($date_from) {
    $date_from_sql = db_escape($date_from);
    $where .= " AND DATE(lg.created_at) >= '$date_from_sql'";
}
if ($date_to) {
    $date_to_sql = db_escape($date_to);
    $where .= " AND DATE(lg.created_at) <= '$date_to_sql'";
}

// Pagination
$sql = "SELECT COUNT(*) as count FROM lettering_groups lg WHERE $where";
$total = db_fetch_assoc(db_query($sql))['count'];
$page = max(1, intval(get('page', 1)));
$pagination = paginate($total, $page, 50);

// Get history
$sql = "SELECT lg.*, u.username as created_by_name, a.code as account_code, a.label as account_label,
               tp.name as third_party_name,
               (SELECT SUM(ABS(amount)) FROM lettering_items li WHERE li.group_id = lg.id AND amount > 0) as total_debit,
               (SELECT SUM(ABS(amount)) FROM lettering_items li WHERE li.group_id = lg.id AND amount < 0) as total_credit,
               (SELECT COUNT(*) FROM lettering_items li WHERE li.group_id = lg.id) as line_count
        FROM lettering_groups lg
        LEFT JOIN users u ON lg.created_by = u.id
        LEFT JOIN accounts a ON lg.account_id = a.id
        LEFT JOIN third_parties tp ON lg.third_party_id = tp.id
        WHERE $where
        ORDER BY lg.created_at DESC
        LIMIT {$pagination['offset']}, {$pagination['per_page']}";
$history = db_fetch_all(db_query($sql));

// Get accounts for filter
$sql = "SELECT DISTINCT a.id, a.code, a.label
        FROM accounts a
        INNER JOIN lettering_groups lg ON lg.account_id = a.id
        ORDER BY a.code";
$accounts_with_letterings = db_fetch_all(db_query($sql));

$page_title = 'Historique des lettrages';
require_once __DIR__ . '/../../header.php';
?>

<h2>Historique des lettrages</h2>

<?php if ($group_detail): ?>
<!-- Detail view of a specific lettering group -->
<div class="mb-20" style="background: #f0f8ff; padding: 15px; border: 1px solid #0066cc;">
    <h3>Detail du lettrage <?php echo h($group_detail['letter_code']); ?></h3>

    <table style="width: auto;">
        <tr>
            <td><strong>Code:</strong></td>
            <td><?php echo h($group_detail['letter_code']); ?></td>
        </tr>
        <tr>
            <td><strong>Compte:</strong></td>
            <td><?php echo h($group_detail['account_code'] . ' - ' . $group_detail['account_label']); ?></td>
        </tr>
        <?php if ($group_detail['third_party_name']): ?>
        <tr>
            <td><strong>Tiers:</strong></td>
            <td><?php echo h($group_detail['third_party_name']); ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <td><strong>Type:</strong></td>
            <td>
                <?php if ($group_detail['is_partial']): ?>
                <span style="color: orange;">Lettrage partiel</span>
                <?php else: ?>
                <span style="color: green;">Lettrage complet</span>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td><strong>Date:</strong></td>
            <td><?php echo format_datetime($group_detail['created_at']); ?></td>
        </tr>
        <tr>
            <td><strong>Cree par:</strong></td>
            <td><?php echo h($group_detail['created_by_name']); ?></td>
        </tr>
    </table>

    <h4>Lignes comprises dans ce lettrage</h4>
    <table class="data-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>N&deg; Piece</th>
                <th>Libelle</th>
                <th>Tiers</th>
                <th>Debit</th>
                <th>Credit</th>
                <th>Montant lettre</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $total_debit = 0;
            $total_credit = 0;
            foreach ($group_lines as $line):
                if ($line['amount'] > 0) {
                    $total_debit += abs($line['amount']);
                } else {
                    $total_credit += abs($line['amount']);
                }
            ?>
            <tr>
                <td><?php echo format_date($line['entry_date']); ?></td>
                <td>
                    <a href="/modules/entries/edit.php?id=<?php echo $line['entry_id']; ?>" target="_blank">
                        <?php echo h($line['piece_number']); ?>
                    </a>
                </td>
                <td><?php echo h($line['line_label']); ?></td>
                <td><?php echo h($line['third_party_name']); ?></td>
                <td class="number"><?php echo $line['debit'] > 0 ? format_money($line['debit']) : ''; ?></td>
                <td class="number"><?php echo $line['credit'] > 0 ? format_money($line['credit']) : ''; ?></td>
                <td class="number" style="font-weight: bold;">
                    <?php echo format_money(abs($line['amount'])); ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="report-totals">
                <td colspan="4">Totaux</td>
                <td class="number"><?php echo format_money($total_debit); ?></td>
                <td class="number"><?php echo format_money($total_credit); ?></td>
                <td class="number">
                    Ecart: <?php echo format_money(abs($total_debit - $total_credit)); ?>
                </td>
            </tr>
        </tfoot>
    </table>

    <div class="mt-10">
        <form method="post" action="" style="display: inline;">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="unletter">
            <input type="hidden" name="group_id" value="<?php echo $group_detail['id']; ?>">
            <button type="submit" class="btn btn-danger confirm-action"
                    data-confirm="Supprimer ce lettrage ? Les lignes redeviendront non lettrees.">
                Supprimer ce lettrage
            </button>
        </form>
        <a href="/modules/letters/history.php?account_id=<?php echo $group_detail['account_id']; ?>" class="btn">
            Retour a l'historique du compte
        </a>
        <a href="/modules/letters/letter.php?account_id=<?php echo $group_detail['account_id']; ?>" class="btn">
            Lettrer ce compte
        </a>
    </div>
</div>
<?php endif; ?>

<h3>Liste des lettrages</h3>

<!-- Filters -->
<div class="filters">
    <form method="get" action="">
        <label>Compte:</label>
        <select name="account_id">
            <option value="">Tous les comptes</option>
            <?php foreach ($accounts_with_letterings as $acc): ?>
            <option value="<?php echo $acc['id']; ?>" <?php echo $account_id == $acc['id'] ? 'selected' : ''; ?>>
                <?php echo h($acc['code'] . ' - ' . $acc['label']); ?>
            </option>
            <?php endforeach; ?>
        </select>

        <label>Du:</label>
        <input type="date" name="date_from" value="<?php echo h($date_from); ?>">

        <label>Au:</label>
        <input type="date" name="date_to" value="<?php echo h($date_to); ?>">

        <button type="submit" class="btn btn-small">Filtrer</button>
        <a href="/modules/letters/history.php" class="btn btn-small">Reset</a>
    </form>
</div>

<?php if (count($history) > 0): ?>
<table class="data-table">
    <thead>
        <tr>
            <th>Code</th>
            <th>Date</th>
            <th>Compte</th>
            <th>Tiers</th>
            <th>Lignes</th>
            <th>Debit</th>
            <th>Credit</th>
            <th>Type</th>
            <th>Cree par</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($history as $lg): ?>
        <tr>
            <td><strong><?php echo h($lg['letter_code']); ?></strong></td>
            <td><?php echo format_datetime($lg['created_at']); ?></td>
            <td>
                <a href="/modules/letters/letter.php?account_id=<?php echo $lg['account_id']; ?>">
                    <?php echo h($lg['account_code']); ?>
                </a>
            </td>
            <td><?php echo h($lg['third_party_name']); ?></td>
            <td><?php echo $lg['line_count']; ?></td>
            <td class="number"><?php echo format_money($lg['total_debit']); ?></td>
            <td class="number"><?php echo format_money($lg['total_credit']); ?></td>
            <td>
                <?php if ($lg['is_partial']): ?>
                <span style="color: orange;">Partiel</span>
                <?php else: ?>
                <span style="color: green;">Complet</span>
                <?php endif; ?>
            </td>
            <td><?php echo h($lg['created_by_name']); ?></td>
            <td>
                <a href="/modules/letters/history.php?group_id=<?php echo $lg['id']; ?>" class="btn btn-small">
                    Detail
                </a>
                <form method="post" action="" style="display: inline;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="unletter">
                    <input type="hidden" name="group_id" value="<?php echo $lg['id']; ?>">
                    <button type="submit" class="btn btn-small btn-danger confirm-action"
                            data-confirm="Supprimer le lettrage <?php echo h($lg['letter_code']); ?> ?">
                        Supprimer
                    </button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php
$filter_params = 'account_id=' . urlencode($account_id) .
    '&date_from=' . urlencode($date_from) .
    '&date_to=' . urlencode($date_to);
echo pagination_links($pagination, '?' . $filter_params);
?>

<?php else: ?>
<p>Aucun lettrage trouve.</p>
<?php endif; ?>

<p>Total: <?php echo $total; ?> lettrages</p>

<div class="mt-20">
    <a href="/modules/letters/select.php" class="btn">Retour a la selection</a>
</div>

<?php require_once __DIR__ . '/../../footer.php'; ?>
