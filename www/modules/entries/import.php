<?php
/**
 * CSV Import page - Legacy style
 */

$page_title = 'Import CSV Ecritures';
require_once __DIR__ . '/../../header.php';
require_role('accountant');

$import_results = null;

if (is_post()) {
    require_csrf();

    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        set_flash('error', 'Erreur lors de l\'upload du fichier.');
    } else {
        $auto_post = post('auto_post') ? true : false;
        $file = fopen($_FILES['csv_file']['tmp_name'], 'r');

        // Skip header
        $header = fgetcsv($file, 0, ';');

        // Group lines by piece_ref
        $pieces = array();
        $line_num = 1;
        $errors = array();

        while (($row = fgetcsv($file, 0, ';')) !== false) {
            $line_num++;

            if (count($row) < 7) {
                $errors[] = "Ligne $line_num: format invalide";
                continue;
            }

            // Parse CSV: date;journal_code;label;account_code;third_party_name;debit;credit;vat_rate_label;due_date;piece_ref
            $date = parse_date(trim($row[0]));
            $journal_code = trim($row[1]);
            $label = trim($row[2]);
            $account_code = trim($row[3]);
            $third_party_name = isset($row[4]) ? trim($row[4]) : '';
            $debit = parse_number(isset($row[5]) ? $row[5] : 0);
            $credit = parse_number(isset($row[6]) ? $row[6] : 0);
            $vat_label = isset($row[7]) ? trim($row[7]) : '';
            $due_date = isset($row[8]) ? parse_date(trim($row[8])) : null;
            $piece_ref = isset($row[9]) ? trim($row[9]) : 'piece_' . $line_num;

            // Validate
            if (!$date) {
                $errors[] = "Ligne $line_num: date invalide";
                continue;
            }

            // Find journal
            $journal_code_esc = db_escape($journal_code);
            $sql = "SELECT id FROM journals WHERE code = '$journal_code_esc'";
            $result = db_query($sql);
            if (db_num_rows($result) == 0) {
                $errors[] = "Ligne $line_num: journal '$journal_code' introuvable";
                continue;
            }
            $journal_id = db_fetch_assoc($result)['id'];

            // Find account
            $account_code_esc = db_escape($account_code);
            $sql = "SELECT id FROM accounts WHERE code = '$account_code_esc'";
            $result = db_query($sql);
            if (db_num_rows($result) == 0) {
                $errors[] = "Ligne $line_num: compte '$account_code' introuvable";
                continue;
            }
            $account_id = db_fetch_assoc($result)['id'];

            // Find third party (optional)
            $third_party_id = null;
            if (!empty($third_party_name)) {
                $tp_name_esc = db_escape($third_party_name);
                $sql = "SELECT id FROM third_parties WHERE name = '$tp_name_esc'";
                $result = db_query($sql);
                if (db_num_rows($result) > 0) {
                    $third_party_id = db_fetch_assoc($result)['id'];
                }
            }

            // Find VAT rate (optional)
            $vat_rate_id = null;
            if (!empty($vat_label)) {
                $vat_label_esc = db_escape($vat_label);
                $sql = "SELECT id FROM vat_rates WHERE label = '$vat_label_esc'";
                $result = db_query($sql);
                if (db_num_rows($result) > 0) {
                    $vat_rate_id = db_fetch_assoc($result)['id'];
                }
            }

            // Group by piece_ref
            if (!isset($pieces[$piece_ref])) {
                $pieces[$piece_ref] = array(
                    'date' => $date,
                    'journal_id' => $journal_id,
                    'label' => $label,
                    'lines' => array()
                );
            }

            $pieces[$piece_ref]['lines'][] = array(
                'account_id' => $account_id,
                'third_party_id' => $third_party_id,
                'label' => $label,
                'debit' => $debit,
                'credit' => $credit,
                'vat_rate_id' => $vat_rate_id,
                'due_date' => $due_date
            );
        }

        fclose($file);

        // Create entries
        $created = 0;
        $posted = 0;
        $user_id = auth_user_id();

        foreach ($pieces as $ref => $piece) {
            // Calculate totals
            $total_debit = 0;
            $total_credit = 0;
            foreach ($piece['lines'] as $line) {
                $total_debit += $line['debit'];
                $total_credit += $line['credit'];
            }

            // Get period
            $period = get_period_for_date($piece['date']);
            $period_id = $period ? $period['id'] : 'NULL';

            // Check if can post
            $can_post = $auto_post &&
                        validate_double_entry($total_debit, $total_credit) &&
                        $period && $period['status'] === 'open';

            // Create entry
            $date_esc = db_escape($piece['date']);
            $label_esc = db_escape($piece['label']);

            $sql = "INSERT INTO entries (journal_id, entry_date, period_id, label, status, total_debit, total_credit, created_by, created_at)
                    VALUES ({$piece['journal_id']}, '$date_esc', $period_id, '$label_esc', 'draft', $total_debit, $total_credit, $user_id, NOW())";
            db_query($sql);
            $entry_id = db_insert_id();
            $created++;

            // Create lines
            $line_no = 1;
            foreach ($piece['lines'] as $line) {
                $tp_id = $line['third_party_id'] ? $line['third_party_id'] : 'NULL';
                $ll = db_escape($line['label']);
                $vr_id = $line['vat_rate_id'] ? $line['vat_rate_id'] : 'NULL';
                $dd = $line['due_date'] ? "'" . db_escape($line['due_date']) . "'" : 'NULL';

                $sql = "INSERT INTO entry_lines (entry_id, line_no, account_id, third_party_id, label, debit, credit, vat_rate_id, due_date)
                        VALUES ($entry_id, $line_no, {$line['account_id']}, $tp_id, '$ll', {$line['debit']}, {$line['credit']}, $vr_id, $dd)";
                db_query($sql);
                $line_no++;
            }

            // Post if requested and valid
            if ($can_post) {
                $piece_number = generate_piece_number($piece['journal_id']);
                $sql = "UPDATE entries SET status = 'posted', piece_number = '$piece_number', posted_at = NOW() WHERE id = $entry_id";
                db_query($sql);
                $posted++;
            }
        }

        audit_log('IMPORT', 'entries', 0, "Imported $created entries, $posted posted");

        $import_results = array(
            'created' => $created,
            'posted' => $posted,
            'errors' => $errors
        );
    }
}
?>

<h2>Import CSV Ecritures</h2>

<?php if ($import_results): ?>
<div class="mb-20" style="background: #f9f9f9; padding: 15px; border: 1px solid #ccc;">
    <h3>Resultat de l'import</h3>
    <p><strong>Pieces creees:</strong> <?php echo $import_results['created']; ?></p>
    <p><strong>Pieces validees:</strong> <?php echo $import_results['posted']; ?></p>

    <?php if (count($import_results['errors']) > 0): ?>
    <h4>Erreurs:</h4>
    <ul style="color: #dc3545;">
        <?php foreach ($import_results['errors'] as $err): ?>
        <li><?php echo h($err); ?></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>
<?php endif; ?>

<form method="post" action="" enctype="multipart/form-data">
    <?php echo csrf_field(); ?>

    <div class="form-group">
        <label for="csv_file">Fichier CSV *</label>
        <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
    </div>

    <div class="form-group">
        <label>
            <input type="checkbox" name="auto_post" value="1">
            Valider automatiquement les pieces equilibrees (si periode ouverte)
        </label>
    </div>

    <div class="form-group">
        <button type="submit" class="btn btn-primary">Importer</button>
        <a href="/modules/entries/list.php" class="btn">Annuler</a>
    </div>
</form>

<div class="mt-20">
    <h3>Format du fichier CSV</h3>
    <p>Separateur: point-virgule (;)</p>
    <p>Colonnes attendues:</p>
    <ol>
        <li><strong>date</strong> - Format DD/MM/YYYY ou YYYY-MM-DD</li>
        <li><strong>journal_code</strong> - Code du journal (VE, AC, BK, OD)</li>
        <li><strong>label</strong> - Libelle de la piece</li>
        <li><strong>account_code</strong> - Code du compte</li>
        <li><strong>third_party_name</strong> - Nom du tiers (optionnel)</li>
        <li><strong>debit</strong> - Montant debit</li>
        <li><strong>credit</strong> - Montant credit</li>
        <li><strong>vat_rate_label</strong> - Libelle du taux TVA (optionnel)</li>
        <li><strong>due_date</strong> - Date d'echeance (optionnel)</li>
        <li><strong>piece_ref</strong> - Reference pour regrouper les lignes d'une meme piece</li>
    </ol>

    <h4>Exemple:</h4>
    <pre style="background: #f5f5f5; padding: 10px; overflow-x: auto;">
date;journal_code;label;account_code;third_party_name;debit;credit;vat_rate_label;due_date;piece_ref
15/01/2024;VE;Facture Client Dupont;411001;Client Dupont;1200,00;0;TVA 20%;;FAC001
15/01/2024;VE;Facture Client Dupont;707000;;0;1000,00;;;FAC001
15/01/2024;VE;Facture Client Dupont;44571;;0;200,00;;;FAC001
    </pre>
</div>

<?php require_once __DIR__ . '/../../footer.php'; ?>
