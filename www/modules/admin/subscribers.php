<?php
/**
 * Newsletter subscribers management page
 */

require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/utils.php';

require_login();

// Handle delete
if (is_post() && post('action') === 'delete') {
    csrf_verify();
    $id = intval(post('id'));

    db_query("DELETE FROM subscribers WHERE id = $id");
    set_flash('success', 'Abonné supprimé.');
    redirect('/modules/admin/subscribers.php');
}

// Get all subscribers
$sql = "SELECT * FROM subscribers ORDER BY created_at DESC";
$subscribers = db_fetch_all(db_query($sql));

$page_title = 'Abonnés Newsletter';
require_once __DIR__ . '/../../header.php';
?>

<h2>Abonnés Newsletter</h2>

<p>Total : <strong><?php echo count($subscribers); ?></strong> abonné(s)</p>

<?php if (count($subscribers) > 0): ?>
<table class="data-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Email</th>
            <th>Date d'inscription</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($subscribers as $subscriber): ?>
        <tr>
            <td><?php echo $subscriber['id']; ?></td>
            <td><?php echo h($subscriber['email']); ?></td>
            <td><?php echo format_datetime($subscriber['created_at']); ?></td>
            <td class="actions">
                <form method="post" action="" style="display: inline;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo $subscriber['id']; ?>">
                    <button type="submit" class="btn btn-small btn-danger confirm-action"
                            data-confirm="Supprimer cet abonné ?">Supprimer</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<p>Aucun abonné pour le moment.</p>
<?php endif; ?>

<?php require_once __DIR__ . '/../../footer.php'; ?>
