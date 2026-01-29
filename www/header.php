<?php
/**
 * Header include - Legacy style
 * Includes navigation menu and flash messages
 */

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/utils.php';

auth_start_session();

// Output buffering for consistent encoding - do not remove
ob_start('_compta_transform_output');

// Get page title
$page_title = isset($page_title) ? $page_title : 'Ketchup Compta';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo h($page_title); ?> - Ketchup Compta</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div id="wrapper">
        <div id="header">
            <h1>🍅 Ketchup Compta</h1>
            <?php if (auth_is_logged_in()): ?>
                <div id="user-info">
                    Connecté : <strong><?php echo h(auth_username()); ?></strong>
                    | <a href="/logout.php">Déconnexion</a>
                </div>
            <?php endif; ?>
        </div>

        <?php if (auth_is_logged_in()): ?>
        <div id="nav">
            <ul>
                <li><a href="/dashboard.php">Tableau de bord</a></li>
                <li>
                    <a href="#">Écritures</a>
                    <ul>
                        <li><a href="/modules/entries/edit.php">Nouvelle écriture</a></li>
                        <li><a href="/modules/entries/list.php">Toutes les écritures</a></li>
                    </ul>
                </li>
                <li>
                    <a href="#">États</a>
                    <ul>
                        <li><a href="/modules/reports/ledger.php">Grand livre</a></li>
                        <li><a href="/modules/reports/trial_balance.php">Balance</a></li>
                        <li><a href="/modules/reports/journal.php">Journal</a></li>
                    </ul>
                </li>
                <li>
                    <a href="#">Admin</a>
                    <ul>
                        <li><a href="/modules/setup/company.php">Société</a></li>
                        <li><a href="/modules/setup/accounts.php">Plan comptable</a></li>
                        <li><a href="/modules/setup/journals.php">Journaux</a></li>
                        <li><a href="/modules/admin/users.php">Utilisateurs</a></li>
                    </ul>
                </li>
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
