<?php
/**
 * Company settings page - Legacy style
 */

$page_title = 'Parametrage Societe';
require_once __DIR__ . '/../../header.php';
require_role('admin');

// Handle form submission
if (is_post()) {
    require_csrf();

    $name = db_escape(trim(post('name')));
    $currency = db_escape(trim(post('currency')));
    $fiscal_year_start = db_escape(parse_date(post('fiscal_year_start')));
    $fiscal_year_end = db_escape(parse_date(post('fiscal_year_end')));
    $carry_forward_account = db_escape(trim(post('carry_forward_account')));

    // Validation
    $errors = array();
    if (empty($name)) {
        $errors[] = 'Le nom de la societe est obligatoire.';
    }
    if (empty($fiscal_year_start) || empty($fiscal_year_end)) {
        $errors[] = 'Les dates de l\'exercice sont obligatoires.';
    }
    if ($fiscal_year_start >= $fiscal_year_end) {
        $errors[] = 'La date de fin doit etre posterieure a la date de debut.';
    }

    if (empty($errors)) {
        // Check if company exists
        $sql = "SELECT id FROM company WHERE id = 1";
        $result = db_query($sql);

        if (db_num_rows($result) > 0) {
            // Update
            $sql = "UPDATE company SET
                    name = '$name',
                    currency = '$currency',
                    fiscal_year_start = '$fiscal_year_start',
                    fiscal_year_end = '$fiscal_year_end',
                    carry_forward_account = '$carry_forward_account'
                    WHERE id = 1";
        } else {
            // Insert
            $sql = "INSERT INTO company (id, name, currency, fiscal_year_start, fiscal_year_end, carry_forward_account)
                    VALUES (1, '$name', '$currency', '$fiscal_year_start', '$fiscal_year_end', '$carry_forward_account')";
        }

        db_query($sql);
        audit_log('UPDATE', 'company', 1, 'Company settings updated');
        set_flash('success', 'Parametres de la societe enregistres.');
        redirect('/modules/setup/company.php');
    } else {
        set_flash('error', implode(' ', $errors));
    }
}

// Get current company data
$company = get_company();
if (!$company) {
    $company = array(
        'name' => '',
        'currency' => 'EUR',
        'fiscal_year_start' => date('Y') . '-01-01',
        'fiscal_year_end' => date('Y') . '-12-31',
        'carry_forward_account' => '110000'
    );
}
?>

<h2>Parametrage de la Societe</h2>

<form method="post" action="" class="validate">
    <?php echo csrf_field(); ?>

    <div class="form-group">
        <label for="name">Nom de la societe *</label>
        <input type="text" id="name" name="name" value="<?php echo h($company['name']); ?>" required>
    </div>

    <div class="form-group">
        <label for="currency">Devise</label>
        <select id="currency" name="currency">
            <option value="EUR" <?php echo $company['currency'] == 'EUR' ? 'selected' : ''; ?>>EUR - Euro</option>
            <option value="USD" <?php echo $company['currency'] == 'USD' ? 'selected' : ''; ?>>USD - Dollar US</option>
            <option value="GBP" <?php echo $company['currency'] == 'GBP' ? 'selected' : ''; ?>>GBP - Livre Sterling</option>
            <option value="CHF" <?php echo $company['currency'] == 'CHF' ? 'selected' : ''; ?>>CHF - Franc Suisse</option>
        </select>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label for="fiscal_year_start">Debut exercice *</label>
            <input type="date" id="fiscal_year_start" name="fiscal_year_start"
                   value="<?php echo h($company['fiscal_year_start']); ?>" required>
        </div>

        <div class="form-group">
            <label for="fiscal_year_end">Fin exercice *</label>
            <input type="date" id="fiscal_year_end" name="fiscal_year_end"
                   value="<?php echo h($company['fiscal_year_end']); ?>" required>
        </div>
    </div>

    <div class="form-group">
        <label for="carry_forward_account">Compte de report a nouveau</label>
        <input type="text" id="carry_forward_account" name="carry_forward_account"
               value="<?php echo h($company['carry_forward_account']); ?>">
        <small>Code du compte utilise pour les a-nouveaux (ex: 110000)</small>
    </div>

    <div class="form-group">
        <button type="submit" class="btn btn-primary">Enregistrer</button>
    </div>
</form>

<div class="mt-20">
    <p><strong>Note:</strong> La modification des dates d'exercice ne regenere pas automatiquement les periodes.
    Utilisez la page <a href="/modules/setup/periods.php">Periodes</a> pour regenerer les periodes mensuelles.</p>
</div>

<?php require_once __DIR__ . '/../../footer.php'; ?>
