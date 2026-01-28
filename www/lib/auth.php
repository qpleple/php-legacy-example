<?php
/**
 * Authentication functions - Legacy style (2006)
 * MD5 hashing with salt, session-based auth
 */

// Password salt (legacy style - would be in config in production)
define('PASSWORD_SALT', 'legacy');

/**
 * Start session if not started
 */
function auth_start_session() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Hash password with MD5 and salt (legacy style - intentionally weak)
 */
function auth_hash_password($password) {
    return md5(PASSWORD_SALT . $password);
}

/**
 * Verify password
 */
function auth_verify_password($password, $hash) {
    return auth_hash_password($password) === $hash;
}

/**
 * Attempt login
 */
function auth_login($username, $password) {
    $username = db_escape($username);

    $sql = "SELECT id, username, password_hash FROM users WHERE username = '$username'";
    $result = db_query($sql);

    if (db_num_rows($result) === 0) {
        return false;
    }

    $user = db_fetch_assoc($result);

    if (!auth_verify_password($password, $user['password_hash'])) {
        return false;
    }

    // Set session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));

    // Log login
    audit_log('LOGIN', 'user', $user['id'], 'User logged in');

    return true;
}

/**
 * Logout
 */
function auth_logout() {
    auth_start_session();

    if (isset($_SESSION['user_id'])) {
        audit_log('LOGOUT', 'user', $_SESSION['user_id'], 'User logged out');
    }

    session_destroy();
    $_SESSION = array();
}

/**
 * Check if user is logged in
 */
function auth_is_logged_in() {
    auth_start_session();
    return isset($_SESSION['user_id']);
}

/**
 * Get current user ID
 */
function auth_user_id() {
    auth_start_session();
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}

/**
 * Get current username
 */
function auth_username() {
    auth_start_session();
    return isset($_SESSION['username']) ? $_SESSION['username'] : null;
}

/**
 * Require login - redirect if not logged in
 */
function require_login() {
    if (!auth_is_logged_in()) {
        set_flash('error', 'Veuillez vous connecter.');
        header('Location: /login.php');
        exit;
    }
}

/**
 * Get CSRF token
 */
function csrf_token() {
    auth_start_session();

    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Generate CSRF hidden field
 */
function csrf_field() {
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token()) . '">';
}

/**
 * Verify CSRF token
 */
function csrf_verify() {
    if (!isset($_POST['_csrf']) || !isset($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $_POST['_csrf']);
}

/**
 * Require valid CSRF token
 */
function require_csrf() {
    if (!csrf_verify()) {
        set_flash('error', 'Token de sécurité invalide. Veuillez réessayer.');
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
}

/**
 * Write to audit log
 */
function audit_log($action, $entity, $entity_id, $details = '') {
    $user_id = auth_user_id();
    if ($user_id === null) {
        $user_id = 0;
    }

    $action = db_escape($action);
    $entity = db_escape($entity);
    $entity_id = intval($entity_id);
    $details = db_escape($details);

    $sql = "INSERT INTO audit_log (user_id, action, entity, entity_id, details, created_at)
            VALUES ($user_id, '$action', '$entity', $entity_id, '$details', NOW())";
    db_query($sql);
}
