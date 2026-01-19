<?php
/**
 * Lettering selection page - Legacy style
 *
 * Shows accounts that can be lettered (411xxx, 401xxx) with:
 * - Unlettered balance
 * - Number of unlettered lines
 * - Recent letterings
 */

require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/utils.php';

require_login();
require_role('accountant');

$filter_type = get('type', ''); // 'customer', 'vendor', or ''
$show_all = get('show_all', '0') === '1';

// Build filter condition for account codes
$account_filter = "(a.code LIKE '411%' OR a.code LIKE '401%')";
if ($filter_type === 'customer') {
    $account_filter = "a.code LIKE '411%'";
} elseif ($filter_type === 'vendor') {
    $account_filter = "a.code LIKE '401%'";
}

// Get accounts (customers/vendors) with their lettering status
// This query calculates:
// - Total unlettered amount per account
// - Number of unlettered lines
// - Last letter code used
$sql = "SELECT
            a.id,
            a.code,
            a.label,
            CASE WHEN a.code LIKE '411%' THEN 'customer' ELSE 'vendor' END as type,
            COUNT(DISTINCT CASE
                WHEN (el.debit + el.credit) - COALESCE(
                    (SELECT SUM(ABS(amount)) FROM lettering_items WHERE entry_line_id = el.id), 0
                ) > 0.001 THEN el.id
            END) as unlettered_count,
            SUM(CASE
                WHEN el.debit > 0 AND (el.debit + el.credit) - COALESCE(
                    (SELECT SUM(ABS(amount)) FROM lettering_items WHERE entry_line_id = el.id), 0
                ) > 0.001
                THEN (el.debit + el.credit) - COALESCE(
                    (SELECT SUM(ABS(amount)) FROM lettering_items WHERE entry_line_id = el.id), 0
                )
                ELSE 0
            END) as unlettered_debit,
            SUM(CASE
                WHEN el.credit > 0 AND (el.debit + el.credit) - COALESCE(
                    (SELECT SUM(ABS(amount)) FROM lettering_items WHERE entry_line_id = el.id), 0
                ) > 0.001
                THEN (el.debit + el.credit) - COALESCE(
                    (SELECT SUM(ABS(amount)) FROM lettering_items WHERE entry_line_id = el.id), 0
                )
                ELSE 0
            END) as unlettered_credit,
            (SELECT letter_code FROM lettering_groups WHERE account_id = a.id ORDER BY id DESC LIMIT 1) as last_letter_code,
            (SELECT COUNT(*) FROM lettering_groups WHERE account_id = a.id) as lettering_count
        FROM accounts a
        LEFT JOIN entry_lines el ON el.account_id = a.id
        LEFT JOIN entries e ON el.entry_id = e.id AND e.status = 'posted'
        WHERE $account_filter
          AND a.is_active = 1
        GROUP BY a.id, a.code, a.label";

if (!$show_all) {
    $sql .= " HAVING unlettered_count > 0";
}

$sql .= " ORDER BY a.code";

$accounts = db_fetch_all(db_query($sql));

// Calculate totals
$total_unlettered_debit = 0;
$total_unlettered_credit = 0;
$total_unlettered_lines = 0;
$customer_count = 0;
$vendor_count = 0;

foreach ($accounts as $acc) {
    $total_unlettered_debit += floatval($acc['unlettered_debit']);
    $total_unlettered_credit += floatval($acc['unlettered_credit']);
    $total_unlettered_lines += intval($acc['unlettered_count']);
    if ($acc['type'] === 'customer') {
        $customer_count++;
    } else {
        $vendor_count++;
    }
}

// Get third parties with unlettered amounts (grouped by third party)
$sql = "SELECT
            tp.id,
            tp.name,
            tp.type,
            a.id as account_id,
            a.code as account_code,
            COUNT(DISTINCT CASE
                WHEN (el.debit + el.credit) - COALESCE(
                    (SELECT SUM(ABS(amount)) FROM lettering_items WHERE entry_line_id = el.id), 0
                ) > 0.001 THEN el.id
            END) as unlettered_count,
            SUM(CASE
                WHEN el.debit > 0 AND (el.debit + el.credit) - COALESCE(
                    (SELECT SUM(ABS(amount)) FROM lettering_items WHERE entry_line_id = el.id), 0
                ) > 0.001
                THEN (el.debit + el.credit) - COALESCE(
                    (SELECT SUM(ABS(amount)) FROM lettering_items WHERE entry_line_id = el.id), 0
                )
                ELSE 0
            END) as unlettered_debit,
            SUM(CASE
                WHEN el.credit > 0 AND (el.debit + el.credit) - COALESCE(
                    (SELECT SUM(ABS(amount)) FROM lettering_items WHERE entry_line_id = el.id), 0
                ) > 0.001
                THEN (el.debit + el.credit) - COALESCE(
                    (SELECT SUM(ABS(amount)) FROM lettering_items WHERE entry_line_id = el.id), 0
                )
                ELSE 0
            END) as unlettered_credit
        FROM third_parties tp
        INNER JOIN accounts a ON tp.account_id = a.id
        LEFT JOIN entry_lines el ON el.third_party_id = tp.id AND el.account_id = a.id
        LEFT JOIN entries e ON el.entry_id = e.id AND e.status = 'posted'
        WHERE $account_filter
        GROUP BY tp.id, tp.name, tp.type, a.id, a.code
        HAVING unlettered_count > 0
        ORDER BY tp.type, tp.name";

$third_parties = db_fetch_all(db_query($sql));

$page_title = 'Lettrage - Selection';
require_once __DIR__ . '/../../header.php';
?>

<h2>Lettrage</h2>

<div class="dashboard-row">
    <div class="stat-box">
        <h3>Comptes clients</h3>
        <p class="big-number"><?php echo $customer_count; ?></p>
        <p>a lettrer</p>
    </div>
    <div class="stat-box">
        <h3>Comptes fournisseurs</h3>
        <p class="big-number"><?php echo $vendor_count; ?></p>
        <p>a lettrer</p>
    </div>
    <div class="stat-box">
        <h3>Lignes non lettrees</h3>
        <p class="big-number"><?php echo $total_unlettered_lines; ?></p>
        <p>total</p>
    </div>
    <div class="stat-box">
        <h3>Solde non lettre</h3>
        <p class="big-number" style="font-size: 18px;">
            D: <?php echo format_money($total_unlettered_debit); ?><br>
            C: <?php echo format_money($total_unlettered_credit); ?>
        </p>
    </div>
</div>

<div class="mb-20">
    <a href="/modules/letters/history.php" class="btn">Historique des lettrages</a>
</div>

<!-- Filters -->
<div class="filters">
    <form method="get" action="">
        <label>Type:</label>
        <select name="type" onchange="this.form.submit()">
            <option value="">Tous</option>
            <option value="customer" <?php echo $filter_type === 'customer' ? 'selected' : ''; ?>>Clients (411)</option>
            <option value="vendor" <?php echo $filter_type === 'vendor' ? 'selected' : ''; ?>>Fournisseurs (401)</option>
        </select>

        <label>
            <input type="checkbox" name="show_all" value="1" <?php echo $show_all ? 'checked' : ''; ?>
                   onchange="this.form.submit()">
            Afficher les comptes soldes
        </label>
    </form>
</div>

<h3>Selection par compte (<?php echo count($accounts); ?>)</h3>

<?php if (count($accounts) > 0): ?>
<table class="data-table filterable-table">
    <thead>
        <tr>
            <th>Type</th>
            <th>Compte</th>
            <th>Libelle</th>
            <th>Lignes</th>
            <th>Debit non lettre</th>
            <th>Credit non lettre</th>
            <th>Solde</th>
            <th>Dernier code</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($accounts as $acc): ?>
        <?php
            $balance = floatval($acc['unlettered_debit']) - floatval($acc['unlettered_credit']);
            $balance_style = $balance >= 0 ? 'color: green;' : 'color: red;';
        ?>
        <tr>
            <td>
                <?php if ($acc['type'] === 'customer'): ?>
                <span style="color: #0066cc;">Client</span>
                <?php else: ?>
                <span style="color: #cc6600;">Fournisseur</span>
                <?php endif; ?>
            </td>
            <td><strong><?php echo h($acc['code']); ?></strong></td>
            <td><?php echo h($acc['label']); ?></td>
            <td><?php echo $acc['unlettered_count']; ?></td>
            <td class="number"><?php echo format_money($acc['unlettered_debit']); ?></td>
            <td class="number"><?php echo format_money($acc['unlettered_credit']); ?></td>
            <td class="number" style="<?php echo $balance_style; ?> font-weight: bold;">
                <?php echo format_money($balance); ?>
            </td>
            <td>
                <?php if ($acc['last_letter_code']): ?>
                <span title="<?php echo $acc['lettering_count']; ?> lettrage(s)">
                    <?php echo h($acc['last_letter_code']); ?>
                </span>
                <?php else: ?>
                <em>-</em>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($acc['unlettered_count'] > 0): ?>
                <a href="/modules/letters/letter.php?account_id=<?php echo $acc['id']; ?>" class="btn btn-small btn-primary">
                    Lettrer
                </a>
                <?php else: ?>
                <a href="/modules/letters/letter.php?account_id=<?php echo $acc['id']; ?>" class="btn btn-small">
                    Voir
                </a>
                <?php endif; ?>
                <?php if ($acc['lettering_count'] > 0): ?>
                <a href="/modules/letters/history.php?account_id=<?php echo $acc['id']; ?>" class="btn btn-small">
                    Historique
                </a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<p>Aucun compte a lettrer.</p>
<?php endif; ?>

<?php if (count($third_parties) > 0): ?>
<h3 class="mt-20">Selection par tiers (<?php echo count($third_parties); ?>)</h3>

<table class="data-table">
    <thead>
        <tr>
            <th>Type</th>
            <th>Tiers</th>
            <th>Compte</th>
            <th>Lignes</th>
            <th>Debit</th>
            <th>Credit</th>
            <th>Solde</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($third_parties as $tp): ?>
        <?php
            $balance = floatval($tp['unlettered_debit']) - floatval($tp['unlettered_credit']);
            $balance_style = $balance >= 0 ? 'color: green;' : 'color: red;';
        ?>
        <tr>
            <td>
                <?php if ($tp['type'] === 'customer'): ?>
                <span style="color: #0066cc;">Client</span>
                <?php else: ?>
                <span style="color: #cc6600;">Fournisseur</span>
                <?php endif; ?>
            </td>
            <td><strong><?php echo h($tp['name']); ?></strong></td>
            <td><?php echo h($tp['account_code']); ?></td>
            <td><?php echo $tp['unlettered_count']; ?></td>
            <td class="number"><?php echo format_money($tp['unlettered_debit']); ?></td>
            <td class="number"><?php echo format_money($tp['unlettered_credit']); ?></td>
            <td class="number" style="<?php echo $balance_style; ?> font-weight: bold;">
                <?php echo format_money($balance); ?>
            </td>
            <td>
                <a href="/modules/letters/letter.php?third_party_id=<?php echo $tp['id']; ?>" class="btn btn-small btn-primary">
                    Lettrer
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<div class="mt-20">
    <p>
        <strong>Note:</strong> Le lettrage permet de rapprocher les factures (debit) avec leurs reglements (credit).
        Seuls les comptes clients (411xxx) et fournisseurs (401xxx) peuvent etre lettres.
    </p>
</div>

<?php require_once __DIR__ . '/../../footer.php'; ?>
