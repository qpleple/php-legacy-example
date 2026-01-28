<?php
/**
 * Entry create/edit page - Legacy style (Simplified, no draft)
 */

require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/utils.php';

require_login();

$page_title = 'Nouvelle Écriture';

$entry_id = intval(get('id', 0));
$entry = null;
$lines = array();

// Load existing entry (view only)
if ($entry_id > 0) {
    $sql = "SELECT e.*, j.code as journal_code FROM entries e
            LEFT JOIN journals j ON e.journal_id = j.id
            WHERE e.id = $entry_id";
    $result = db_query($sql);
    if (db_num_rows($result) > 0) {
        $entry = db_fetch_assoc($result);

        // Get lines
        $sql = "SELECT el.*, a.code as account_code, a.label as account_label
                FROM entry_lines el
                LEFT JOIN accounts a ON el.account_id = a.id
                WHERE el.entry_id = $entry_id
                ORDER BY el.line_no";
        $lines = db_fetch_all(db_query($sql));

        $page_title = 'Pièce ' . $entry['piece_number'];
    } else {
        set_flash('error', 'Pièce introuvable.');
        redirect('/modules/entries/list.php');
    }
}

// Handle form submission (only for new entries)
if (is_post() && !$entry) {
    csrf_verify();

    $journal_id = intval(post('journal_id'));
    $entry_date = db_escape(parse_date(post('entry_date')));
    $label = db_escape(trim(post('label')));

    // Validation
    $errors = array();
    if (empty($journal_id)) $errors[] = 'Le journal est obligatoire.';
    if (empty($entry_date)) $errors[] = 'La date est obligatoire.';
    if (empty($label)) $errors[] = 'Le libellé est obligatoire.';

    // Process lines
    $line_data = array();
    $total_debit = 0;
    $total_credit = 0;

    if (isset($_POST['lines']) && is_array($_POST['lines'])) {
        $line_no = 1;
        foreach ($_POST['lines'] as $line) {
            $account_id = intval($line['account_id']);
            if ($account_id == 0) continue;

            $line_label = trim($line['label']);
            $debit = parse_number($line['debit']);
            $credit = parse_number($line['credit']);

            if ($debit > 0 && $credit > 0) {
                $errors[] = "Ligne $line_no : une ligne ne peut avoir à la fois un débit et un crédit.";
            }

            $line_data[] = array(
                'line_no' => $line_no,
                'account_id' => $account_id,
                'label' => $line_label,
                'debit' => $debit,
                'credit' => $credit
            );

            $total_debit += $debit;
            $total_credit += $credit;
            $line_no++;
        }
    }

    if (count($line_data) < 2) {
        $errors[] = 'Une pièce doit avoir au moins 2 lignes.';
    }

    if (!validate_double_entry($total_debit, $total_credit)) {
        $errors[] = 'La pièce n\'est pas équilibrée (débit != crédit).';
    }

    if (empty($errors)) {
        $user_id = auth_user_id();
        $piece_number = generate_piece_number($journal_id);

        $sql = "INSERT INTO entries (journal_id, entry_date, piece_number, label, status, total_debit, total_credit, created_by, created_at, posted_at)
                VALUES ($journal_id, '$entry_date', '$piece_number', '$label', 'posted', $total_debit, $total_credit, $user_id, datetime('now'), datetime('now'))";
        db_query($sql);
        $entry_id = db_insert_id();

        foreach ($line_data as $ld) {
            $acc_id = $ld['account_id'];
            $ll = db_escape($ld['label']);
            $d = $ld['debit'];
            $c = $ld['credit'];

            $sql = "INSERT INTO entry_lines (entry_id, line_no, account_id, label, debit, credit)
                    VALUES ($entry_id, {$ld['line_no']}, $acc_id, '$ll', $d, $c)";
            db_query($sql);
        }

        audit_log('CREATE', 'entries', $entry_id, "Entry created: $piece_number");
        set_flash('success', "Écriture enregistrée : $piece_number");
        redirect('/modules/entries/edit.php?id=' . $entry_id);
    } else {
        set_flash('error', implode('<br>', $errors));
    }
}

$journals = get_journals();
$accounts = get_accounts();

require_once __DIR__ . '/../../header.php';
?>

<h2><?php echo $page_title; ?></h2>

<?php if ($entry): ?>
<div class="flash flash-info">
    Cette écriture a été enregistrée et ne peut plus être modifiée.
</div>

<div class="form-row mb-20">
    <div class="form-group">
        <label>Date</label>
        <input type="text" value="<?php echo format_date($entry['entry_date']); ?>" readonly style="background: #eee;">
    </div>
    <div class="form-group">
        <label>Journal</label>
        <input type="text" value="<?php echo h($entry['journal_code']); ?>" readonly style="background: #eee;">
    </div>
    <div class="form-group" style="flex: 2;">
        <label>Libellé</label>
        <input type="text" value="<?php echo h($entry['label']); ?>" readonly style="background: #eee;">
    </div>
    <div class="form-group">
        <label>N° Pièce</label>
        <input type="text" value="<?php echo h($entry['piece_number']); ?>" readonly style="background: #eee;">
    </div>
</div>

<h3>Lignes</h3>
<table class="data-table">
    <thead>
        <tr>
            <th>#</th>
            <th>Compte</th>
            <th>Libellé</th>
            <th>Débit</th>
            <th>Crédit</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($lines as $idx => $line): ?>
        <tr>
            <td><?php echo $idx + 1; ?></td>
            <td><?php echo h($line['account_code'] . ' - ' . $line['account_label']); ?></td>
            <td><?php echo h($line['label']); ?></td>
            <td class="number"><?php echo $line['debit'] > 0 ? format_money($line['debit']) : ''; ?></td>
            <td class="number"><?php echo $line['credit'] > 0 ? format_money($line['credit']) : ''; ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <th colspan="3">Total</th>
            <th class="number"><?php echo format_money($entry['total_debit']); ?></th>
            <th class="number"><?php echo format_money($entry['total_credit']); ?></th>
        </tr>
    </tfoot>
</table>

<div class="mt-20">
    <a href="/modules/entries/list.php" class="btn">Retour à la liste</a>
    <a href="/modules/entries/edit.php" class="btn btn-primary">Nouvelle écriture</a>
</div>

<?php else: ?>

<form method="post" action="" id="entry-form">
    <?php echo csrf_field(); ?>

    <div class="form-row mb-20">
        <div class="form-group">
            <label for="entry_date">Date *</label>
            <input type="date" id="entry_date" name="entry_date" value="<?php echo date('Y-m-d'); ?>" required>
        </div>
        <div class="form-group">
            <label for="journal_id">Journal *</label>
            <select id="journal_id" name="journal_id" required>
                <option value="">-- Choisir --</option>
                <?php foreach ($journals as $j): ?>
                <option value="<?php echo $j['id']; ?>">
                    <?php echo h($j['code'] . ' - ' . $j['label']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="flex: 2;">
            <label for="label">Libellé *</label>
            <input type="text" id="label" name="label" style="width: 100%;" required>
        </div>
    </div>

    <h3>Lignes</h3>
    <table class="entry-lines-table" id="entry-lines-table">
        <thead>
            <tr>
                <th style="width: 30px;">#</th>
                <th class="col-account">Compte</th>
                <th class="col-label">Libellé</th>
                <th class="col-debit">Débit</th>
                <th class="col-credit">Crédit</th>
                <th class="col-actions">Actions</th>
            </tr>
        </thead>
        <tbody id="entry-lines-body">
        </tbody>
    </table>

    <table style="display:none;">
        <tbody>
            <tr id="line-template">
                <td class="line-no">0</td>
                <td>
                    <select name="lines[][account_id]" class="line-account">
                        <option value="">--</option>
                        <?php foreach ($accounts as $acc): ?>
                        <option value="<?php echo $acc['id']; ?>">
                            <?php echo h($acc['code'] . ' - ' . $acc['label']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><input type="text" name="lines[][label]"></td>
                <td><input type="text" name="lines[][debit]" class="line-debit"></td>
                <td><input type="text" name="lines[][credit]" class="line-credit"></td>
                <td><button type="button" class="btn btn-small btn-danger btn-remove-line">X</button></td>
            </tr>
        </tbody>
    </table>

    <div class="mt-10">
        <button type="button" class="btn btn-add-line">+ Ajouter ligne</button>
    </div>

    <div class="entry-totals">
        <span class="total-debit">Total Débit : <span id="total-debit">0.00</span> EUR</span>
        <span class="total-credit">Total Crédit : <span id="total-credit">0.00</span> EUR</span>
        <span class="balance" id="balance">Équilibre</span>
    </div>

    <div class="mt-20">
        <button type="submit" class="btn btn-success">Enregistrer</button>
        <a href="/modules/entries/list.php" class="btn">Annuler</a>
    </div>
</form>

<?php endif; ?>

<?php require_once __DIR__ . '/../../footer.php'; ?>
