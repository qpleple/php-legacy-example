<?php
/**
 * Chart of accounts management - Legacy style
 */

require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/utils.php';

require_login();

// Handle delete
if (is_post() && post('action') === 'delete') {
    csrf_verify();
    $id = intval(post('id'));

    // Check if account is used
    $sql = "SELECT COUNT(*) as count FROM entry_lines WHERE account_id = $id";
    $result = db_query($sql);
    if (db_fetch_assoc($result)['count'] > 0) {
        set_flash('error', 'Impossible de supprimer : ce compte est utilisé dans des écritures.');
    } else {
        db_query("DELETE FROM accounts WHERE id = $id");
        audit_log('DELETE', 'accounts', $id, 'Account deleted');
        set_flash('success', 'Compte supprimé.');
    }
    redirect('/modules/setup/accounts.php');
}

// Handle create/update
if (is_post() && (post('action') === 'create' || post('action') === 'update')) {
    csrf_verify();

    $id = intval(post('id'));
    $code = db_escape(trim(post('code')));
    $label = db_escape(trim(post('label')));
    $type = db_escape(post('type'));
    $is_active = post('is_active') ? 1 : 0;

    // Validation
    $errors = array();
    if (empty($code)) $errors[] = 'Le code est obligatoire.';
    if (empty($label)) $errors[] = 'Le libellé est obligatoire.';

    // Check unique code
    $sql = "SELECT id FROM accounts WHERE code = '$code' AND id != $id";
    $result = db_query($sql);
    if (db_num_rows($result) > 0) {
        $errors[] = 'Ce code de compte existe déjà.';
    }

    if (empty($errors)) {
        if (post('action') === 'create') {
            $sql = "INSERT INTO accounts (code, label, type, is_active)
                    VALUES ('$code', '$label', '$type', $is_active)";
            db_query($sql);
            $id = db_insert_id();
            audit_log('CREATE', 'accounts', $id, "Account $code created");
            set_flash('success', 'Compte créé.');
        } else {
            $sql = "UPDATE accounts SET code = '$code', label = '$label', type = '$type', is_active = $is_active
                    WHERE id = $id";
            db_query($sql);
            audit_log('UPDATE', 'accounts', $id, "Account $code updated");
            set_flash('success', 'Compte mis à jour.');
        }
        redirect('/modules/setup/accounts.php');
    } else {
        set_flash('error', implode(' ', $errors));
    }
}

// Handle CSV import
if (is_post() && post('action') === 'import') {
    csrf_verify();

    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        set_flash('error', 'Erreur lors de l\'upload du fichier.');
    } else {
        $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
        $count = 0;
        $errors = array();

        // Skip header
        fgetcsv($file, 0, ';');

        while (($row = fgetcsv($file, 0, ';')) !== false) {
            if (count($row) < 2) continue;

            $code = db_escape(trim($row[0]));
            $label = db_escape(trim($row[1]));
            $type = isset($row[2]) ? db_escape(trim($row[2])) : 'general';
            $is_active = isset($row[3]) ? (intval($row[3]) ? 1 : 0) : 1;

            if (empty($code) || empty($label)) continue;

            // Check if exists
            $sql = "SELECT id FROM accounts WHERE code = '$code'";
            $result = db_query($sql);

            if (db_num_rows($result) > 0) {
                // Update
                $sql = "UPDATE accounts SET label = '$label', type = '$type', is_active = $is_active WHERE code = '$code'";
            } else {
                // Insert
                $sql = "INSERT INTO accounts (code, label, type, is_active) VALUES ('$code', '$label', '$type', $is_active)";
            }

            db_query($sql);
            $count++;
        }

        fclose($file);
        audit_log('IMPORT', 'accounts', 0, "Imported $count accounts from CSV");
        set_flash('success', "$count comptes importés.");
        redirect('/modules/setup/accounts.php');
    }
}

// Filters
$search = get('search', '');
$type_filter = get('type', '');

// Build query
$where = "1=1";
if ($search) {
    $search_esc = db_escape($search);
    $where .= " AND (code LIKE '%$search_esc%' OR label LIKE '%$search_esc%')";
}
if ($type_filter) {
    $type_esc = db_escape($type_filter);
    $where .= " AND type = '$type_esc'";
}

// Pagination
$sql = "SELECT COUNT(*) as count FROM accounts WHERE $where";
$total = db_fetch_assoc(db_query($sql))['count'];
$page = max(1, intval(get('page', 1)));
$pagination = paginate($total, $page, 50);

// Get accounts
$sql = "SELECT * FROM accounts WHERE $where ORDER BY code LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}";
$accounts = db_fetch_all(db_query($sql));

// Edit mode
$edit_account = null;
if (get('edit')) {
    $edit_id = intval(get('edit'));
    $sql = "SELECT * FROM accounts WHERE id = $edit_id";
    $result = db_query($sql);
    if (db_num_rows($result) > 0) {
        $edit_account = db_fetch_assoc($result);
    }
}

$page_title = 'Plan Comptable';
require_once __DIR__ . '/../../header.php';
?>

<h2>Plan Comptable</h2>

<!-- Add/Edit Form -->
<div class="mb-20" style="background: #f9f9f9; padding: 15px; border: 1px solid #ccc;">
    <h3><?php echo $edit_account ? 'Modifier le compte' : 'Nouveau compte'; ?></h3>
    <form method="post" action="">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="<?php echo $edit_account ? 'update' : 'create'; ?>">
        <input type="hidden" name="id" value="<?php echo $edit_account ? $edit_account['id'] : 0; ?>">

        <div class="form-row">
            <div class="form-group">
                <label for="code">Code *</label>
                <input type="text" id="code" name="code"
                       value="<?php echo h($edit_account ? $edit_account['code'] : ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="label">Libellé *</label>
                <input type="text" id="label" name="label" style="width: 300px;"
                       value="<?php echo h($edit_account ? $edit_account['label'] : ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="type">Type</label>
                <select id="type" name="type">
                    <option value="general" <?php echo ($edit_account && $edit_account['type'] == 'general') ? 'selected' : ''; ?>>Général</option>
                    <option value="customer" <?php echo ($edit_account && $edit_account['type'] == 'customer') ? 'selected' : ''; ?>>Client</option>
                    <option value="vendor" <?php echo ($edit_account && $edit_account['type'] == 'vendor') ? 'selected' : ''; ?>>Fournisseur</option>
                </select>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_active" value="1"
                           <?php echo (!$edit_account || $edit_account['is_active']) ? 'checked' : ''; ?>>
                    Actif
                </label>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">
            <?php echo $edit_account ? 'Mettre à jour' : 'Créer'; ?>
        </button>
        <?php if ($edit_account): ?>
        <a href="/modules/setup/accounts.php" class="btn">Annuler</a>
        <?php endif; ?>
    </form>
</div>

<!-- Import CSV -->
<div class="mb-20">
    <form method="post" action="" enctype="multipart/form-data" style="display: inline;">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="import">
        <label>Import CSV: </label>
        <input type="file" name="csv_file" accept=".csv">
        <button type="submit" class="btn btn-small">Importer</button>
        <small>(Format: code;libelle;type;actif)</small>
    </form>
</div>

<!-- Filters -->
<div class="filters">
    <form method="get" action="">
        <label>Recherche:</label>
        <input type="text" name="search" value="<?php echo h($search); ?>" placeholder="Code ou libellé">
        <label>Type:</label>
        <select name="type">
            <option value="">Tous</option>
            <option value="general" <?php echo $type_filter == 'general' ? 'selected' : ''; ?>>Général</option>
            <option value="customer" <?php echo $type_filter == 'customer' ? 'selected' : ''; ?>>Client</option>
            <option value="vendor" <?php echo $type_filter == 'vendor' ? 'selected' : ''; ?>>Fournisseur</option>
        </select>
        <button type="submit" class="btn btn-small">Filtrer</button>
        <a href="/modules/setup/accounts.php" class="btn btn-small">Reset</a>
    </form>
</div>

<!-- Accounts table -->
<table class="data-table">
    <thead>
        <tr>
            <th>Code</th>
            <th>Libellé</th>
            <th>Type</th>
            <th>Actif</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($accounts as $account): ?>
        <tr>
            <td><?php echo h($account['code']); ?></td>
            <td><?php echo h($account['label']); ?></td>
            <td><?php echo h($account['type']); ?></td>
            <td><?php echo $account['is_active'] ? 'Oui' : 'Non'; ?></td>
            <td class="actions">
                <a href="?edit=<?php echo $account['id']; ?>" class="btn btn-small">Modifier</a>
                <form method="post" action="" style="display: inline;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo $account['id']; ?>">
                    <button type="submit" class="btn btn-small btn-danger confirm-action"
                            data-confirm="Supprimer ce compte ?">Supprimer</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php echo pagination_links($pagination, '?search=' . urlencode($search) . '&type=' . urlencode($type_filter)); ?>

<p>Total: <?php echo $total; ?> comptes</p>

<?php require_once __DIR__ . '/../../footer.php'; ?>
