<?php
/**
 * Lettering page - Legacy style
 */

$page_title = 'Lettrage';
require_once __DIR__ . '/../../header.php';
require_role('accountant');

$third_party_id = intval(get('third_party_id', 0));
$account_id = intval(get('account_id', 0));

$third_party = null;
$account = null;

// Get third party or account info
if ($third_party_id > 0) {
    $sql = "SELECT tp.*, a.id as account_id, a.code as account_code, a.label as account_label
            FROM third_parties tp
            LEFT JOIN accounts a ON tp.account_id = a.id
            WHERE tp.id = $third_party_id";
    $result = db_query($sql);
    if (db_num_rows($result) > 0) {
        $third_party = db_fetch_assoc($result);
        $account_id = $third_party['account_id'];
    }
} elseif ($account_id > 0) {
    $sql = "SELECT * FROM accounts WHERE id = $account_id";
    $result = db_query($sql);
    if (db_num_rows($result) > 0) {
        $account = db_fetch_assoc($result);
    }
}

if ($account_id == 0) {
    set_flash('error', 'Compte non trouve.');
    redirect('/modules/letters/select.php');
}

// Handle create lettering group
if (is_post() && post('action') === 'create_lettering') {
    require_csrf();

    $selected_lines = isset($_POST['lines']) ? $_POST['lines'] : array();

    if (count($selected_lines) < 2) {
        set_flash('error', 'Selectionnez au moins 2 lignes pour le lettrage.');
    } else {
        // Calculate total
        $total = 0;
        $line_ids = array();
        foreach ($selected_lines as $line_id) {
            $line_id = intval($line_id);
            $sql = "SELECT debit, credit FROM entry_lines WHERE id = $line_id";
            $result = db_query($sql);
            if (db_num_rows($result) > 0) {
                $line = db_fetch_assoc($result);
                $total += $line['debit'] - $line['credit'];
                $line_ids[] = $line_id;
            }
        }

        // Check balance (with tolerance)
        if (abs($total) > 0.01) {
            set_flash('error', 'Les lignes selectionnees ne sont pas equilibrees. Ecart: ' . format_money($total));
        } else {
            // Create lettering group
            $user_id = auth_user_id();
            $tp_id = $third_party_id ? $third_party_id : 'NULL';

            $sql = "INSERT INTO lettering_groups (account_id, third_party_id, created_at, created_by)
                    VALUES ($account_id, $tp_id, NOW(), $user_id)";
            db_query($sql);
            $group_id = db_insert_id();

            // Add lettering items
            foreach ($line_ids as $line_id) {
                $sql = "SELECT debit, credit FROM entry_lines WHERE id = $line_id";
                $line = db_fetch_assoc(db_query($sql));
                $amount = $line['debit'] - $line['credit'];

                $sql = "INSERT INTO lettering_items (group_id, entry_line_id, amount)
                        VALUES ($group_id, $line_id, $amount)";
                db_query($sql);
            }

            audit_log('CREATE', 'lettering_groups', $group_id, 'Lettering created with ' . count($line_ids) . ' lines');
            set_flash('success', 'Lettrage cree avec succes.');
        }
    }

    $redirect_url = $third_party_id > 0 ? "?third_party_id=$third_party_id" : "?account_id=$account_id";
    redirect('/modules/letters/letter.php' . $redirect_url);
}

// Handle delete lettering group
if (is_post() && post('action') === 'delete_lettering') {
    require_csrf();

    $group_id = intval(post('group_id'));

    db_query("DELETE FROM lettering_items WHERE group_id = $group_id");
    db_query("DELETE FROM lettering_groups WHERE id = $group_id");

    audit_log('DELETE', 'lettering_groups', $group_id, 'Lettering deleted');
    set_flash('success', 'Lettrage supprime.');

    $redirect_url = $third_party_id > 0 ? "?third_party_id=$third_party_id" : "?account_id=$account_id";
    redirect('/modules/letters/letter.php' . $redirect_url);
}

// Get unlettered lines
$sql = "SELECT el.*, e.entry_date, e.piece_number, e.label as entry_label
        FROM entry_lines el
        INNER JOIN entries e ON el.entry_id = e.id
        WHERE el.account_id = $account_id
          AND e.status = 'posted'
          AND el.id NOT IN (SELECT entry_line_id FROM lettering_items)
        ORDER BY e.entry_date, el.id";
$unlettered_lines = db_fetch_all(db_query($sql));

// Calculate unlettered totals
$unlettered_debit = 0;
$unlettered_credit = 0;
foreach ($unlettered_lines as $line) {
    $unlettered_debit += $line['debit'];
    $unlettered_credit += $line['credit'];
}

// Get existing lettering groups for this account
$sql = "SELECT lg.*, u.username as created_by_name,
               (SELECT SUM(ABS(amount)) FROM lettering_items li WHERE li.group_id = lg.id) as total_amount,
               (SELECT COUNT(*) FROM lettering_items li WHERE li.group_id = lg.id) as line_count
        FROM lettering_groups lg
        LEFT JOIN users u ON lg.created_by = u.id
        WHERE lg.account_id = $account_id
        ORDER BY lg.created_at DESC";
$lettering_groups = db_fetch_all(db_query($sql));
?>

<h2>Lettrage</h2>

<div class="mb-20" style="background: #f9f9f9; padding: 15px; border: 1px solid #ccc;">
    <?php if ($third_party): ?>
    <p><strong>Tiers:</strong> <?php echo h($third_party['name']); ?>
       (<?php echo $third_party['type'] === 'customer' ? 'Client' : 'Fournisseur'; ?>)</p>
    <?php endif; ?>
    <p><strong>Compte:</strong> <?php echo h($account_id ? $account['code'] . ' - ' . $account['label'] : $third_party['account_code'] . ' - ' . $third_party['account_label']); ?></p>
    <p><strong>Solde non lettre:</strong>
        <span style="<?php echo ($unlettered_debit - $unlettered_credit) >= 0 ? 'color: green;' : 'color: red;'; ?>">
            <?php echo format_money($unlettered_debit - $unlettered_credit); ?>
        </span>
    </p>
</div>

<h3>Lignes non lettrees</h3>

<?php if (count($unlettered_lines) > 0): ?>
<form method="post" action="">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="create_lettering">

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 30px;">
                    <input type="checkbox" id="select-all">
                </th>
                <th>Date</th>
                <th>N&deg; Piece</th>
                <th>Libelle</th>
                <th>Debit</th>
                <th>Credit</th>
                <th>Solde</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $running_balance = 0;
            foreach ($unlettered_lines as $line):
                $running_balance += $line['debit'] - $line['credit'];
            ?>
            <tr class="lettering-line" data-debit="<?php echo $line['debit']; ?>" data-credit="<?php echo $line['credit']; ?>">
                <td>
                    <input type="checkbox" name="lines[]" value="<?php echo $line['id']; ?>" class="row-checkbox lettering-checkbox">
                </td>
                <td><?php echo format_date($line['entry_date']); ?></td>
                <td>
                    <a href="/modules/entries/edit.php?id=<?php echo $line['entry_id']; ?>" target="_blank">
                        <?php echo h($line['piece_number']); ?>
                    </a>
                </td>
                <td><?php echo h($line['label']); ?></td>
                <td class="number"><?php echo $line['debit'] > 0 ? format_money($line['debit']) : ''; ?></td>
                <td class="number"><?php echo $line['credit'] > 0 ? format_money($line['credit']) : ''; ?></td>
                <td class="number"><?php echo format_money($running_balance); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="report-totals">
                <td colspan="4">Totaux non lettres</td>
                <td class="number"><?php echo format_money($unlettered_debit); ?></td>
                <td class="number"><?php echo format_money($unlettered_credit); ?></td>
                <td class="number"><?php echo format_money($unlettered_debit - $unlettered_credit); ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="lettering-summary">
        <strong>Selection:</strong>
        Solde = <span id="lettering-total" class="balanced">0.00</span> EUR
        <button type="submit" id="btn-create-lettering" class="btn btn-success" disabled>
            Creer le lettrage
        </button>
    </div>
</form>
<?php else: ?>
<p>Toutes les lignes sont lettrees.</p>
<?php endif; ?>

<h3 class="mt-20">Lettrages existants</h3>

<?php if (count($lettering_groups) > 0): ?>
<table class="data-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Date</th>
            <th>Cree par</th>
            <th>Nb lignes</th>
            <th>Montant</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($lettering_groups as $lg): ?>
        <tr>
            <td><?php echo $lg['id']; ?></td>
            <td><?php echo format_datetime($lg['created_at']); ?></td>
            <td><?php echo h($lg['created_by_name']); ?></td>
            <td><?php echo $lg['line_count']; ?></td>
            <td class="number"><?php echo format_money($lg['total_amount'] / 2); ?></td>
            <td>
                <form method="post" action="" style="display: inline;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="delete_lettering">
                    <input type="hidden" name="group_id" value="<?php echo $lg['id']; ?>">
                    <button type="submit" class="btn btn-small btn-danger confirm-action"
                            data-confirm="Supprimer ce lettrage ?">Supprimer</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<p>Aucun lettrage existant pour ce compte.</p>
<?php endif; ?>

<div class="mt-20">
    <a href="/modules/letters/select.php" class="btn">Retour a la selection</a>
</div>

<?php require_once __DIR__ . '/../../footer.php'; ?>
