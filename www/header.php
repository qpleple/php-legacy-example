<?php
/**
 * Header include - Legacy style
 * Includes navigation menu and flash messages
 */

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/utils.php';

auth_start_session();

// Get page title
$page_title = isset($page_title) ? $page_title : 'Comptabilite';
$company = get_company();
$company_name = $company ? $company['name'] : 'Comptabilite';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo h($page_title); ?> - <?php echo h($company_name); ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div id="wrapper">
        <div id="header">
            <h1><?php echo h($company_name); ?></h1>
            <?php if (auth_is_logged_in()): ?>
                <div id="user-info">
                    Connecte: <strong><?php echo h(auth_username()); ?></strong>
                    (<?php echo h(auth_role()); ?>)
                    | <a href="/logout.php">Deconnexion</a>
                </div>
            <?php endif; ?>
        </div>

        <?php if (auth_is_logged_in()): ?>
        <div id="nav">
            <ul>
                <li><a href="/index.php">Tableau de bord</a></li>
                <li>
                    <a href="#">Parametrage</a>
                    <ul>
                        <li><a href="/modules/setup/company.php">Societe</a></li>
                        <li><a href="/modules/setup/periods.php">Periodes</a></li>
                        <li><a href="/modules/setup/accounts.php">Plan comptable</a></li>
                        <li><a href="/modules/setup/journals.php">Journaux</a></li>
                        <li><a href="/modules/setup/third_parties.php">Tiers</a></li>
                        <li><a href="/modules/setup/vat.php">TVA</a></li>
                    </ul>
                </li>
                <li>
                    <a href="#">Ecritures</a>
                    <ul>
                        <li><a href="/modules/entries/list.php">Liste des pieces</a></li>
                        <li><a href="/modules/entries/edit.php">Nouvelle piece</a></li>
                        <li><a href="/modules/entries/import.php">Import CSV</a></li>
                    </ul>
                </li>
                <li>
                    <a href="#">Banque</a>
                    <ul>
                        <li><a href="/modules/bank/accounts.php">Comptes bancaires</a></li>
                        <li><a href="/modules/bank/import.php">Import releve</a></li>
                        <li><a href="/modules/bank/reconcile.php">Rapprochement</a></li>
                    </ul>
                </li>
                <li><a href="/modules/letters/select.php">Lettrage</a></li>
                <li>
                    <a href="#">Etats</a>
                    <ul>
                        <li><a href="/modules/reports/ledger.php">Grand livre</a></li>
                        <li><a href="/modules/reports/trial_balance.php">Balance</a></li>
                        <li><a href="/modules/reports/journal.php">Journal</a></li>
                        <li><a href="/modules/reports/vat_summary.php">Synthese TVA</a></li>
                    </ul>
                </li>
                <li>
                    <a href="#">Cloture</a>
                    <ul>
                        <li><a href="/modules/close/lock_period.php">Verrouillage periodes</a></li>
                        <li><a href="/modules/close/year_end.php">Cloture annuelle</a></li>
                    </ul>
                </li>
                <?php if (auth_has_role('admin')): ?>
                <li><a href="/modules/admin/users.php">Admin</a></li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php
        // Display flash message
        $flash = get_flash();
        if ($flash):
        ?>
        <div class="flash flash-<?php echo h($flash['type']); ?>">
            <?php echo h($flash['msg']); ?>
        </div>
        <?php endif; ?>

        <div id="content">
