<?php
/**
 * Logout page - Legacy style
 */

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/utils.php';

auth_logout();
set_flash('success', 'Vous avez ete deconnecte.');
redirect('/login.php');
