<?php
/**
 * Landing page - Public page with newsletter subscription
 */

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/utils.php';

auth_start_session();

// If logged in, redirect to dashboard
if (auth_is_logged_in()) {
    redirect('/dashboard.php');
}

$success = '';
$error = '';

// Handle newsletter subscription
if (is_post()) {
    $email = trim(post('email'));

    // Validate email
    if (empty($email)) {
        $error = 'Veuillez entrer une adresse email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse email invalide.';
    } else {
        // Check if already subscribed
        $email_escaped = db_escape($email);
        $sql = "SELECT id FROM subscribers WHERE email = '$email_escaped'";
        $result = db_query($sql);

        if (db_num_rows($result) > 0) {
            $error = 'Cette adresse email est déjà inscrite.';
        } else {
            // Insert subscriber
            $now = date('Y-m-d H:i:s');
            $sql = "INSERT INTO subscribers (email, created_at) VALUES ('$email_escaped', '$now')";
            db_query($sql);
            $success = 'Merci pour votre inscription !';
        }
    }
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
            <h1>Ketchup Compta</h1>
            <p class="hint" style="margin-top: 0; margin-bottom: 20px;">Logiciel de comptabilité en partie double</p>

            <div class="form-group" style="text-align: center;">
                <a href="/login.php" class="btn btn-primary" style="display: block; padding: 10px;">Se connecter</a>
            </div>

            <hr style="margin: 25px 0; border: none; border-top: 1px solid #ccc;">

            <h3 style="text-align: center; margin-bottom: 15px;">Restez informé</h3>
            <p class="hint" style="margin-top: 0; margin-bottom: 15px;">Inscrivez-vous à notre newsletter</p>

            <?php if ($success): ?>
            <div class="flash flash-success"><?php echo h($success); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="flash flash-error"><?php echo h($error); ?></div>
            <?php endif; ?>

            <form method="post" action="">
                <div class="form-group">
                    <label for="email">Adresse email :</label>
                    <input type="email" id="email" name="email" value="<?php echo h(post('email')); ?>" required>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-success" style="width: 100%;">S'inscrire</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
