<?php
/**
 * VAT rates management - Legacy style
 */

require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/utils.php';

require_login();
require_role('admin');

// Handle delete
if (is_post() && post('action') === 'delete') {
    csrf_verify();
    $id = intval(post('id'));

    // Check if VAT rate is used
    $sql = "SELECT COUNT(*) as count FROM entry_lines WHERE vat_rate_id = $id";
    $result = db_query($sql);
    if (db_fetch_assoc($result)['count'] > 0) {
        set_flash('error', 'Impossible de supprimer: ce taux est utilise dans des ecritures.');
    } else {
        db_query("DELETE FROM vat_rates WHERE id = $id");
        audit_log('DELETE', 'vat_rates', $id, 'VAT rate deleted');
        set_flash('success', 'Taux de TVA supprime.');
    }
    redirect('/modules/setup/vat.php');
}

// Handle create/update
if (is_post() && (post('action') === 'create' || post('action') === 'update')) {
    csrf_verify();

    $id = intval(post('id'));
    $label = db_escape(trim(post('label')));
    $rate = floatval(str_replace(',', '.', post('rate')));
    $account_collected = db_escape(trim(post('account_collected')));
    $account_deductible = db_escape(trim(post('account_deductible')));
    $is_active = post('is_active') ? 1 : 0;

    // Validation
    $errors = array();
    if (empty($label)) $errors[] = 'Le libelle est obligatoire.';
    if ($rate < 0 || $rate > 100) $errors[] = 'Le taux doit etre entre 0 et 100.';
    if (empty($account_collected)) $errors[] = 'Le compte TVA collectee est obligatoire.';
    if (empty($account_deductible)) $errors[] = 'Le compte TVA deductible est obligatoire.';

    if (empty($errors)) {
        if (post('action') === 'create') {
            $sql = "INSERT INTO vat_rates (label, rate, account_collected, account_deductible, is_active)
                    VALUES ('$label', $rate, '$account_collected', '$account_deductible', $is_active)";
            db_query($sql);
            $id = db_insert_id();
            audit_log('CREATE', 'vat_rates', $id, "VAT rate $label created");
            set_flash('success', 'Taux de TVA cree.');
        } else {
            $sql = "UPDATE vat_rates SET label = '$label', rate = $rate, account_collected = '$account_collected',
                    account_deductible = '$account_deductible', is_active = $is_active WHERE id = $id";
            db_query($sql);
            audit_log('UPDATE', 'vat_rates', $id, "VAT rate $label updated");
            set_flash('success', 'Taux de TVA mis a jour.');
        }
        redirect('/modules/setup/vat.php');
    } else {
        set_flash('error', implode(' ', $errors));
    }
}

// Toggle active status
if (is_post() && post('action') === 'toggle') {
    csrf_verify();
    $id = intval(post('id'));

    $sql = "UPDATE vat_rates SET is_active = NOT is_active WHERE id = $id";
    db_query($sql);
    audit_log('UPDATE', 'vat_rates', $id, 'VAT rate active status toggled');
    set_flash('success', 'Statut mis a jour.');
    redirect('/modules/setup/vat.php');
}

// Get all VAT rates
$sql = "SELECT v.*, (SELECT COUNT(*) FROM entry_lines el WHERE el.vat_rate_id = v.id) as usage_count
        FROM vat_rates v ORDER BY rate DESC";
$vat_rates = db_fetch_all(db_query($sql));

// Edit mode
$edit_vat = null;
if (get('edit')) {
    $edit_id = intval(get('edit'));
    $sql = "SELECT * FROM vat_rates WHERE id = $edit_id";
    $result = db_query($sql);
    if (db_num_rows($result) > 0) {
        $edit_vat = db_fetch_assoc($result);
    }
}

$page_title = 'Taux de TVA';
require_once __DIR__ . '/../../header.php';
?>

<h2>Gestion des Taux de TVA</h2>

<!-- Add/Edit Form -->
<div class="mb-20" style="background: #f9f9f9; padding: 15px; border: 1px solid #ccc;">
    <h3><?php echo $edit_vat ? 'Modifier le taux' : 'Nouveau taux de TVA'; ?></h3>
    <form method="post" action="">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="<?php echo $edit_vat ? 'update' : 'create'; ?>">
        <input type="hidden" name="id" value="<?php echo $edit_vat ? $edit_vat['id'] : 0; ?>">

        <div class="form-row">
            <div class="form-group">
                <label for="label">Libelle *</label>
                <input type="text" id="label" name="label" style="width: 150px;"
                       value="<?php echo h($edit_vat ? $edit_vat['label'] : ''); ?>" required
                       placeholder="ex: TVA 20%">
            </div>
            <div class="form-group">
                <label for="rate">Taux (%) *</label>
                <input type="text" id="rate" name="rate" style="width: 80px;"
                       value="<?php echo $edit_vat ? number_format($edit_vat['rate'], 2, ',', '') : ''; ?>" required
                       placeholder="ex: 20,00">
            </div>
            <div class="form-group">
                <label for="account_collected">Compte TVA collectee *</label>
                <input type="text" id="account_collected" name="account_collected" style="width: 100px;"
                       value="<?php echo h($edit_vat ? $edit_vat['account_collected'] : '44571'); ?>" required
                       placeholder="ex: 44571">
            </div>
            <div class="form-group">
                <label for="account_deductible">Compte TVA deductible *</label>
                <input type="text" id="account_deductible" name="account_deductible" style="width: 100px;"
                       value="<?php echo h($edit_vat ? $edit_vat['account_deductible'] : '44566'); ?>" required
                       placeholder="ex: 44566">
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_active" value="1"
                           <?php echo (!$edit_vat || $edit_vat['is_active']) ? 'checked' : ''; ?>>
                    Actif
                </label>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">
            <?php echo $edit_vat ? 'Mettre a jour' : 'Creer'; ?>
        </button>
        <?php if ($edit_vat): ?>
        <a href="/modules/setup/vat.php" class="btn">Annuler</a>
        <?php endif; ?>
    </form>
</div>

<!-- VAT rates table -->
<table class="data-table">
    <thead>
        <tr>
            <th>Libelle</th>
            <th>Taux</th>
            <th>Compte collectee</th>
            <th>Compte deductible</th>
            <th>Utilisations</th>
            <th>Actif</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($vat_rates as $vat): ?>
        <tr>
            <td><strong><?php echo h($vat['label']); ?></strong></td>
            <td class="number"><?php echo number_format($vat['rate'], 2, ',', ' '); ?> %</td>
            <td><?php echo h($vat['account_collected']); ?></td>
            <td><?php echo h($vat['account_deductible']); ?></td>
            <td><?php echo $vat['usage_count']; ?></td>
            <td>
                <form method="post" action="" style="display: inline;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?php echo $vat['id']; ?>">
                    <button type="submit" class="btn btn-small <?php echo $vat['is_active'] ? 'btn-success' : ''; ?>">
                        <?php echo $vat['is_active'] ? 'Oui' : 'Non'; ?>
                    </button>
                </form>
            </td>
            <td class="actions">
                <a href="?edit=<?php echo $vat['id']; ?>" class="btn btn-small">Modifier</a>
                <?php if ($vat['usage_count'] == 0): ?>
                <form method="post" action="" style="display: inline;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo $vat['id']; ?>">
                    <button type="submit" class="btn btn-small btn-danger confirm-action"
                            data-confirm="Supprimer ce taux ?">Supprimer</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="mt-20">
    <h3>Fonctionnement de la TVA</h3>
    <ul>
        <li><strong>TVA collectee:</strong> sur les ventes, creditee au compte 44571</li>
        <li><strong>TVA deductible:</strong> sur les achats, debitee au compte 44566</li>
        <li>Le calcul de TVA se fait: montant_HT * taux / 100</li>
    </ul>
</div>

<?php require_once __DIR__ . '/../../footer.php'; ?>
