<?php
/**
 * Landing page - Public welcome page
 */

require_once __DIR__ . '/lib/db.php';
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
        <div id="login-box">
            <h1>🍅 Ketchup Compta</h1>
            <p class="hint" style="margin-top: 0; margin-bottom: 20px;">Logiciel de comptabilité en partie double</p>

            <div class="form-group" style="text-align: center;">
                <a href="/login.php" class="btn btn-primary" style="display: block; padding: 10px;">Se connecter</a>
            </div>
        </div>
    </div>
</body>
</html>
