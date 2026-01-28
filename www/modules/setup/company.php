<?php
/**
 * Company settings page - Legacy style
 */

require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/utils.php';

require_login();

// Handle form submission
if (is_post()) {
    csrf_verify();

    $currency = db_escape(trim(post('currency')));
    $fiscal_year_start = db_escape(parse_date(post('fiscal_year_start')));
    $fiscal_year_end = db_escape(parse_date(post('fiscal_year_end')));

    // Validation
    $errors = array();
    if (empty($fiscal_year_start) || empty($fiscal_year_end)) {
        $errors[] = 'Les dates de l\'exercice sont obligatoires.';
    }
    if ($fiscal_year_start >= $fiscal_year_end) {
        $errors[] = 'La date de fin doit être postérieure à la date de début.';
    }

    if (empty($errors)) {
        // Check if company exists
        $sql = "SELECT id FROM company WHERE id = 1";
        $result = db_query($sql);

        if (db_num_rows($result) > 0) {
            // Update
            $sql = "UPDATE company SET
                    currency = '$currency',
                    fiscal_year_start = '$fiscal_year_start',
                    fiscal_year_end = '$fiscal_year_end'
                    WHERE id = 1";
        } else {
            // Insert
            $sql = "INSERT INTO company (id, name, currency, fiscal_year_start, fiscal_year_end)
                    VALUES (1, 'Ketchup Compta', '$currency', '$fiscal_year_start', '$fiscal_year_end')";
        }

        db_query($sql);
        audit_log('UPDATE', 'company', 1, 'Company settings updated');
        set_flash('success', 'Paramètres de la société enregistrés.');
        redirect('/modules/setup/company.php');
    } else {
        set_flash('error', implode(' ', $errors));
    }
}

// Get current company data
$company = get_company();
if (!$company) {
    $company = array(
        'currency' => 'EUR',
        'fiscal_year_start' => date('Y') . '-01-01',
        'fiscal_year_end' => date('Y') . '-12-31'
    );
}

$page_title = 'Paramétrage Société';
require_once __DIR__ . '/../../header.php';
?>

<h2>Paramétrage de la Société</h2>

<form method="post" action="" class="validate">
    <?php echo csrf_field(); ?>

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
            <label for="fiscal_year_start">Début exercice *</label>
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
        <button type="submit" class="btn btn-primary">Enregistrer</button>
    </div>
</form>

<div class="mt-20">
    <p><strong>Note :</strong> La modification des dates d'exercice ne régénère pas automatiquement les périodes.
    Utilisez la page <a href="/modules/setup/periods.php">Périodes</a> pour régénérer les périodes mensuelles.</p>
</div>

<?php require_once __DIR__ . '/../../footer.php'; ?>
