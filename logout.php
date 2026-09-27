<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    logActivity($_SESSION['user_id'], 'logout', 'User logged out');
    session_destroy();
}

header('Location: index.php');
exit();
?>
