<?php
/**
 * Lettering selection page - Legacy style
 */

$page_title = 'Lettrage - Selection';
require_once __DIR__ . '/../../header.php';
require_role('accountant');

// Get third parties with unlettered amounts
$sql = "SELECT tp.id, tp.name, tp.type, a.code as account_code, a.id as account_id,
               SUM(CASE WHEN el.debit > 0 THEN el.debit ELSE 0 END) as total_debit,
               SUM(CASE WHEN el.credit > 0 THEN el.credit ELSE 0 END) as total_credit,
               COUNT(el.id) as line_count
        FROM third_parties tp
        LEFT JOIN accounts a ON tp.account_id = a.id
        LEFT JOIN entry_lines el ON el.account_id = a.id
        LEFT JOIN entries e ON el.entry_id = e.id AND e.status = 'posted'
        WHERE el.id IS NOT NULL
          AND el.id NOT IN (SELECT entry_line_id FROM lettering_items)
        GROUP BY tp.id, tp.name, tp.type, a.code, a.id
        HAVING line_count > 0
        ORDER BY tp.type, tp.name";
$third_parties_with_balance = db_fetch_all(db_query($sql));

// Get accounts (customers/vendors) with unlettered items
$sql = "SELECT a.id, a.code, a.label, a.type,
               SUM(CASE WHEN el.debit > 0 THEN el.debit ELSE 0 END) as total_debit,
               SUM(CASE WHEN el.credit > 0 THEN el.credit ELSE 0 END) as total_credit,
               COUNT(el.id) as line_count
        FROM accounts a
        INNER JOIN entry_lines el ON el.account_id = a.id
        INNER JOIN entries e ON el.entry_id = e.id AND e.status = 'posted'
        WHERE a.type IN ('customer', 'vendor')
          AND el.id NOT IN (SELECT entry_line_id FROM lettering_items)
        GROUP BY a.id, a.code, a.label, a.type
        HAVING line_count > 0
        ORDER BY a.type, a.code";
$accounts_with_balance = db_fetch_all(db_query($sql));
?>

<h2>Lettrage - Selection du compte</h2>

<div class="dashboard-row">
    <div class="stat-box">
        <h3>Tiers a lettrer</h3>
        <p class="big-number"><?php echo count($third_parties_with_balance); ?></p>
    </div>
    <div class="stat-box">
        <h3>Comptes a lettrer</h3>
        <p class="big-number"><?php echo count($accounts_with_balance); ?></p>
    </div>
</div>

<h3>Selectionnez par tiers</h3>

<?php if (count($third_parties_with_balance) > 0): ?>
<table class="data-table">
    <thead>
        <tr>
            <th>Type</th>
            <th>Tiers</th>
            <th>Compte</th>
            <th>Total Debit</th>
            <th>Total Credit</th>
            <th>Solde</th>
            <th>Lignes</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($third_parties_with_balance as $tp): ?>
        <?php $balance = $tp['total_debit'] - $tp['total_credit']; ?>
        <tr>
            <td><?php echo $tp['type'] === 'customer' ? 'Client' : 'Fournisseur'; ?></td>
            <td><strong><?php echo h($tp['name']); ?></strong></td>
            <td><?php echo h($tp['account_code']); ?></td>
            <td class="number"><?php echo format_money($tp['total_debit']); ?></td>
            <td class="number"><?php echo format_money($tp['total_credit']); ?></td>
            <td class="number" style="<?php echo $balance >= 0 ? 'color: green;' : 'color: red;'; ?>">
                <?php echo format_money($balance); ?>
            </td>
            <td><?php echo $tp['line_count']; ?></td>
            <td>
                <a href="/modules/letters/letter.php?third_party_id=<?php echo $tp['id']; ?>" class="btn btn-small btn-primary">
                    Lettrer
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<p>Aucun tiers avec des lignes a lettrer.</p>
<?php endif; ?>

<h3>Ou selectionnez par compte</h3>

<?php if (count($accounts_with_balance) > 0): ?>
<table class="data-table">
    <thead>
        <tr>
            <th>Type</th>
            <th>Compte</th>
            <th>Libelle</th>
            <th>Total Debit</th>
            <th>Total Credit</th>
            <th>Solde</th>
            <th>Lignes</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($accounts_with_balance as $acc): ?>
        <?php $balance = $acc['total_debit'] - $acc['total_credit']; ?>
        <tr>
            <td><?php echo $acc['type'] === 'customer' ? 'Client' : 'Fournisseur'; ?></td>
            <td><strong><?php echo h($acc['code']); ?></strong></td>
            <td><?php echo h($acc['label']); ?></td>
            <td class="number"><?php echo format_money($acc['total_debit']); ?></td>
            <td class="number"><?php echo format_money($acc['total_credit']); ?></td>
            <td class="number" style="<?php echo $balance >= 0 ? 'color: green;' : 'color: red;'; ?>">
                <?php echo format_money($balance); ?>
            </td>
            <td><?php echo $acc['line_count']; ?></td>
            <td>
                <a href="/modules/letters/letter.php?account_id=<?php echo $acc['id']; ?>" class="btn btn-small btn-primary">
                    Lettrer
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<p>Aucun compte avec des lignes a lettrer.</p>
<?php endif; ?>

<?php require_once __DIR__ . '/../../footer.php'; ?>
