<?php
/**
 * Entries list page - Legacy style (Simplified, no draft)
 */

require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/utils.php';

require_login();

// Filters
$journal_id = get('journal_id', '');
$search = get('search', '');

$where = "1=1";
if ($journal_id) {
    $journal_id = intval($journal_id);
    $where .= " AND e.journal_id = $journal_id";
}
if ($search) {
    $search_esc = db_escape($search);
    $where .= " AND (e.label LIKE '%$search_esc%' OR e.piece_number LIKE '%$search_esc%')";
}

$sql = "SELECT COUNT(*) as count FROM entries e WHERE $where";
$total = db_fetch_assoc(db_query($sql))['count'];
$page = max(1, intval(get('page', 1)));
$pagination = paginate($total, $page, 30);

$sql = "SELECT e.*, j.code as journal_code, j.label as journal_label, u.username as created_by_name
        FROM entries e
        LEFT JOIN journals j ON e.journal_id = j.id
        LEFT JOIN users u ON e.created_by = u.id
        WHERE $where
        ORDER BY e.entry_date DESC, e.id DESC
        LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}";
$entries = db_fetch_all(db_query($sql));

$journals = get_journals();

$page_title = 'Toutes les Écritures';
require_once __DIR__ . '/../../header.php';
?>

<h2>Toutes les Écritures</h2>

<div class="mb-10">
    <a href="/modules/entries/edit.php" class="btn btn-primary">Nouvelle écriture</a>
</div>

<div class="filters">
    <form method="get" action="">
        <label>Journal :</label>
        <select name="journal_id">
            <option value="">Tous</option>
            <?php foreach ($journals as $j): ?>
            <option value="<?php echo $j['id']; ?>" <?php echo get('journal_id') == $j['id'] ? 'selected' : ''; ?>>
                <?php echo h($j['code'] . ' - ' . $j['label']); ?>
            </option>
            <?php endforeach; ?>
        </select>

        <label>Recherche :</label>
        <input type="text" name="search" value="<?php echo h(get('search')); ?>" placeholder="Libellé ou numéro">

        <button type="submit" class="btn btn-small">Filtrer</button>
        <a href="/modules/entries/list.php" class="btn btn-small">Reset</a>
    </form>
</div>

<?php if (count($entries) > 0): ?>
<table class="data-table">
    <thead>
        <tr>
            <th>Date</th>
            <th>Journal</th>
            <th>N° Pièce</th>
            <th>Libellé</th>
            <th>Débit</th>
            <th>Crédit</th>
            <th>Créé par</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($entries as $entry): ?>
        <tr>
            <td><?php echo format_date($entry['entry_date']); ?></td>
            <td><?php echo h($entry['journal_code']); ?></td>
            <td>
                <a href="/modules/entries/edit.php?id=<?php echo $entry['id']; ?>">
                    <?php echo h($entry['piece_number']); ?>
                </a>
            </td>
            <td><?php echo h($entry['label']); ?></td>
            <td class="number"><?php echo format_money($entry['total_debit']); ?></td>
            <td class="number"><?php echo format_money($entry['total_credit']); ?></td>
            <td><?php echo h($entry['created_by_name']); ?></td>
            <td class="actions">
                <a href="/modules/entries/edit.php?id=<?php echo $entry['id']; ?>" class="btn btn-small">Voir</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php
$filter_params = 'journal_id=' . urlencode(get('journal_id')) .
    '&search=' . urlencode(get('search'));
echo pagination_links($pagination, '?' . $filter_params);
?>

<?php else: ?>
<p>Aucune écriture trouvée.</p>
<?php endif; ?>

<p>Total : <?php echo $total; ?> écritures</p>

<?php require_once __DIR__ . '/../../footer.php'; ?>
