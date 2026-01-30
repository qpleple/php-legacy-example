<?php
/**
 * Landing page - Public welcome page
 */

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/utils.php';

auth_start_session();

// If logged in, redirect to dashboard
if (auth_is_logged_in()) {
    redirect('/dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ketchup Compta - Logiciel de comptabilité</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div id="login-wrapper">
        <div id="login-box" style="max-width: 480px;">
            <div style="text-align: center; margin-bottom: 25px;">
                <span style="font-size: 48px;">🍅</span>
                <h1 style="margin-top: 10px;">Ketchup Compta</h1>
                <p class="hint" style="margin-top: 5px;">Logiciel de comptabilité en partie double</p>
            </div>

            <div class="form-group" style="text-align: center;">
                <a href="/login.php" class="btn btn-primary" style="display: block; padding: 12px; font-size: 14px;">Se connecter</a>
            </div>

            <div style="margin: 30px 0; border-top: 1px solid #e5e5e5;"></div>

            <h3 style="color: #003366; margin-bottom: 15px; text-align: center;">Fonctionnalités</h3>

            <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px;">
                <div style="flex: 1; min-width: 200px; padding: 12px; background: #f9f9f9; border: 1px solid #e5e5e5;">
                    <strong style="color: #003366;">Plan comptable</strong><br>
                    <span style="color: #666; font-size: 11px;">Plan comptable général français</span>
                </div>
                <div style="flex: 1; min-width: 200px; padding: 12px; background: #f9f9f9; border: 1px solid #e5e5e5;">
                    <strong style="color: #003366;">Journaux</strong><br>
                    <span style="color: #666; font-size: 11px;">Achats, ventes, banque, OD</span>
                </div>
                <div style="flex: 1; min-width: 200px; padding: 12px; background: #f9f9f9; border: 1px solid #e5e5e5;">
                    <strong style="color: #003366;">Écritures</strong><br>
                    <span style="color: #666; font-size: 11px;">Saisie en partie double</span>
                </div>
                <div style="flex: 1; min-width: 200px; padding: 12px; background: #f9f9f9; border: 1px solid #e5e5e5;">
                    <strong style="color: #003366;">États comptables</strong><br>
                    <span style="color: #666; font-size: 11px;">Grand livre, balance, TVA</span>
                </div>
            </div>

            <p class="hint" style="font-size: 11px; text-align: center; color: #999;">
                Version 1.0
            </p>
        </div>
    </div>
</body>
</html>
