<?php
/**
 * Entry create/edit page - Legacy style
 */

$page_title = 'Saisie Ecriture';
require_once __DIR__ . '/../../header.php';
require_role('accountant');

$entry_id = intval(get('id', 0));
$entry = null;
$lines = array();
$attachments = array();
$is_readonly = false;

// Load existing entry
if ($entry_id > 0) {
    $sql = "SELECT e.*, j.code as journal_code FROM entries e
            LEFT JOIN journals j ON e.journal_id = j.id
            WHERE e.id = $entry_id";
    $result = db_query($sql);
    if (db_num_rows($result) > 0) {
        $entry = db_fetch_assoc($result);
        $is_readonly = ($entry['status'] === 'posted');

        // Get lines
        $sql = "SELECT el.*, a.code as account_code, a.label as account_label, a.type as account_type,
                       tp.name as third_party_name, vr.label as vat_label, vr.rate as vat_rate
                FROM entry_lines el
                LEFT JOIN accounts a ON el.account_id = a.id
                LEFT JOIN third_parties tp ON el.third_party_id = tp.id
                LEFT JOIN vat_rates vr ON el.vat_rate_id = vr.id
                WHERE el.entry_id = $entry_id
                ORDER BY el.line_no";
        $lines = db_fetch_all(db_query($sql));

        // Get attachments
        $sql = "SELECT * FROM attachments WHERE entry_id = $entry_id ORDER BY uploaded_at";
        $attachments = db_fetch_all(db_query($sql));

        $page_title = $is_readonly ? 'Piece ' . $entry['piece_number'] : 'Modification Ecriture';
    } else {
        set_flash('error', 'Piece introuvable.');
        redirect('/modules/entries/list.php');
    }
}

// Handle form submission
if (is_post() && !$is_readonly) {
    require_csrf();
    $action = post('action');

    $journal_id = intval(post('journal_id'));
    $entry_date = db_escape(parse_date(post('entry_date')));
    $label = db_escape(trim(post('label')));

    // Validation
    $errors = array();
    if (empty($journal_id)) $errors[] = 'Le journal est obligatoire.';
    if (empty($entry_date)) $errors[] = 'La date est obligatoire.';
    if (empty($label)) $errors[] = 'Le libelle est obligatoire.';

    // Get period for the date
    $period = get_period_for_date($entry_date);
    $period_id = $period ? $period['id'] : null;

    // Check if period is open
    if ($period && $period['status'] === 'locked') {
        $errors[] = 'La periode est verrouillee, impossible de saisir.';
    }

    // Process lines
    $line_data = array();
    $total_debit = 0;
    $total_credit = 0;

    if (isset($_POST['lines']) && is_array($_POST['lines'])) {
        $line_no = 1;
        foreach ($_POST['lines'] as $line) {
            $account_id = intval($line['account_id']);
            if ($account_id == 0) continue; // Skip empty lines

            $third_party_id = !empty($line['third_party_id']) ? intval($line['third_party_id']) : null;
            $line_label = trim($line['label']);
            $debit = parse_number($line['debit']);
            $credit = parse_number($line['credit']);
            $vat_rate_id = !empty($line['vat_rate_id']) ? intval($line['vat_rate_id']) : null;
            $vat_base = !empty($line['vat_base']) ? parse_number($line['vat_base']) : null;
            $vat_amount = !empty($line['vat_amount']) ? parse_number($line['vat_amount']) : null;
            $due_date = !empty($line['due_date']) ? parse_date($line['due_date']) : null;

            // A line must have debit OR credit, not both (unless both are 0)
            if ($debit > 0 && $credit > 0) {
                $errors[] = "Ligne $line_no: une ligne ne peut avoir a la fois un debit et un credit.";
            }

            $line_data[] = array(
                'line_no' => $line_no,
                'account_id' => $account_id,
                'third_party_id' => $third_party_id,
                'label' => $line_label,
                'debit' => $debit,
                'credit' => $credit,
                'vat_rate_id' => $vat_rate_id,
                'vat_base' => $vat_base,
                'vat_amount' => $vat_amount,
                'due_date' => $due_date
            );

            $total_debit += $debit;
            $total_credit += $credit;
            $line_no++;
        }
    }

    if (count($line_data) < 2) {
        $errors[] = 'Une piece doit avoir au moins 2 lignes.';
    }

    // Validation for posting
    if ($action === 'post') {
        if (!validate_double_entry($total_debit, $total_credit)) {
            $errors[] = 'La piece n\'est pas equilibree (debit != credit).';
        }
        if (!$period_id) {
            $errors[] = 'Aucune periode definie pour cette date.';
        }
    }

    if (empty($errors)) {
        $user_id = auth_user_id();

        if ($entry_id == 0) {
            // Create new entry
            $sql = "INSERT INTO entries (journal_id, entry_date, period_id, label, status, total_debit, total_credit, created_by, created_at)
                    VALUES ($journal_id, '$entry_date', " . ($period_id ? $period_id : 'NULL') . ", '$label', 'draft', $total_debit, $total_credit, $user_id, NOW())";
            db_query($sql);
            $entry_id = db_insert_id();
            audit_log('CREATE', 'entries', $entry_id, 'Entry created as draft');
        } else {
            // Update existing entry
            $sql = "UPDATE entries SET journal_id = $journal_id, entry_date = '$entry_date',
                    period_id = " . ($period_id ? $period_id : 'NULL') . ", label = '$label',
                    total_debit = $total_debit, total_credit = $total_credit
                    WHERE id = $entry_id";
            db_query($sql);
            audit_log('UPDATE', 'entries', $entry_id, 'Entry updated');
        }

        // Delete and reinsert lines (legacy pattern)
        db_query("DELETE FROM entry_lines WHERE entry_id = $entry_id");

        foreach ($line_data as $ld) {
            $acc_id = $ld['account_id'];
            $tp_id = $ld['third_party_id'] ? $ld['third_party_id'] : 'NULL';
            $ll = db_escape($ld['label']);
            $d = $ld['debit'];
            $c = $ld['credit'];
            $vr_id = $ld['vat_rate_id'] ? $ld['vat_rate_id'] : 'NULL';
            $vb = $ld['vat_base'] !== null ? $ld['vat_base'] : 'NULL';
            $va = $ld['vat_amount'] !== null ? $ld['vat_amount'] : 'NULL';
            $dd = $ld['due_date'] ? "'" . db_escape($ld['due_date']) . "'" : 'NULL';

            $sql = "INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit, vat_rate_id, vat_base, vat_amount, due_date)
                    VALUES ($entry_id, {$ld['line_no']}, $acc_id, $tp_id, '$ll', $d, $c, $vr_id, $vb, $va, $dd)";
            db_query($sql);
        }

        // Handle file upload
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $upload_result = handle_upload('attachment', $entry_id);
            if (isset($upload_result['error'])) {
                set_flash('warning', 'Piece enregistree, mais erreur upload: ' . $upload_result['error']);
            } else {
                $fn = db_escape($upload_result['filename']);
                $sp = db_escape($upload_result['stored_path']);
                $sql = "INSERT INTO attachments (entry_id, filename, stored_path, uploaded_at)
                        VALUES ($entry_id, '$fn', '$sp', NOW())";
                db_query($sql);
            }
        }

        // Post the entry
        if ($action === 'post') {
            $piece_number = generate_piece_number($journal_id);
            $sql = "UPDATE entries SET status = 'posted', piece_number = '$piece_number', posted_at = NOW() WHERE id = $entry_id";
            db_query($sql);
            audit_log('POST_ENTRY', 'entries', $entry_id, "Entry posted as $piece_number");
            set_flash('success', "Piece validee: $piece_number");
        } else {
            set_flash('success', 'Piece enregistree en brouillon.');
        }

        redirect('/modules/entries/edit.php?id=' . $entry_id);
    } else {
        set_flash('error', implode('<br>', $errors));
    }
}

// Handle attachment delete
if (is_post() && post('action') === 'delete_attachment' && !$is_readonly) {
    require_csrf();
    $att_id = intval(post('attachment_id'));
    $sql = "SELECT stored_path FROM attachments WHERE id = $att_id AND entry_id = $entry_id";
    $result = db_query($sql);
    if (db_num_rows($result) > 0) {
        $att = db_fetch_assoc($result);
        @unlink('/var/www/html/uploads/' . $att['stored_path']);
        db_query("DELETE FROM attachments WHERE id = $att_id");
        set_flash('success', 'Piece jointe supprimee.');
    }
    redirect('/modules/entries/edit.php?id=' . $entry_id);
}

// Get data for dropdowns
$journals = get_journals();
$accounts = get_accounts();
$third_parties = get_third_parties();
$vat_rates = get_vat_rates();
?>

<h2><?php echo $page_title; ?></h2>

<?php if ($is_readonly): ?>
<div class="flash flash-warning">
    Cette piece est validee et ne peut plus etre modifiee.
</div>
<?php endif; ?>

<form method="post" action="" enctype="multipart/form-data" id="entry-form">
    <?php echo csrf_field(); ?>

    <!-- Header -->
    <div class="form-row mb-20">
        <div class="form-group">
            <label for="entry_date">Date *</label>
            <input type="date" id="entry_date" name="entry_date"
                   value="<?php echo $entry ? $entry['entry_date'] : date('Y-m-d'); ?>"
                   <?php echo $is_readonly ? 'readonly' : 'required'; ?>>
        </div>
        <div class="form-group">
            <label for="journal_id">Journal *</label>
            <select id="journal_id" name="journal_id" <?php echo $is_readonly ? 'disabled' : 'required'; ?>>
                <option value="">-- Choisir --</option>
                <?php foreach ($journals as $j): ?>
                <option value="<?php echo $j['id']; ?>"
                    <?php echo ($entry && $entry['journal_id'] == $j['id']) ? 'selected' : ''; ?>>
                    <?php echo h($j['code'] . ' - ' . $j['label']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="flex: 2;">
            <label for="label">Libelle *</label>
            <input type="text" id="label" name="label" style="width: 100%;"
                   value="<?php echo h($entry ? $entry['label'] : ''); ?>"
                   <?php echo $is_readonly ? 'readonly' : 'required'; ?>>
        </div>
        <?php if ($entry && $entry['piece_number']): ?>
        <div class="form-group">
            <label>N&deg; Piece</label>
            <input type="text" value="<?php echo h($entry['piece_number']); ?>" readonly style="background: #eee;">
        </div>
        <?php endif; ?>
    </div>

    <!-- Lines -->
    <h3>Lignes</h3>
    <table class="entry-lines-table" id="entry-lines-table">
        <thead>
            <tr>
                <th style="width: 30px;">#</th>
                <th class="col-account">Compte</th>
                <th class="col-third-party">Tiers</th>
                <th class="col-label">Libelle</th>
                <th class="col-debit">Debit</th>
                <th class="col-credit">Credit</th>
                <th class="col-vat">TVA</th>
                <th style="width: 100px;">Echeance</th>
                <?php if (!$is_readonly): ?>
                <th class="col-actions">Actions</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody id="entry-lines-body">
            <?php if (count($lines) > 0): ?>
                <?php foreach ($lines as $idx => $line): ?>
                <tr>
                    <td class="line-no"><?php echo $idx + 1; ?></td>
                    <td>
                        <select name="lines[<?php echo $idx; ?>][account_id]" class="line-account" <?php echo $is_readonly ? 'disabled' : ''; ?>>
                            <option value="">--</option>
                            <?php foreach ($accounts as $acc): ?>
                            <option value="<?php echo $acc['id']; ?>" data-type="<?php echo $acc['type']; ?>"
                                <?php echo $line['account_id'] == $acc['id'] ? 'selected' : ''; ?>>
                                <?php echo h($acc['code'] . ' - ' . $acc['label']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <select name="lines[<?php echo $idx; ?>][third_party_id]" class="line-third-party" <?php echo $is_readonly ? 'disabled' : ''; ?>>
                            <option value="">--</option>
                            <?php foreach ($third_parties as $tp): ?>
                            <option value="<?php echo $tp['id']; ?>"
                                <?php echo $line['third_party_id'] == $tp['id'] ? 'selected' : ''; ?>>
                                <?php echo h($tp['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <input type="text" name="lines[<?php echo $idx; ?>][label]" value="<?php echo h($line['label']); ?>" <?php echo $is_readonly ? 'readonly' : ''; ?>>
                    </td>
                    <td>
                        <input type="text" name="lines[<?php echo $idx; ?>][debit]" class="line-debit" value="<?php echo $line['debit'] > 0 ? number_format($line['debit'], 2, '.', '') : ''; ?>" <?php echo $is_readonly ? 'readonly' : ''; ?>>
                    </td>
                    <td>
                        <input type="text" name="lines[<?php echo $idx; ?>][credit]" class="line-credit" value="<?php echo $line['credit'] > 0 ? number_format($line['credit'], 2, '.', '') : ''; ?>" <?php echo $is_readonly ? 'readonly' : ''; ?>>
                    </td>
                    <td>
                        <select name="lines[<?php echo $idx; ?>][vat_rate_id]" class="line-vat" <?php echo $is_readonly ? 'disabled' : ''; ?>>
                            <option value="">--</option>
                            <?php foreach ($vat_rates as $vr): ?>
                            <option value="<?php echo $vr['id']; ?>" data-rate="<?php echo $vr['rate']; ?>"
                                <?php echo $line['vat_rate_id'] == $vr['id'] ? 'selected' : ''; ?>>
                                <?php echo h($vr['label']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <input type="date" name="lines[<?php echo $idx; ?>][due_date]" value="<?php echo $line['due_date']; ?>" <?php echo $is_readonly ? 'readonly' : ''; ?>>
                    </td>
                    <?php if (!$is_readonly): ?>
                    <td>
                        <button type="button" class="btn btn-small btn-danger btn-remove-line">X</button>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Line template (hidden) -->
    <table style="display:none;">
        <tbody>
            <tr id="line-template" class="hidden">
                <td class="line-no">0</td>
                <td>
                    <select name="lines[][account_id]" class="line-account">
                        <option value="">--</option>
                        <?php foreach ($accounts as $acc): ?>
                        <option value="<?php echo $acc['id']; ?>" data-type="<?php echo $acc['type']; ?>">
                            <?php echo h($acc['code'] . ' - ' . $acc['label']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <select name="lines[][third_party_id]" class="line-third-party">
                        <option value="">--</option>
                        <?php foreach ($third_parties as $tp): ?>
                        <option value="<?php echo $tp['id']; ?>"><?php echo h($tp['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><input type="text" name="lines[][label]"></td>
                <td><input type="text" name="lines[][debit]" class="line-debit"></td>
                <td><input type="text" name="lines[][credit]" class="line-credit"></td>
                <td>
                    <select name="lines[][vat_rate_id]" class="line-vat">
                        <option value="">--</option>
                        <?php foreach ($vat_rates as $vr): ?>
                        <option value="<?php echo $vr['id']; ?>" data-rate="<?php echo $vr['rate']; ?>"><?php echo h($vr['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><input type="date" name="lines[][due_date]"></td>
                <td><button type="button" class="btn btn-small btn-danger btn-remove-line">X</button></td>
            </tr>
        </tbody>
    </table>

    <?php if (!$is_readonly): ?>
    <div class="mt-10">
        <button type="button" class="btn btn-add-line">+ Ajouter ligne</button>
    </div>
    <?php endif; ?>

    <!-- Totals -->
    <div class="entry-totals">
        <span class="total-debit">Total Debit: <span id="total-debit">0.00</span> EUR</span>
        <span class="total-credit">Total Credit: <span id="total-credit">0.00</span> EUR</span>
        <span class="balance" id="balance">Equilibre</span>
    </div>

    <!-- Attachments -->
    <div class="mt-20">
        <h3>Pieces jointes</h3>
        <?php if (count($attachments) > 0): ?>
        <ul>
            <?php foreach ($attachments as $att): ?>
            <li>
                <a href="/uploads/<?php echo h($att['stored_path']); ?>" target="_blank">
                    <?php echo h($att['filename']); ?>
                </a>
                (<?php echo format_datetime($att['uploaded_at']); ?>)
                <?php if (!$is_readonly): ?>
                <form method="post" action="" style="display: inline;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="delete_attachment">
                    <input type="hidden" name="attachment_id" value="<?php echo $att['id']; ?>">
                    <button type="submit" class="btn btn-small btn-danger confirm-action" data-confirm="Supprimer cette piece jointe ?">X</button>
                </form>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <p>Aucune piece jointe.</p>
        <?php endif; ?>

        <?php if (!$is_readonly): ?>
        <div class="form-group">
            <label>Ajouter une piece jointe:</label>
            <input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.gif">
            <small>(PDF, JPG, PNG - max 5MB)</small>
        </div>
        <?php endif; ?>
    </div>

    <!-- Actions -->
    <?php if (!$is_readonly): ?>
    <div class="mt-20">
        <button type="submit" name="action" value="save" class="btn btn-primary">Enregistrer (brouillon)</button>
        <button type="submit" name="action" value="post" class="btn btn-success confirm-action"
                data-confirm="Valider cette piece ? Elle ne pourra plus etre modifiee.">Valider</button>
        <a href="/modules/entries/list.php" class="btn">Annuler</a>
    </div>
    <?php else: ?>
    <div class="mt-20">
        <a href="/modules/entries/list.php" class="btn">Retour a la liste</a>
        <a href="/modules/entries/pdf.php?id=<?php echo $entry_id; ?>" class="btn" target="_blank">Imprimer PDF</a>
    </div>
    <?php endif; ?>
</form>

<?php require_once __DIR__ . '/../../footer.php'; ?>
