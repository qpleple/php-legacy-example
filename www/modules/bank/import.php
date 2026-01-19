<?php
/**
 * Bank statement import - Legacy style
 */

$page_title = 'Import Releve Bancaire';
require_once __DIR__ . '/../../header.php';
require_role('accountant');

// Get bank accounts
$sql = "SELECT * FROM bank_accounts WHERE is_active = 1 ORDER BY label";
$bank_accounts = db_fetch_all(db_query($sql));

$import_results = null;

if (is_post()) {
    require_csrf();

    $bank_account_id = intval(post('bank_account_id'));

    if ($bank_account_id == 0) {
        set_flash('error', 'Veuillez selectionner un compte bancaire.');
    } elseif (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        set_flash('error', 'Erreur lors de l\'upload du fichier.');
    } else {
        $filename = db_escape($_FILES['csv_file']['name']);

        // Create statement
        $sql = "INSERT INTO bank_statements (bank_account_id, imported_at, source_filename)
                VALUES ($bank_account_id, NOW(), '$filename')";
        db_query($sql);
        $statement_id = db_insert_id();

        // Parse CSV
        $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
        $header = fgetcsv($file, 0, ';');

        $count = 0;
        $errors = array();
        $line_num = 1;

        while (($row = fgetcsv($file, 0, ';')) !== false) {
            $line_num++;

            if (count($row) < 3) {
                $errors[] = "Ligne $line_num: format invalide";
                continue;
            }

            // Format: date;label;amount;ref
            $line_date = parse_date(trim($row[0]));
            $label = db_escape(trim($row[1]));
            $amount = parse_number($row[2]);
            $ref = isset($row[3]) ? db_escape(trim($row[3])) : '';

            if (!$line_date) {
                $errors[] = "Ligne $line_num: date invalide";
                continue;
            }

            $sql = "INSERT INTO bank_statement_lines (statement_id, line_date, label, amount, ref, status)
                    VALUES ($statement_id, '$line_date', '$label', $amount, '$ref', 'unmatched')";
            db_query($sql);
            $count++;
        }

        fclose($file);

        audit_log('IMPORT', 'bank_statements', $statement_id, "Imported $count lines from $filename");

        $import_results = array(
            'statement_id' => $statement_id,
            'count' => $count,
            'errors' => $errors
        );
    }
}
?>

<h2>Import Releve Bancaire</h2>

<?php if ($import_results): ?>
<div class="mb-20" style="background: #d4edda; padding: 15px; border: 1px solid #c3e6cb;">
    <h3>Import reussi</h3>
    <p><strong>Lignes importees:</strong> <?php echo $import_results['count']; ?></p>

    <?php if (count($import_results['errors']) > 0): ?>
    <h4>Erreurs:</h4>
    <ul style="color: #dc3545;">
        <?php foreach ($import_results['errors'] as $err): ?>
        <li><?php echo h($err); ?></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <p>
        <a href="/modules/bank/reconcile.php?statement_id=<?php echo $import_results['statement_id']; ?>" class="btn btn-primary">
            Passer au rapprochement
        </a>
    </p>
</div>
<?php endif; ?>

<form method="post" action="" enctype="multipart/form-data">
    <?php echo csrf_field(); ?>

    <div class="form-group">
        <label for="bank_account_id">Compte bancaire *</label>
        <select id="bank_account_id" name="bank_account_id" required>
            <option value="">-- Choisir --</option>
            <?php foreach ($bank_accounts as $ba): ?>
            <option value="<?php echo $ba['id']; ?>"><?php echo h($ba['label']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="csv_file">Fichier CSV *</label>
        <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
    </div>

    <div class="form-group">
        <button type="submit" class="btn btn-primary">Importer</button>
        <a href="/modules/bank/reconcile.php" class="btn">Annuler</a>
    </div>
</form>

<div class="mt-20">
    <h3>Format du fichier CSV</h3>
    <p>Separateur: point-virgule (;)</p>
    <p>Colonnes attendues:</p>
    <ol>
        <li><strong>date</strong> - Format DD/MM/YYYY ou YYYY-MM-DD</li>
        <li><strong>label</strong> - Libelle de l'operation</li>
        <li><strong>amount</strong> - Montant (positif = credit, negatif = debit)</li>
        <li><strong>ref</strong> - Reference (optionnel)</li>
    </ol>

    <h4>Exemple:</h4>
    <pre style="background: #f5f5f5; padding: 10px; overflow-x: auto;">
date;label;amount;ref
02/01/2024;VIREMENT CLIENT DUPONT;1200,00;VIR001
05/01/2024;PRELEVEMENT EDF;-150,50;PREL001
10/01/2024;CHEQUE 123456;-500,00;CHQ123456
    </pre>
</div>

<!-- Recent imports -->
<div class="mt-20">
    <h3>Derniers imports</h3>
    <?php
    $sql = "SELECT bs.*, ba.label as bank_label,
                   (SELECT COUNT(*) FROM bank_statement_lines bsl WHERE bsl.statement_id = bs.id) as line_count,
                   (SELECT COUNT(*) FROM bank_statement_lines bsl WHERE bsl.statement_id = bs.id AND bsl.status = 'unmatched') as unmatched_count
            FROM bank_statements bs
            LEFT JOIN bank_accounts ba ON bs.bank_account_id = ba.id
            ORDER BY bs.imported_at DESC LIMIT 10";
    $statements = db_fetch_all(db_query($sql));
    ?>

    <?php if (count($statements) > 0): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>Date import</th>
                <th>Compte</th>
                <th>Fichier</th>
                <th>Lignes</th>
                <th>Non pointees</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($statements as $stmt): ?>
            <tr>
                <td><?php echo format_datetime($stmt['imported_at']); ?></td>
                <td><?php echo h($stmt['bank_label']); ?></td>
                <td><?php echo h($stmt['source_filename']); ?></td>
                <td><?php echo $stmt['line_count']; ?></td>
                <td>
                    <?php if ($stmt['unmatched_count'] > 0): ?>
                    <span style="color: #dc3545; font-weight: bold;"><?php echo $stmt['unmatched_count']; ?></span>
                    <?php else: ?>
                    <span style="color: #28a745;">0</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="/modules/bank/reconcile.php?statement_id=<?php echo $stmt['id']; ?>" class="btn btn-small">
                        Rapprocher
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p>Aucun import pour le moment.</p>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../footer.php'; ?>
