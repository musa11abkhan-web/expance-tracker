<?php
/**
 * Logout Handler
 * Project: Expense Tracker (MUSA / 3465340082)
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

// Log out user completely (clears session and cookie)
logoutUser();

// Start fresh session for flash message
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION['login_message'] = 'You have been successfully logged out.';
header('Location: login.php');
exit;
