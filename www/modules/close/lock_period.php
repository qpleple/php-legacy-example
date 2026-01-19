<?php
/**
 * Period locking page - Legacy style
 */

require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/utils.php';

require_login();
require_role('admin');

// Handle lock/unlock
if (is_post()) {
    csrf_verify();

    $period_id = intval(post('period_id'));
    $action = post('action');

    if ($action === 'lock') {
        // Check for draft entries
        $sql = "SELECT COUNT(*) as count FROM entries WHERE period_id = $period_id AND status = 'draft'";
        $result = db_query($sql);
        $draft_count = db_fetch_assoc($result)['count'];

        if ($draft_count > 0) {
            set_flash('error', "Impossible de verrouiller: $draft_count ecriture(s) en brouillon dans cette periode.");
        } else {
            $sql = "UPDATE periods SET status = 'locked' WHERE id = $period_id";
            db_query($sql);
            audit_log('LOCK', 'periods', $period_id, 'Period locked');
            set_flash('success', 'Periode verrouillee.');
        }
    } elseif ($action === 'unlock') {
        $sql = "UPDATE periods SET status = 'open' WHERE id = $period_id";
        db_query($sql);
        audit_log('UNLOCK', 'periods', $period_id, 'Period unlocked');
        set_flash('success', 'Periode deverrouillee.');
    }

    redirect('/modules/close/lock_period.php');
}

// Get periods with statistics
$sql = "SELECT p.*,
               (SELECT COUNT(*) FROM entries e WHERE e.period_id = p.id AND e.status = 'draft') as draft_count,
               (SELECT COUNT(*) FROM entries e WHERE e.period_id = p.id AND e.status = 'posted') as posted_count,
               (SELECT SUM(e.total_debit) FROM entries e WHERE e.period_id = p.id AND e.status = 'posted') as total_debit
        FROM periods p
        ORDER BY p.start_date";
$periods = db_fetch_all(db_query($sql));

// Count summary
$open_count = 0;
$locked_count = 0;
foreach ($periods as $p) {
    if ($p['status'] === 'open') $open_count++;
    else $locked_count++;
}

$page_title = 'Verrouillage des Periodes';
require_once __DIR__ . '/../../header.php';
?>

<h2>Verrouillage des Periodes</h2>

<div class="dashboard-row mb-20">
    <div class="stat-box">
        <h3>Periodes ouvertes</h3>
        <p class="big-number" style="color: green;"><?php echo $open_count; ?></p>
    </div>
    <div class="stat-box">
        <h3>Periodes verroullees</h3>
        <p class="big-number" style="color: red;"><?php echo $locked_count; ?></p>
    </div>
    <div class="stat-box">
        <h3>Total periodes</h3>
        <p class="big-number"><?php echo count($periods); ?></p>
    </div>
</div>

<table class="data-table">
    <thead>
        <tr>
            <th>Periode</th>
            <th>Debut</th>
            <th>Fin</th>
            <th>Statut</th>
            <th>Brouillons</th>
            <th>Validees</th>
            <th>Volume</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($periods as $period): ?>
        <tr>
            <td>
                <?php
                $start = new DateTime($period['start_date']);
                echo $start->format('F Y');
                ?>
            </td>
            <td><?php echo format_date($period['start_date']); ?></td>
            <td><?php echo format_date($period['end_date']); ?></td>
            <td>
                <span class="status-<?php echo $period['status'] === 'open' ? 'posted' : 'draft'; ?>"
                      style="<?php echo $period['status'] === 'locked' ? 'background: #f8d7da; color: #721c24;' : ''; ?>">
                    <?php echo $period['status'] === 'open' ? 'Ouverte' : 'Verrouillee'; ?>
                </span>
            </td>
            <td>
                <?php if ($period['draft_count'] > 0): ?>
                <span style="color: orange; font-weight: bold;"><?php echo $period['draft_count']; ?></span>
                <?php else: ?>
                0
                <?php endif; ?>
            </td>
            <td><?php echo $period['posted_count']; ?></td>
            <td class="number"><?php echo format_money($period['total_debit'] ?: 0); ?></td>
            <td>
                <form method="post" action="" style="display: inline;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="period_id" value="<?php echo $period['id']; ?>">
                    <?php if ($period['status'] === 'open'): ?>
                    <input type="hidden" name="action" value="lock">
                    <button type="submit" class="btn btn-small btn-danger confirm-action"
                            data-confirm="Verrouiller cette periode ? Les saisies seront bloquees."
                            <?php echo $period['draft_count'] > 0 ? 'disabled title="Brouillons existants"' : ''; ?>>
                        Verrouiller
                    </button>
                    <?php else: ?>
                    <input type="hidden" name="action" value="unlock">
                    <button type="submit" class="btn btn-small btn-success confirm-action"
                            data-confirm="Deverrouiller cette periode ?">
                        Deverrouiller
                    </button>
                    <?php endif; ?>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="mt-20">
    <h3>Regles de verrouillage</h3>
    <ul>
        <li>Une periode ne peut etre verrouillee que si elle ne contient aucune ecriture en brouillon.</li>
        <li>Une fois verrouillee, aucune nouvelle ecriture ne peut etre creee ou validee dans cette periode.</li>
        <li>Seul un administrateur peut verrouiller ou deverrouiller une periode.</li>
        <li>Toutes les periodes doivent etre verroullees avant la cloture annuelle.</li>
    </ul>
</div>

<div class="mt-20">
    <a href="/modules/close/year_end.php" class="btn btn-primary">Passer a la cloture annuelle</a>
</div>

<?php require_once __DIR__ . '/../../footer.php'; ?>
