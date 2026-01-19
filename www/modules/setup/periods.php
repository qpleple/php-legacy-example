<?php
/**
 * Periods management page - Legacy style
 */

$page_title = 'Gestion des Periodes';
require_once __DIR__ . '/../../header.php';
require_role('admin');

$company = get_company();

// Handle generate periods
if (is_post() && post('action') === 'generate') {
    require_csrf();

    if (!$company) {
        set_flash('error', 'Configurez d\'abord la societe.');
        redirect('/modules/setup/periods.php');
    }

    // Delete existing periods (only if no entries reference them)
    $sql = "SELECT COUNT(*) as count FROM entries WHERE period_id IS NOT NULL";
    $result = db_query($sql);
    $has_entries = db_fetch_assoc($result)['count'] > 0;

    if ($has_entries && !post('force')) {
        set_flash('warning', 'Des ecritures existent dans les periodes actuelles. Cochez "Forcer" pour regenerer.');
    } else {
        // Delete existing periods
        db_query("DELETE FROM periods");

        // Generate monthly periods
        $start = new DateTime($company['fiscal_year_start']);
        $end = new DateTime($company['fiscal_year_end']);

        while ($start <= $end) {
            $period_start = $start->format('Y-m-d');
            $period_end = $start->format('Y-m-t'); // Last day of month

            // Don't go past fiscal year end
            if (strtotime($period_end) > strtotime($company['fiscal_year_end'])) {
                $period_end = $company['fiscal_year_end'];
            }

            $sql = "INSERT INTO periods (start_date, end_date, status) VALUES ('$period_start', '$period_end', 'open')";
            db_query($sql);

            $start->modify('first day of next month');
        }

        audit_log('GENERATE', 'periods', 0, 'Periods regenerated for fiscal year');
        set_flash('success', 'Periodes generees avec succes.');
    }

    redirect('/modules/setup/periods.php');
}

// Handle lock/unlock period
if (is_post() && (post('action') === 'lock' || post('action') === 'unlock')) {
    require_csrf();

    $period_id = intval(post('period_id'));
    $new_status = post('action') === 'lock' ? 'locked' : 'open';

    // Check for draft entries in this period if locking
    if ($new_status === 'locked') {
        $sql = "SELECT COUNT(*) as count FROM entries WHERE period_id = $period_id AND status = 'draft'";
        $result = db_query($sql);
        if (db_fetch_assoc($result)['count'] > 0) {
            set_flash('error', 'Impossible de verrouiller: des ecritures en brouillon existent dans cette periode.');
            redirect('/modules/setup/periods.php');
        }
    }

    $sql = "UPDATE periods SET status = '$new_status' WHERE id = $period_id";
    db_query($sql);

    audit_log('UPDATE', 'periods', $period_id, "Period $new_status");
    set_flash('success', 'Statut de la periode mis a jour.');
    redirect('/modules/setup/periods.php');
}

// Get all periods
$periods = get_periods();
?>

<h2>Gestion des Periodes</h2>

<?php if ($company): ?>
<div class="mb-20">
    <p><strong>Exercice:</strong>
        <?php echo format_date($company['fiscal_year_start']); ?> -
        <?php echo format_date($company['fiscal_year_end']); ?>
    </p>
</div>

<form method="post" action="" class="mb-20">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="generate">

    <button type="submit" class="btn" onclick="return confirm('Regenerer toutes les periodes mensuelles ?');">
        Generer periodes mensuelles
    </button>

    <label style="margin-left: 20px;">
        <input type="checkbox" name="force" value="1">
        Forcer (meme si des ecritures existent)
    </label>
</form>
<?php else: ?>
<div class="flash flash-warning">
    Veuillez d'abord <a href="/modules/setup/company.php">configurer la societe</a>.
</div>
<?php endif; ?>

<h3>Liste des periodes</h3>

<?php if (count($periods) > 0): ?>
<table class="data-table">
    <thead>
        <tr>
            <th>Debut</th>
            <th>Fin</th>
            <th>Statut</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($periods as $period): ?>
        <tr>
            <td><?php echo format_date($period['start_date']); ?></td>
            <td><?php echo format_date($period['end_date']); ?></td>
            <td>
                <span class="status-<?php echo $period['status']; ?>">
                    <?php echo $period['status'] === 'open' ? 'Ouverte' : 'Verrouillee'; ?>
                </span>
            </td>
            <td>
                <form method="post" action="" style="display: inline;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="period_id" value="<?php echo $period['id']; ?>">
                    <?php if ($period['status'] === 'open'): ?>
                    <input type="hidden" name="action" value="lock">
                    <button type="submit" class="btn btn-small confirm-action" data-confirm="Verrouiller cette periode ?">
                        Verrouiller
                    </button>
                    <?php else: ?>
                    <input type="hidden" name="action" value="unlock">
                    <button type="submit" class="btn btn-small confirm-action" data-confirm="Deverrouiller cette periode ?">
                        Deverrouiller
                    </button>
                    <?php endif; ?>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<p>Aucune periode definie. Cliquez sur "Generer periodes mensuelles" pour creer les periodes de l'exercice.</p>
<?php endif; ?>

<?php require_once __DIR__ . '/../../footer.php'; ?>
