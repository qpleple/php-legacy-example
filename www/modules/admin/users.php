<?php
/**
 * User management page - Legacy style
 */

require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/utils.php';

require_login();
require_role('admin');

$current_user_id = auth_user_id();

// Handle delete
if (is_post() && post('action') === 'delete') {
    csrf_verify();
    $id = intval(post('id'));

    if ($id === $current_user_id) {
        set_flash('error', 'Vous ne pouvez pas supprimer votre propre compte.');
    } else {
        // Check if user has entries
        $sql = "SELECT COUNT(*) as count FROM entries WHERE created_by = $id";
        $result = db_query($sql);
        if (db_fetch_assoc($result)['count'] > 0) {
            set_flash('error', 'Impossible de supprimer: cet utilisateur a cree des ecritures.');
        } else {
            db_query("DELETE FROM users WHERE id = $id");
            audit_log('DELETE', 'users', $id, 'User deleted');
            set_flash('success', 'Utilisateur supprime.');
        }
    }
    redirect('/modules/admin/users.php');
}

// Handle create/update
if (is_post() && (post('action') === 'create' || post('action') === 'update')) {
    csrf_verify();

    $id = intval(post('id'));
    $username = db_escape(trim(post('username')));
    $role = db_escape(post('role'));
    $password = post('password');

    // Validation
    $errors = array();
    if (empty($username)) $errors[] = 'Le nom d\'utilisateur est obligatoire.';
    if (!in_array($role, array('admin', 'accountant', 'viewer'))) $errors[] = 'Role invalide.';

    // Check unique username
    $sql = "SELECT id FROM users WHERE username = '$username' AND id != $id";
    $result = db_query($sql);
    if (db_num_rows($result) > 0) {
        $errors[] = 'Ce nom d\'utilisateur existe deja.';
    }

    // Password required for new users
    if (post('action') === 'create' && empty($password)) {
        $errors[] = 'Le mot de passe est obligatoire pour un nouvel utilisateur.';
    }

    if (empty($errors)) {
        if (post('action') === 'create') {
            $password_hash = auth_hash_password($password);
            $sql = "INSERT INTO users (username, password_hash, role, created_at)
                    VALUES ('$username', '$password_hash', '$role', NOW())";
            db_query($sql);
            $id = db_insert_id();
            audit_log('CREATE', 'users', $id, "User $username created");
            set_flash('success', 'Utilisateur cree.');
        } else {
            // Update
            $sql = "UPDATE users SET username = '$username', role = '$role'";

            // Update password if provided
            if (!empty($password)) {
                $password_hash = auth_hash_password($password);
                $sql .= ", password_hash = '$password_hash'";
            }

            $sql .= " WHERE id = $id";
            db_query($sql);
            audit_log('UPDATE', 'users', $id, "User $username updated");
            set_flash('success', 'Utilisateur mis a jour.');
        }
        redirect('/modules/admin/users.php');
    } else {
        set_flash('error', implode(' ', $errors));
    }
}

// Get all users
$sql = "SELECT u.*,
               (SELECT COUNT(*) FROM entries e WHERE e.created_by = u.id) as entry_count,
               (SELECT MAX(al.created_at) FROM audit_log al WHERE al.user_id = u.id AND al.action = 'LOGIN') as last_login
        FROM users u
        ORDER BY u.username";
$users = db_fetch_all(db_query($sql));

// Edit mode
$edit_user = null;
if (get('edit')) {
    $edit_id = intval(get('edit'));
    $sql = "SELECT * FROM users WHERE id = $edit_id";
    $result = db_query($sql);
    if (db_num_rows($result) > 0) {
        $edit_user = db_fetch_assoc($result);
    }
}

$page_title = 'Gestion Utilisateurs';
require_once __DIR__ . '/../../header.php';
?>

<h2>Gestion des Utilisateurs</h2>

<!-- Add/Edit Form -->
<div class="mb-20" style="background: #f9f9f9; padding: 15px; border: 1px solid #ccc;">
    <h3><?php echo $edit_user ? 'Modifier l\'utilisateur' : 'Nouvel utilisateur'; ?></h3>
    <form method="post" action="">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="<?php echo $edit_user ? 'update' : 'create'; ?>">
        <input type="hidden" name="id" value="<?php echo $edit_user ? $edit_user['id'] : 0; ?>">

        <div class="form-row">
            <div class="form-group">
                <label for="username">Nom d'utilisateur *</label>
                <input type="text" id="username" name="username"
                       value="<?php echo h($edit_user ? $edit_user['username'] : ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Mot de passe <?php echo $edit_user ? '(laisser vide pour conserver)' : '*'; ?></label>
                <input type="password" id="password" name="password" <?php echo $edit_user ? '' : 'required'; ?>>
            </div>
            <div class="form-group">
                <label for="role">Role *</label>
                <select id="role" name="role" required>
                    <option value="viewer" <?php echo ($edit_user && $edit_user['role'] == 'viewer') ? 'selected' : ''; ?>>
                        Lecture seule
                    </option>
                    <option value="accountant" <?php echo ($edit_user && $edit_user['role'] == 'accountant') ? 'selected' : (!$edit_user ? 'selected' : ''); ?>>
                        Comptable
                    </option>
                    <option value="admin" <?php echo ($edit_user && $edit_user['role'] == 'admin') ? 'selected' : ''; ?>>
                        Administrateur
                    </option>
                </select>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">
            <?php echo $edit_user ? 'Mettre a jour' : 'Creer'; ?>
        </button>
        <?php if ($edit_user): ?>
        <a href="/modules/admin/users.php" class="btn">Annuler</a>
        <?php endif; ?>
    </form>
</div>

<!-- Users table -->
<table class="data-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Nom d'utilisateur</th>
            <th>Role</th>
            <th>Cree le</th>
            <th>Derniere connexion</th>
            <th>Nb ecritures</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($users as $user): ?>
        <tr>
            <td><?php echo $user['id']; ?></td>
            <td>
                <strong><?php echo h($user['username']); ?></strong>
                <?php if ($user['id'] == $current_user_id): ?>
                <span style="color: green;">(vous)</span>
                <?php endif; ?>
            </td>
            <td>
                <?php
                $roles = array(
                    'admin' => 'Administrateur',
                    'accountant' => 'Comptable',
                    'viewer' => 'Lecture seule'
                );
                echo isset($roles[$user['role']]) ? $roles[$user['role']] : $user['role'];
                ?>
            </td>
            <td><?php echo format_datetime($user['created_at']); ?></td>
            <td><?php echo $user['last_login'] ? format_datetime($user['last_login']) : 'Jamais'; ?></td>
            <td><?php echo $user['entry_count']; ?></td>
            <td class="actions">
                <a href="?edit=<?php echo $user['id']; ?>" class="btn btn-small">Modifier</a>
                <?php if ($user['id'] != $current_user_id && $user['entry_count'] == 0): ?>
                <form method="post" action="" style="display: inline;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                    <button type="submit" class="btn btn-small btn-danger confirm-action"
                            data-confirm="Supprimer cet utilisateur ?">Supprimer</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="mt-20">
    <h3>Roles et permissions</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>Role</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Administrateur</strong></td>
                <td>Acces complet: parametrage, utilisateurs, cloture, toutes les operations comptables</td>
            </tr>
            <tr>
                <td><strong>Comptable</strong></td>
                <td>Saisie, validation, lettrage, pointage, consultation des etats</td>
            </tr>
            <tr>
                <td><strong>Lecture seule</strong></td>
                <td>Consultation uniquement des etats et ecritures validees</td>
            </tr>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../../footer.php'; ?>
