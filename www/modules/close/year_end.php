<?php
/**
 * Year-end closing page - Legacy style
 */

$page_title = 'Cloture Annuelle';
require_once __DIR__ . '/../../header.php';
require_role('admin');

$company = get_company();

// Check preconditions
$errors = array();
$warnings = array();

// Check if all periods are locked
$sql = "SELECT COUNT(*) as count FROM periods WHERE status = 'open'";
$result = db_query($sql);
$open_periods = db_fetch_assoc($result)['count'];
if ($open_periods > 0) {
    $errors[] = "$open_periods periode(s) non verrouillee(s). Toutes les periodes doivent etre verroullees.";
}

// Check for draft entries
$sql = "SELECT COUNT(*) as count FROM entries WHERE status = 'draft'";
$result = db_query($sql);
$draft_entries = db_fetch_assoc($result)['count'];
if ($draft_entries > 0) {
    $errors[] = "$draft_entries ecriture(s) en brouillon. Validez ou supprimez les brouillons.";
}

// Check if already closed
if ($company && $company['fiscal_year_closed']) {
    $warnings[] = "L'exercice est deja marque comme cloture.";
}

$can_close = count($errors) === 0;

// Handle year-end closing
if (is_post() && post('action') === 'close_year') {
    require_csrf();

    if (!$can_close) {
        set_flash('error', 'Les conditions de cloture ne sont pas remplies.');
        redirect('/modules/close/year_end.php');
    }

    // Get carry forward account
    $carry_forward_code = $company['carry_forward_account'] ?: '110000';
    $sql = "SELECT id FROM accounts WHERE code = '" . db_escape($carry_forward_code) . "'";
    $result = db_query($sql);
    if (db_num_rows($result) == 0) {
        set_flash('error', "Compte de report a nouveau ($carry_forward_code) introuvable.");
        redirect('/modules/close/year_end.php');
    }
    $carry_forward_account_id = db_fetch_assoc($result)['id'];

    // Get OD journal
    $sql = "SELECT id FROM journals WHERE code = 'OD'";
    $result = db_query($sql);
    if (db_num_rows($result) == 0) {
        set_flash('error', "Journal OD introuvable.");
        redirect('/modules/close/year_end.php');
    }
    $od_journal_id = db_fetch_assoc($result)['id'];

    // Calculate balances for all accounts
    $sql = "SELECT a.id as account_id, a.code, a.label,
                   SUM(el.debit) - SUM(el.credit) as balance
            FROM accounts a
            INNER JOIN entry_lines el ON el.account_id = a.id
            INNER JOIN entries e ON el.entry_id = e.id AND e.status = 'posted'
            GROUP BY a.id, a.code, a.label
            HAVING balance != 0
            ORDER BY a.code";
    $balances = db_fetch_all(db_query($sql));

    // Calculate new fiscal year dates
    $new_start = date('Y-m-d', strtotime($company['fiscal_year_end'] . ' +1 day'));
    $new_year = date('Y', strtotime($new_start));
    $new_end = $new_year . '-12-31';

    // Create opening entry
    $user_id = auth_user_id();
    $entry_label = "A-nouveaux exercice $new_year";

    $sql = "INSERT INTO entries (journal_id, entry_date, period_id, label, status, total_debit, total_credit, created_by, created_at)
            VALUES ($od_journal_id, '$new_start', NULL, '$entry_label', 'draft', 0, 0, $user_id, NOW())";
    db_query($sql);
    $entry_id = db_insert_id();

    $total_debit = 0;
    $total_credit = 0;
    $line_no = 1;

    // Create lines for each account balance
    foreach ($balances as $bal) {
        $account_id = $bal['account_id'];
        $balance = $bal['balance'];
        $line_label = db_escape("Report " . $bal['code']);

        $debit = $balance > 0 ? $balance : 0;
        $credit = $balance < 0 ? abs($balance) : 0;

        $sql = "INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
                VALUES ($entry_id, $line_no, $account_id, '$line_label', $debit, $credit)";
        db_query($sql);

        $total_debit += $debit;
        $total_credit += $credit;
        $line_no++;
    }

    // Balance with carry-forward account if needed
    $diff = $total_debit - $total_credit;
    if (abs($diff) > 0.01) {
        $debit = $diff < 0 ? abs($diff) : 0;
        $credit = $diff > 0 ? $diff : 0;

        $sql = "INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
                VALUES ($entry_id, $line_no, $carry_forward_account_id, 'Equilibrage report', $debit, $credit)";
        db_query($sql);

        $total_debit += $debit;
        $total_credit += $credit;
    }

    // Update entry totals
    $sql = "UPDATE entries SET total_debit = $total_debit, total_credit = $total_credit WHERE id = $entry_id";
    db_query($sql);

    // Mark fiscal year as closed
    $sql = "UPDATE company SET fiscal_year_closed = 1 WHERE id = 1";
    db_query($sql);

    audit_log('CLOSE_YEAR', 'company', 1, "Year-end closing performed, opening entry $entry_id created");

    set_flash('success', "Cloture effectuee. Ecriture d'a-nouveaux #$entry_id creee en brouillon.");
    redirect('/modules/entries/edit.php?id=' . $entry_id);
}

// Get balance preview
$sql = "SELECT a.code, a.label,
               SUM(el.debit) - SUM(el.credit) as balance
        FROM accounts a
        INNER JOIN entry_lines el ON el.account_id = a.id
        INNER JOIN entries e ON el.entry_id = e.id AND e.status = 'posted'
        GROUP BY a.id, a.code, a.label
        HAVING balance != 0
        ORDER BY a.code";
$balance_preview = db_fetch_all(db_query($sql));

$total_debit_preview = 0;
$total_credit_preview = 0;
foreach ($balance_preview as $bal) {
    if ($bal['balance'] > 0) $total_debit_preview += $bal['balance'];
    else $total_credit_preview += abs($bal['balance']);
}
?>

<h2>Cloture Annuelle</h2>

<?php if ($company): ?>
<div class="mb-20" style="background: #f9f9f9; padding: 15px; border: 1px solid #ccc;">
    <h3>Exercice actuel</h3>
    <p><strong>Periode:</strong> <?php echo format_date($company['fiscal_year_start']); ?> - <?php echo format_date($company['fiscal_year_end']); ?></p>
    <p><strong>Statut:</strong>
        <?php if ($company['fiscal_year_closed']): ?>
        <span style="color: red;">Cloture</span>
        <?php else: ?>
        <span style="color: green;">En cours</span>
        <?php endif; ?>
    </p>
    <p><strong>Compte de report a nouveau:</strong> <?php echo h($company['carry_forward_account']); ?></p>
</div>
<?php endif; ?>

<!-- Preconditions -->
<h3>Verification des conditions</h3>

<?php if (count($errors) > 0): ?>
<div class="flash flash-error">
    <strong>Conditions non remplies:</strong>
    <ul>
        <?php foreach ($errors as $err): ?>
        <li><?php echo h($err); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php else: ?>
<div class="flash flash-success">
    Toutes les conditions sont remplies pour la cloture.
</div>
<?php endif; ?>

<?php if (count($warnings) > 0): ?>
<div class="flash flash-warning">
    <strong>Avertissements:</strong>
    <ul>
        <?php foreach ($warnings as $warn): ?>
        <li><?php echo h($warn); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<!-- Balance preview -->
<h3>Apercu des a-nouveaux</h3>
<p>Les soldes suivants seront reportes dans l'ecriture d'ouverture:</p>

<table class="data-table">
    <thead>
        <tr>
            <th>Compte</th>
            <th>Libelle</th>
            <th>Solde Debiteur</th>
            <th>Solde Crediteur</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($balance_preview as $bal): ?>
        <tr>
            <td><?php echo h($bal['code']); ?></td>
            <td><?php echo h($bal['label']); ?></td>
            <td class="number"><?php echo $bal['balance'] > 0 ? format_money($bal['balance']) : ''; ?></td>
            <td class="number"><?php echo $bal['balance'] < 0 ? format_money(abs($bal['balance'])) : ''; ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr class="report-totals">
            <td colspan="2">TOTAUX</td>
            <td class="number"><?php echo format_money($total_debit_preview); ?></td>
            <td class="number"><?php echo format_money($total_credit_preview); ?></td>
        </tr>
    </tfoot>
</table>

<?php if (abs($total_debit_preview - $total_credit_preview) > 0.01): ?>
<p style="color: orange;">
    <strong>Note:</strong> Un ecart de <?php echo format_money(abs($total_debit_preview - $total_credit_preview)); ?>
    sera equilibre sur le compte de report a nouveau.
</p>
<?php endif; ?>

<!-- Close button -->
<div class="mt-20">
    <?php if ($can_close): ?>
    <form method="post" action="">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="close_year">
        <button type="submit" class="btn btn-danger confirm-action"
                data-confirm="Effectuer la cloture annuelle ? Cette action creera une ecriture d'a-nouveaux.">
            Effectuer la cloture
        </button>
    </form>
    <?php else: ?>
    <button class="btn" disabled>Cloture impossible (conditions non remplies)</button>
    <?php endif; ?>

    <a href="/modules/close/lock_period.php" class="btn">Retour aux periodes</a>
</div>

<div class="mt-20">
    <h3>Procedure de cloture</h3>
    <ol>
        <li>Verifier que toutes les ecritures sont validees (aucun brouillon)</li>
        <li>Verrouiller toutes les periodes de l'exercice</li>
        <li>Lancer la cloture - une ecriture d'a-nouveaux sera creee</li>
        <li>Verifier l'ecriture d'a-nouveaux et la valider</li>
        <li>Configurer les dates du nouvel exercice</li>
        <li>Generer les periodes du nouvel exercice</li>
    </ol>
</div>

<?php require_once __DIR__ . '/../../footer.php'; ?>
