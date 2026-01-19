<?php
/**
 * Bank accounts management - Legacy style
 */

$page_title = 'Comptes Bancaires';
require_once __DIR__ . '/../../header.php';
require_role('accountant');

// Handle delete
if (is_post() && post('action') === 'delete') {
    require_csrf();
    $id = intval(post('id'));

    // Check if bank account has statements
    $sql = "SELECT COUNT(*) as count FROM bank_statements WHERE bank_account_id = $id";
    $result = db_query($sql);
    if (db_fetch_assoc($result)['count'] > 0) {
        set_flash('error', 'Impossible de supprimer: ce compte a des releves importes.');
    } else {
        db_query("DELETE FROM bank_accounts WHERE id = $id");
        audit_log('DELETE', 'bank_accounts', $id, 'Bank account deleted');
        set_flash('success', 'Compte bancaire supprime.');
    }
    redirect('/modules/bank/accounts.php');
}

// Handle create/update
if (is_post() && (post('action') === 'create' || post('action') === 'update')) {
    require_csrf();

    $id = intval(post('id'));
    $label = db_escape(trim(post('label')));
    $account_id = intval(post('account_id'));
    $is_active = post('is_active') ? 1 : 0;

    // Validation
    $errors = array();
    if (empty($label)) $errors[] = 'Le libelle est obligatoire.';
    if ($account_id == 0) $errors[] = 'Le compte comptable est obligatoire.';

    if (empty($errors)) {
        if (post('action') === 'create') {
            $sql = "INSERT INTO bank_accounts (label, account_id, is_active)
                    VALUES ('$label', $account_id, $is_active)";
            db_query($sql);
            $id = db_insert_id();
            audit_log('CREATE', 'bank_accounts', $id, "Bank account $label created");
            set_flash('success', 'Compte bancaire cree.');
        } else {
            $sql = "UPDATE bank_accounts SET label = '$label', account_id = $account_id, is_active = $is_active
                    WHERE id = $id";
            db_query($sql);
            audit_log('UPDATE', 'bank_accounts', $id, "Bank account $label updated");
            set_flash('success', 'Compte bancaire mis a jour.');
        }
        redirect('/modules/bank/accounts.php');
    } else {
        set_flash('error', implode(' ', $errors));
    }
}

// Get bank accounts
$sql = "SELECT ba.*, a.code as account_code, a.label as account_label,
               (SELECT COUNT(*) FROM bank_statements bs WHERE bs.bank_account_id = ba.id) as statement_count
        FROM bank_accounts ba
        LEFT JOIN accounts a ON ba.account_id = a.id
        ORDER BY ba.label";
$bank_accounts = db_fetch_all(db_query($sql));

// Get available accounts (512xxx)
$sql = "SELECT * FROM accounts WHERE code LIKE '512%' AND is_active = 1 ORDER BY code";
$accounts = db_fetch_all(db_query($sql));

// Edit mode
$edit_ba = null;
if (get('edit')) {
    $edit_id = intval(get('edit'));
    $sql = "SELECT * FROM bank_accounts WHERE id = $edit_id";
    $result = db_query($sql);
    if (db_num_rows($result) > 0) {
        $edit_ba = db_fetch_assoc($result);
    }
}
?>

<h2>Gestion des Comptes Bancaires</h2>

<!-- Add/Edit Form -->
<div class="mb-20" style="background: #f9f9f9; padding: 15px; border: 1px solid #ccc;">
    <h3><?php echo $edit_ba ? 'Modifier le compte' : 'Nouveau compte bancaire'; ?></h3>
    <form method="post" action="">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="<?php echo $edit_ba ? 'update' : 'create'; ?>">
        <input type="hidden" name="id" value="<?php echo $edit_ba ? $edit_ba['id'] : 0; ?>">

        <div class="form-row">
            <div class="form-group">
                <label for="label">Libelle *</label>
                <input type="text" id="label" name="label" style="width: 250px;"
                       value="<?php echo h($edit_ba ? $edit_ba['label'] : ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="account_id">Compte comptable (512xxx) *</label>
                <select id="account_id" name="account_id" required>
                    <option value="">-- Choisir --</option>
                    <?php foreach ($accounts as $acc): ?>
                    <option value="<?php echo $acc['id']; ?>"
                        <?php echo ($edit_ba && $edit_ba['account_id'] == $acc['id']) ? 'selected' : ''; ?>>
                        <?php echo h($acc['code'] . ' - ' . $acc['label']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_active" value="1"
                           <?php echo (!$edit_ba || $edit_ba['is_active']) ? 'checked' : ''; ?>>
                    Actif
                </label>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">
            <?php echo $edit_ba ? 'Mettre a jour' : 'Creer'; ?>
        </button>
        <?php if ($edit_ba): ?>
        <a href="/modules/bank/accounts.php" class="btn">Annuler</a>
        <?php endif; ?>
    </form>
</div>

<!-- Bank accounts table -->
<table class="data-table">
    <thead>
        <tr>
            <th>Libelle</th>
            <th>Compte comptable</th>
            <th>Nb releves</th>
            <th>Actif</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($bank_accounts as $ba): ?>
        <tr>
            <td><strong><?php echo h($ba['label']); ?></strong></td>
            <td><?php echo h($ba['account_code'] . ' - ' . $ba['account_label']); ?></td>
            <td><?php echo $ba['statement_count']; ?></td>
            <td><?php echo $ba['is_active'] ? 'Oui' : 'Non'; ?></td>
            <td class="actions">
                <a href="?edit=<?php echo $ba['id']; ?>" class="btn btn-small">Modifier</a>
                <?php if ($ba['statement_count'] == 0): ?>
                <form method="post" action="" style="display: inline;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo $ba['id']; ?>">
                    <button type="submit" class="btn btn-small btn-danger confirm-action"
                            data-confirm="Supprimer ce compte ?">Supprimer</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/../../footer.php'; ?>
