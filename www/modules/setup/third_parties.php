<?php
/**
 * Third parties management (customers/vendors) - Legacy style
 */

require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/utils.php';

require_login();
require_role('accountant');

// Handle delete
if (is_post() && post('action') === 'delete') {
    csrf_verify();
    $id = intval(post('id'));

    // Check if third party is used
    $sql = "SELECT COUNT(*) as count FROM entry_lines WHERE third_party_id = $id";
    $result = db_query($sql);
    if (db_fetch_assoc($result)['count'] > 0) {
        set_flash('error', 'Impossible de supprimer: ce tiers est utilise dans des ecritures.');
    } else {
        db_query("DELETE FROM third_parties WHERE id = $id");
        audit_log('DELETE', 'third_parties', $id, 'Third party deleted');
        set_flash('success', 'Tiers supprime.');
    }
    redirect('/modules/setup/third_parties.php');
}

// Handle create/update
if (is_post() && (post('action') === 'create' || post('action') === 'update')) {
    csrf_verify();

    $id = intval(post('id'));
    $type = db_escape(post('type'));
    $name = db_escape(trim(post('name')));
    $email = db_escape(trim(post('email')));
    $account_id = intval(post('account_id'));
    $auto_create_account = post('auto_create_account') ? true : false;

    // Validation
    $errors = array();
    if (empty($name)) $errors[] = 'Le nom est obligatoire.';
    if (!in_array($type, array('customer', 'vendor'))) $errors[] = 'Type invalide.';

    // Auto-create account if requested
    if ($auto_create_account && $account_id == 0) {
        // Get next account number
        $prefix = $type === 'customer' ? '411' : '401';
        $sql = "SELECT MAX(CAST(SUBSTRING(code, 4) AS UNSIGNED)) as max_num FROM accounts WHERE code LIKE '$prefix%'";
        $result = db_query($sql);
        $row = db_fetch_assoc($result);
        $next_num = ($row['max_num'] ? $row['max_num'] : 0) + 1;
        $new_code = $prefix . str_pad($next_num, 3, '0', STR_PAD_LEFT);

        // Create account
        $account_label = db_escape($name);
        $account_type = $type;
        $sql = "INSERT INTO accounts (code, label, type, is_active) VALUES ('$new_code', '$account_label', '$account_type', 1)";
        db_query($sql);
        $account_id = db_insert_id();
        audit_log('CREATE', 'accounts', $account_id, "Auto-created account $new_code for third party");
    }

    if (empty($errors)) {
        if (post('action') === 'create') {
            $sql = "INSERT INTO third_parties (type, name, email, account_id, created_at)
                    VALUES ('$type', '$name', '$email', " . ($account_id ? $account_id : 'NULL') . ", NOW())";
            db_query($sql);
            $id = db_insert_id();
            audit_log('CREATE', 'third_parties', $id, "Third party $name created");
            set_flash('success', 'Tiers cree.');
        } else {
            $sql = "UPDATE third_parties SET type = '$type', name = '$name', email = '$email',
                    account_id = " . ($account_id ? $account_id : 'NULL') . " WHERE id = $id";
            db_query($sql);
            audit_log('UPDATE', 'third_parties', $id, "Third party $name updated");
            set_flash('success', 'Tiers mis a jour.');
        }
        redirect('/modules/setup/third_parties.php');
    } else {
        set_flash('error', implode(' ', $errors));
    }
}

// Filters
$type_filter = get('type', '');
$search = get('search', '');

// Build query
$where = "1=1";
if ($type_filter) {
    $type_esc = db_escape($type_filter);
    $where .= " AND tp.type = '$type_esc'";
}
if ($search) {
    $search_esc = db_escape($search);
    $where .= " AND (tp.name LIKE '%$search_esc%' OR tp.email LIKE '%$search_esc%')";
}

// Pagination
$sql = "SELECT COUNT(*) as count FROM third_parties tp WHERE $where";
$total = db_fetch_assoc(db_query($sql))['count'];
$page = max(1, intval(get('page', 1)));
$pagination = paginate($total, $page, 30);

// Get third parties
$sql = "SELECT tp.*, a.code as account_code, a.label as account_label
        FROM third_parties tp
        LEFT JOIN accounts a ON tp.account_id = a.id
        WHERE $where
        ORDER BY tp.name
        LIMIT {$pagination['offset']}, {$pagination['per_page']}";
$third_parties = db_fetch_all(db_query($sql));

// Get accounts for dropdown
$customer_accounts = get_accounts(true, 'customer');
$vendor_accounts = get_accounts(true, 'vendor');

// Edit mode
$edit_tp = null;
if (get('edit')) {
    $edit_id = intval(get('edit'));
    $sql = "SELECT * FROM third_parties WHERE id = $edit_id";
    $result = db_query($sql);
    if (db_num_rows($result) > 0) {
        $edit_tp = db_fetch_assoc($result);
    }
}

$page_title = 'Gestion des Tiers';
require_once __DIR__ . '/../../header.php';
?>

<h2>Gestion des Tiers</h2>

<!-- Add/Edit Form -->
<div class="mb-20" style="background: #f9f9f9; padding: 15px; border: 1px solid #ccc;">
    <h3><?php echo $edit_tp ? 'Modifier le tiers' : 'Nouveau tiers'; ?></h3>
    <form method="post" action="">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="<?php echo $edit_tp ? 'update' : 'create'; ?>">
        <input type="hidden" name="id" value="<?php echo $edit_tp ? $edit_tp['id'] : 0; ?>">

        <div class="form-row">
            <div class="form-group">
                <label for="type">Type *</label>
                <select id="type" name="type" required>
                    <option value="customer" <?php echo ($edit_tp && $edit_tp['type'] == 'customer') ? 'selected' : ''; ?>>Client</option>
                    <option value="vendor" <?php echo ($edit_tp && $edit_tp['type'] == 'vendor') ? 'selected' : ''; ?>>Fournisseur</option>
                </select>
            </div>
            <div class="form-group">
                <label for="name">Nom *</label>
                <input type="text" id="name" name="name" style="width: 250px;"
                       value="<?php echo h($edit_tp ? $edit_tp['name'] : ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email"
                       value="<?php echo h($edit_tp ? $edit_tp['email'] : ''); ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="account_id">Compte associe</label>
                <select id="account_id" name="account_id">
                    <option value="">-- Aucun --</option>
                    <optgroup label="Comptes clients (411)">
                        <?php foreach ($customer_accounts as $acc): ?>
                        <option value="<?php echo $acc['id']; ?>"
                            <?php echo ($edit_tp && $edit_tp['account_id'] == $acc['id']) ? 'selected' : ''; ?>>
                            <?php echo h($acc['code'] . ' - ' . $acc['label']); ?>
                        </option>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="Comptes fournisseurs (401)">
                        <?php foreach ($vendor_accounts as $acc): ?>
                        <option value="<?php echo $acc['id']; ?>"
                            <?php echo ($edit_tp && $edit_tp['account_id'] == $acc['id']) ? 'selected' : ''; ?>>
                            <?php echo h($acc['code'] . ' - ' . $acc['label']); ?>
                        </option>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
            </div>
            <?php if (!$edit_tp): ?>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="auto_create_account" value="1">
                    Creer automatiquement le compte
                </label>
                <small>(411xxx pour client, 401xxx pour fournisseur)</small>
            </div>
            <?php endif; ?>
        </div>

        <button type="submit" class="btn btn-primary">
            <?php echo $edit_tp ? 'Mettre a jour' : 'Creer'; ?>
        </button>
        <?php if ($edit_tp): ?>
        <a href="/modules/setup/third_parties.php" class="btn">Annuler</a>
        <?php endif; ?>
    </form>
</div>

<!-- Filters -->
<div class="filters">
    <form method="get" action="">
        <label>Type:</label>
        <select name="type">
            <option value="">Tous</option>
            <option value="customer" <?php echo $type_filter == 'customer' ? 'selected' : ''; ?>>Clients</option>
            <option value="vendor" <?php echo $type_filter == 'vendor' ? 'selected' : ''; ?>>Fournisseurs</option>
        </select>
        <label>Recherche:</label>
        <input type="text" name="search" value="<?php echo h($search); ?>" placeholder="Nom ou email">
        <button type="submit" class="btn btn-small">Filtrer</button>
        <a href="/modules/setup/third_parties.php" class="btn btn-small">Reset</a>
    </form>
</div>

<!-- Third parties table -->
<table class="data-table">
    <thead>
        <tr>
            <th>Type</th>
            <th>Nom</th>
            <th>Email</th>
            <th>Compte</th>
            <th>Cree le</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($third_parties as $tp): ?>
        <tr>
            <td><?php echo $tp['type'] === 'customer' ? 'Client' : 'Fournisseur'; ?></td>
            <td><strong><?php echo h($tp['name']); ?></strong></td>
            <td><?php echo h($tp['email']); ?></td>
            <td>
                <?php if ($tp['account_code']): ?>
                <?php echo h($tp['account_code'] . ' - ' . $tp['account_label']); ?>
                <?php else: ?>
                <em>Non associe</em>
                <?php endif; ?>
            </td>
            <td><?php echo format_date($tp['created_at']); ?></td>
            <td class="actions">
                <a href="?edit=<?php echo $tp['id']; ?>" class="btn btn-small">Modifier</a>
                <form method="post" action="" style="display: inline;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo $tp['id']; ?>">
                    <button type="submit" class="btn btn-small btn-danger confirm-action"
                            data-confirm="Supprimer ce tiers ?">Supprimer</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php echo pagination_links($pagination, '?type=' . urlencode($type_filter) . '&search=' . urlencode($search)); ?>

<p>Total: <?php echo $total; ?> tiers</p>

<?php require_once __DIR__ . '/../../footer.php'; ?>
