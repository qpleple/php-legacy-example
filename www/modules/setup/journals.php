<?php
/**
 * Journals management - Legacy style
 */

require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/utils.php';

require_login();

// Handle delete
if (is_post() && post('action') === 'delete') {
    csrf_verify();
    $id = intval(post('id'));

    // Check if journal is used
    $sql = "SELECT COUNT(*) as count FROM entries WHERE journal_id = $id";
    $result = db_query($sql);
    if (db_fetch_assoc($result)['count'] > 0) {
        set_flash('error', 'Impossible de supprimer : ce journal contient des écritures.');
    } else {
        db_query("DELETE FROM journals WHERE id = $id");
        audit_log('DELETE', 'journals', $id, 'Journal deleted');
        set_flash('success', 'Journal supprimé.');
    }
    redirect('/modules/setup/journals.php');
}

// Handle create/update
if (is_post() && (post('action') === 'create' || post('action') === 'update')) {
    csrf_verify();

    $id = intval(post('id'));
    $code = db_escape(strtoupper(trim(post('code'))));
    $label = db_escape(trim(post('label')));
    $sequence_prefix = db_escape(strtoupper(trim(post('sequence_prefix'))));
    $next_number = intval(post('next_number'));
    $is_active = post('is_active') ? 1 : 0;

    if ($next_number < 1) $next_number = 1;

    // Validation
    $errors = array();
    if (empty($code)) $errors[] = 'Le code est obligatoire.';
    if (empty($label)) $errors[] = 'Le libellé est obligatoire.';
    if (empty($sequence_prefix)) $errors[] = 'Le préfixe est obligatoire.';

    // Check unique code
    $sql = "SELECT id FROM journals WHERE code = '$code' AND id != $id";
    $result = db_query($sql);
    if (db_num_rows($result) > 0) {
        $errors[] = 'Ce code de journal existe déjà.';
    }

    if (empty($errors)) {
        if (post('action') === 'create') {
            $sql = "INSERT INTO journals (code, label, sequence_prefix, next_number, is_active)
                    VALUES ('$code', '$label', '$sequence_prefix', $next_number, $is_active)";
            db_query($sql);
            $id = db_insert_id();
            audit_log('CREATE', 'journals', $id, "Journal $code created");
            set_flash('success', 'Journal créé.');
        } else {
            $sql = "UPDATE journals SET code = '$code', label = '$label', sequence_prefix = '$sequence_prefix',
                    next_number = $next_number, is_active = $is_active WHERE id = $id";
            db_query($sql);
            audit_log('UPDATE', 'journals', $id, "Journal $code updated");
            set_flash('success', 'Journal mis à jour.');
        }
        redirect('/modules/setup/journals.php');
    } else {
        set_flash('error', implode(' ', $errors));
    }
}

// Get all journals
$sql = "SELECT j.*, (SELECT COUNT(*) FROM entries e WHERE e.journal_id = j.id) as entry_count
        FROM journals j ORDER BY code";
$journals = db_fetch_all(db_query($sql));

// Edit mode
$edit_journal = null;
if (get('edit')) {
    $edit_id = intval(get('edit'));
    $sql = "SELECT * FROM journals WHERE id = $edit_id";
    $result = db_query($sql);
    if (db_num_rows($result) > 0) {
        $edit_journal = db_fetch_assoc($result);
    }
}

$page_title = 'Journaux';
require_once __DIR__ . '/../../header.php';
?>

<h2>Gestion des Journaux</h2>

<!-- Add/Edit Form -->
<div class="mb-20" style="background: #f9f9f9; padding: 15px; border: 1px solid #ccc;">
    <h3><?php echo $edit_journal ? 'Modifier le journal' : 'Nouveau journal'; ?></h3>
    <form method="post" action="">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="<?php echo $edit_journal ? 'update' : 'create'; ?>">
        <input type="hidden" name="id" value="<?php echo $edit_journal ? $edit_journal['id'] : 0; ?>">

        <div class="form-row">
            <div class="form-group">
                <label for="code">Code *</label>
                <input type="text" id="code" name="code" maxlength="10" style="width: 100px;"
                       value="<?php echo h($edit_journal ? $edit_journal['code'] : ''); ?>" required>
                <small>(ex: VE, AC, BK, OD)</small>
            </div>
            <div class="form-group">
                <label for="label">Libellé *</label>
                <input type="text" id="label" name="label" style="width: 250px;"
                       value="<?php echo h($edit_journal ? $edit_journal['label'] : ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="sequence_prefix">Préfixe séquence *</label>
                <input type="text" id="sequence_prefix" name="sequence_prefix" maxlength="10" style="width: 100px;"
                       value="<?php echo h($edit_journal ? $edit_journal['sequence_prefix'] : ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="next_number">Prochain numéro</label>
                <input type="number" id="next_number" name="next_number" min="1" style="width: 100px;"
                       value="<?php echo $edit_journal ? $edit_journal['next_number'] : 1; ?>">
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_active" value="1"
                           <?php echo (!$edit_journal || $edit_journal['is_active']) ? 'checked' : ''; ?>>
                    Actif
                </label>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">
            <?php echo $edit_journal ? 'Mettre à jour' : 'Créer'; ?>
        </button>
        <?php if ($edit_journal): ?>
        <a href="/modules/setup/journals.php" class="btn">Annuler</a>
        <?php endif; ?>
    </form>
</div>

<!-- Journals table -->
<table class="data-table">
    <thead>
        <tr>
            <th>Code</th>
            <th>Libellé</th>
            <th>Préfixe</th>
            <th>Prochain N°</th>
            <th>Nb écritures</th>
            <th>Actif</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($journals as $journal): ?>
        <tr>
            <td><strong><?php echo h($journal['code']); ?></strong></td>
            <td><?php echo h($journal['label']); ?></td>
            <td><?php echo h($journal['sequence_prefix']); ?></td>
            <td><?php echo $journal['next_number']; ?></td>
            <td><?php echo $journal['entry_count']; ?></td>
            <td><?php echo $journal['is_active'] ? 'Oui' : 'Non'; ?></td>
            <td class="actions">
                <a href="?edit=<?php echo $journal['id']; ?>" class="btn btn-small">Modifier</a>
                <?php if ($journal['entry_count'] == 0): ?>
                <form method="post" action="" style="display: inline;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo $journal['id']; ?>">
                    <button type="submit" class="btn btn-small btn-danger confirm-action"
                            data-confirm="Supprimer ce journal ?">Supprimer</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="mt-20">
    <p><strong>Note :</strong> Le format des numéros de pièce est : PRÉFIXE + ANNÉE + '-' + NUMÉRO (ex : VE2024-000001)</p>
</div>

<?php require_once __DIR__ . '/../../footer.php'; ?>
