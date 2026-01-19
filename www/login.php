<?php
/**
 * Login page - Legacy style
 */

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/utils.php';

auth_start_session();

// Already logged in?
if (auth_is_logged_in()) {
    redirect('/index.php');
}

$error = '';

// Handle login
if (is_post()) {
    $username = trim(post('username'));
    $password = post('password');

    if (empty($username) || empty($password)) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        if (auth_login($username, $password)) {
            set_flash('success', 'Connexion reussie. Bienvenue!');
            redirect('/index.php');
        } else {
            $error = 'Identifiants incorrects.';
        }
    }
}

$page_title = 'Connexion';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion - Comptabilite</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div id="login-wrapper">
        <div id="login-box">
            <h1>Connexion</h1>

            <?php if ($error): ?>
            <div class="flash flash-error"><?php echo h($error); ?></div>
            <?php endif; ?>

            <?php
            $flash = get_flash();
            if ($flash):
            ?>
            <div class="flash flash-<?php echo h($flash['type']); ?>">
                <?php echo h($flash['msg']); ?>
            </div>
            <?php endif; ?>

            <form method="post" action="">
                <div class="form-group">
                    <label for="username">Nom d'utilisateur:</label>
                    <input type="text" id="username" name="username" value="<?php echo h(post('username')); ?>" required>
                </div>
                <div class="form-group">
                    <label for="password">Mot de passe:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Se connecter</button>
                </div>
            </form>

            <p class="hint">
                <small>Utilisateur par defaut: admin / admin123</small>
            </p>
        </div>
    </div>
</body>
</html>
