<?php
/**
 * Project: Expense Tracker
 * Page: Categories (Feature Removed)
 * Safely redirects to All Expenses
 */
require_once __DIR__ . '/config/db.php';
header('Location: expenses.php');
exit;
