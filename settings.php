<?php
/**
 * Project: Expense Tracker
 * Page: Settings (Step 6 & Step 9)
 * Database: tracker
 * Currency: PKR (Pakistani Rupees)
 */

require_once __DIR__ . '/config/db.php';
requireLogin();

$pageTitle = 'Settings';
$pageHeader = 'Application Settings';
$activePage = 'settings';

// Ensure settings table exists
if (function_exists('ensureSettingsTable')) {
    ensureSettingsTable($pdo);
}

// -----------------------------------------------------------------------------
// Step 9 Helper Functions: Backup & Restore Logic
// -----------------------------------------------------------------------------
if (!function_exists('generateDatabaseBackupSql')) {
    /**
     * Generates a complete SQL dump of the tracker database (structure and data)
     */
    function generateDatabaseBackupSql(PDO $pdo, string $databaseName = 'tracker'): string {
        $timestamp = date('Y-m-d H:i:s');
        $sql = "-- ============================================================\n";
        $sql .= "-- Expense Tracker Database Backup\n";
        $sql .= "-- Database: {$databaseName}\n";
        $sql .= "-- Generated: {$timestamp}\n";
        $sql .= "-- Currency Standard: PKR (Pakistani Rupees)\n";
        $sql .= "-- Local XAMPP MySQL Environment\n";
        $sql .= "-- ============================================================\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n";
        $sql .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
        $sql .= "SET time_zone = \"+05:00\";\n";
        $sql .= "SET NAMES utf8mb4;\n\n";

        // Query all base tables in the tracker database
        $tablesStmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
        $tables = $tablesStmt ? $tablesStmt->fetchAll(PDO::FETCH_COLUMN) : [];

        foreach ($tables as $table) {
            $sql .= "-- ------------------------------------------------------------\n";
            $sql .= "-- Table structure for `{$table}`\n";
            $sql .= "-- ------------------------------------------------------------\n";
            $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";

            $createStmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
            $createRow = $createStmt ? $createStmt->fetch(PDO::FETCH_NUM) : null;
            if ($createRow && isset($createRow[1])) {
                $sql .= $createRow[1] . ";\n\n";
            }

            $sql .= "-- ------------------------------------------------------------\n";
            $sql .= "-- Data records for `{$table}`\n";
            $sql .= "-- ------------------------------------------------------------\n";

            $rowsStmt = $pdo->query("SELECT * FROM `{$table}`");
            $rows = $rowsStmt ? $rowsStmt->fetchAll(PDO::FETCH_ASSOC) : [];

            if (!empty($rows)) {
                $cols = array_keys($rows[0]);
                $quotedCols = array_map(function($c) { 
                    return "`" . str_replace("`", "``", $c) . "`"; 
                }, $cols);
                $colsStr = implode(", ", $quotedCols);

                // Chunk insert statements
                $chunks = array_chunk($rows, 50);
                foreach ($chunks as $chunk) {
                    $valuesArr = [];
                    foreach ($chunk as $row) {
                        $rowVals = [];
                        foreach ($cols as $col) {
                            $val = $row[$col];
                            if ($val === null) {
                                $rowVals[] = "NULL";
                            } else {
                                $rowVals[] = $pdo->quote($val);
                            }
                        }
                        $valuesArr[] = "(" . implode(", ", $rowVals) . ")";
                    }
                    $sql .= "INSERT INTO `{$table}` ({$colsStr}) VALUES\n" . implode(",\n", $valuesArr) . ";\n";
                }
                $sql .= "\n";
            } else {
                $sql .= "-- (No records in `{$table}`)\n\n";
            }
        }

        $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
        $sql .= "-- ========================== END OF BACKUP ==========================\n";

        return $sql;
    }
}

if (!function_exists('splitSqlStatements')) {
    /**
     * Splits multi-statement SQL script into single executable statements
     */
    function splitSqlStatements(string $sql): array {
        $statements = [];
        $current = '';
        $inSingleQuote = false;
        $inDoubleQuote = false;
        $inBacktick = false;
        $inCommentLine = false;
        $inCommentBlock = false;
        $len = strlen($sql);

        for ($i = 0; $i < $len; $i++) {
            $char = $sql[$i];
            $next = ($i + 1 < $len) ? $sql[$i + 1] : '';

            // Handle line comments (-- or #)
            if ($inCommentLine) {
                if ($char === "\n" || $char === "\r") {
                    $inCommentLine = false;
                }
                continue;
            }

            // Handle block comments (/* ... */)
            if ($inCommentBlock) {
                if ($char === '*' && $next === '/') {
                    $inCommentBlock = false;
                    $i++;
                }
                continue;
            }

            // Start comment detection
            if (!$inSingleQuote && !$inDoubleQuote && !$inBacktick) {
                if (($char === '-' && $next === '-') || $char === '#') {
                    $inCommentLine = true;
                    if ($char === '-') {
                        $i++;
                    }
                    continue;
                }
                if ($char === '/' && $next === '*') {
                    $inCommentBlock = true;
                    $i++;
                    continue;
                }
            }

            // Quotes tracking
            if ($char === "'" && !$inDoubleQuote && !$inBacktick) {
                $escaped = ($i > 0 && $sql[$i - 1] === '\\');
                if (!$escaped) {
                    $inSingleQuote = !$inSingleQuote;
                }
            } elseif ($char === '"' && !$inSingleQuote && !$inBacktick) {
                $escaped = ($i > 0 && $sql[$i - 1] === '\\');
                if (!$escaped) {
                    $inDoubleQuote = !$inDoubleQuote;
                }
            } elseif ($char === '`' && !$inSingleQuote && !$inDoubleQuote) {
                $inBacktick = !$inBacktick;
            }

            // Semicolon statement boundary
            if ($char === ';' && !$inSingleQuote && !$inDoubleQuote && !$inBacktick) {
                $trimmed = trim($current);
                if ($trimmed !== '') {
                    $statements[] = $trimmed;
                }
                $current = '';
                continue;
            }

            $current .= $char;
        }

        $trimmed = trim($current);
        if ($trimmed !== '') {
            $statements[] = $trimmed;
        }

        return $statements;
    }
}

// -----------------------------------------------------------------------------
// Step 9 Request Handler: Backup Database Download (Requirement 2 & 5)
// -----------------------------------------------------------------------------
if ((isset($_GET['action']) && $_GET['action'] === 'backup') || (isset($_POST['action']) && $_POST['action'] === 'backup')) {
    try {
        // Filename format: tracker_backup_YYYY-MM-DD_HH-MM-SS.sql
        $backupFilename = 'tracker_backup_' . date('Y-m-d_H-i-s') . '.sql';
        $backupSql = generateDatabaseBackupSql($pdo, 'tracker');

        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $backupFilename . '"');
        header('Content-Length: ' . strlen($backupSql));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $backupSql;
        exit;

    } catch (Throwable $e) {
        error_log("Database Backup Failed: " . $e->getMessage());
        $_SESSION['error_message'] = 'Database backup failed: ' . $e->getMessage();
        header('Location: settings.php');
        exit;
    }
}

// Flash notifications
$successMessage = '';
$errorMessage = '';

if (!empty($_SESSION['success_message'])) {
    $successMessage = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (!empty($_SESSION['error_message'])) {
    $errorMessage = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
if (isset($_GET['backup']) && $_GET['backup'] === 'success') {
    $successMessage = 'Database backup downloaded successfully.';
}

// Allowed Date Formats
$allowedDateFormats = [
    'DD-MM-YYYY' => 'DD-MM-YYYY (e.g., ' . date('d-m-Y') . ') - Default',
    'YYYY-MM-DD' => 'YYYY-MM-DD (e.g., ' . date('Y-m-d') . ')',
    'DD/MM/YYYY' => 'DD/MM/YYYY (e.g., ' . date('d/m/Y') . ')',
    'MM/DD/YYYY' => 'MM/DD/YYYY (e.g., ' . date('m/d/Y') . ')',
    'd M Y'      => 'd M Y (e.g., ' . date('d M Y') . ')'
];

// -----------------------------------------------------------------------------
// POST Request Handlers: Restore Database OR Save Settings
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Step 9: Restore Database Handler (Requirements 3, 4, 6, 7)
    if (isset($_POST['action']) && $_POST['action'] === 'restore') {
        if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
            $errCode = $_FILES['backup_file']['error'] ?? -1;
            if ($errCode === UPLOAD_ERR_NO_FILE) {
                $errorMessage = 'Database restore failed: No file was selected. Please choose a .sql backup file.';
            } else {
                $errorMessage = 'Database restore failed: File upload error (Code: ' . (int)$errCode . ').';
            }
        } else {
            $file = $_FILES['backup_file'];
            $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            // Security check 1: Only allow .sql files (Requirement 4)
            if ($fileExt !== 'sql') {
                $errorMessage = 'Database restore failed: Invalid file type. Only .sql files are allowed for restore.';
            } elseif ($file['size'] <= 0 || $file['size'] > 25 * 1024 * 1024) {
                $errorMessage = 'Database restore failed: The uploaded file is empty or exceeds the 25MB limit.';
            } elseif (!is_uploaded_file($file['tmp_name'])) {
                $errorMessage = 'Database restore failed: File upload verification check failed.';
            } else {
                $content = file_get_contents($file['tmp_name']);
                if ($content === false || trim($content) === '') {
                    $errorMessage = 'Database restore failed: Unable to read file content or file is empty.';
                } elseif (preg_match('/<\?(?:php|=)/i', $content) || preg_match('/<script\b/i', $content)) {
                    // Security check 2: Do not execute arbitrary web scripts (Requirement 4)
                    $errorMessage = 'Database restore failed: Security check failed. Uploaded file contains forbidden executable code.';
                } else {
                    try {
                        $statements = splitSqlStatements($content);
                        if (empty($statements)) {
                            $errorMessage = 'Database restore failed: No valid SQL statements found in backup file.';
                        } else {
                            // Restore into tracker database using existing local PDO connection
                            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
                            foreach ($statements as $stmt) {
                                $stmtClean = trim($stmt);
                                if ($stmtClean !== '') {
                                    $pdo->exec($stmtClean);
                                }
                            }
                            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

                            // Requirement 6: After successful restore show message
                            $_SESSION['success_message'] = 'Database restored successfully.';
                            header('Location: settings.php');
                            exit;
                        }
                    } catch (Throwable $e) {
                        try {
                            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
                        } catch (Throwable $ignored) {}
                        error_log("Database Restore Error: " . $e->getMessage());
                        $errorMessage = 'Database restore failed: ' . $e->getMessage();
                    }
                }
            }
        }
    }

    // Step 6: Save General Settings Handler
    elseif ((isset($_POST['action']) && $_POST['action'] === 'save_settings') || isset($_POST['app_name'])) {
        $appNameInput = isset($_POST['app_name']) ? trim($_POST['app_name']) : '';
        if ($appNameInput === '' && isset($_POST['application_name'])) {
            $appNameInput = trim($_POST['application_name']);
        }

        if ($appNameInput === '') {
            $appNameInput = 'Expense Tracker';
        } elseif (mb_strlen($appNameInput) > 100) {
            $appNameInput = mb_substr($appNameInput, 0, 100);
        }

        $currency = 'PKR';

        $dateFormatInput = isset($_POST['date_format']) ? trim($_POST['date_format']) : 'DD-MM-YYYY';
        if (!array_key_exists($dateFormatInput, $allowedDateFormats)) {
            $dateFormatInput = 'DD-MM-YYYY';
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO settings (setting_key, setting_value)
                VALUES (:key, :val)
                ON DUPLICATE KEY UPDATE 
                    setting_value = VALUES(setting_value),
                    updated_at = CURRENT_TIMESTAMP
            ");

            $settingsToSave = [
                'app_name'         => $appNameInput,
                'application_name' => $appNameInput,
                'currency'         => $currency,
                'date_format'      => $dateFormatInput
            ];

            foreach ($settingsToSave as $k => $v) {
                $stmt->execute([
                    ':key' => $k,
                    ':val' => $v
                ]);
            }

            $pdo->commit();

            $_SESSION['success_message'] = 'Application settings have been saved successfully!';
            header('Location: settings.php');
            exit;

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Settings Save Error: " . $e->getMessage());
            $errorMessage = 'An error occurred while saving settings. Please try again.';
        }
    }

    // Feature 2: Change Password Handler
    elseif (isset($_POST['action']) && $_POST['action'] === 'change_password') {
        $currentPassword = isset($_POST['current_password']) ? (string)$_POST['current_password'] : '';
        $newPassword     = isset($_POST['new_password']) ? (string)$_POST['new_password'] : '';
        $confirmPassword = isset($_POST['confirm_password']) ? (string)$_POST['confirm_password'] : '';

        $currentUserId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $errorMessage = 'Please fill in all password fields.';
        } elseif (strlen($newPassword) < 8) {
            $errorMessage = 'New password must be at least 8 characters long.';
        } elseif ($newPassword !== $confirmPassword) {
            $errorMessage = 'New password and confirm password do not match.';
        } else {
            try {
                $userStmt = $pdo->prepare("SELECT id, password FROM `users` WHERE `id` = :id LIMIT 1");
                $userStmt->execute([':id' => $currentUserId]);
                $userRow = $userStmt->fetch();

                if (!$userRow || !password_verify($currentPassword, $userRow['password'])) {
                    $errorMessage = 'Incorrect current password. Please verify and try again.';
                } else {
                    $hashedNewPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $updateStmt = $pdo->prepare("UPDATE `users` SET `password` = :password, `updated_at` = CURRENT_TIMESTAMP WHERE `id` = :id");
                    $updateStmt->execute([
                        ':password' => $hashedNewPassword,
                        ':id'       => $currentUserId
                    ]);

                    // Require the user to log in again with new password
                    logoutUser();
                    if (session_status() === PHP_SESSION_NONE) {
                        session_start();
                    }
                    $_SESSION['login_message'] = 'Password changed successfully! Please log in with your new password.';
                    header('Location: login.php');
                    exit;
                }
            } catch (PDOException $e) {
                error_log("Password Change Error: " . $e->getMessage());
                $errorMessage = 'A database error occurred while updating your password. Please try again.';
            }
        }
    }
}

// Fetch Current Settings
$currentAppName    = getSetting('app_name', 'Expense Tracker');
$currentCurrency   = 'PKR';
$currentDateFormat = getSetting('date_format', 'DD-MM-YYYY');

// Determine formatted sample date for preview
$previewDate = formatExpenseDate(date('Y-m-d'));

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="main-content">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <main class="p-3 p-md-4">
        <!-- Page Header & Title -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1 small text-muted">
                        <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Settings</li>
                    </ol>
                </nav>
                <h4 class="fw-bold mb-1 text-dark">
                    <i class="bi bi-gear-fill text-primary me-2"></i>Application Settings
                </h4>
                <p class="text-muted small mb-0">
                    Configure your application preferences, manage database backups, and restore database records.
                </p>
            </div>
            <div>
                <a href="index.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
                    <i class="bi bi-arrow-left"></i>
                    <span>Back to Dashboard</span>
                </a>
            </div>
        </div>

        <!-- Success Flash Alert -->
        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-3 shadow-sm border-0 mb-4" role="alert" style="background-color: #ecfdf5; border-left: 4px solid #10b981 !important; color: #065f46;">
                <div class="d-flex align-items-center justify-content-center bg-success text-white rounded-circle flex-shrink-0" style="width: 32px; height: 32px;">
                    <i class="bi bi-check-lg fw-bold"></i>
                </div>
                <div>
                    <h6 class="mb-0 fw-bold">Operation Successful</h6>
                    <small><?php echo htmlspecialchars($successMessage); ?></small>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Error Flash Alert (Requirement 7) -->
        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-3 shadow-sm border-0 mb-4" role="alert" style="background-color: #fef2f2; border-left: 4px solid #ef4444 !important; color: #991b1b;">
                <div class="d-flex align-items-center justify-content-center bg-danger text-white rounded-circle flex-shrink-0" style="width: 32px; height: 32px;">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                </div>
                <div>
                    <h6 class="mb-0 fw-bold">Action Failed</h6>
                    <small><?php echo htmlspecialchars($errorMessage); ?></small>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Main Form & Overview Grid -->
        <div class="row g-4">
            <!-- Settings & Maintenance Column -->
            <div class="col-12 col-lg-8">
                <!-- 1. General Application Settings Card -->
                <div class="content-card shadow-sm mb-4">
                    <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary bg-opacity-10 text-primary p-2 rounded-3">
                                <i class="bi bi-sliders fs-6"></i>
                            </span>
                            <span class="fw-bold text-dark">General Application Configuration</span>
                        </div>
                        <span class="badge bg-light text-secondary border">Settings</span>
                    </div>

                    <div class="card-body p-4">
                        <form method="POST" action="settings.php" id="settingsForm">
                            <input type="hidden" name="action" value="save_settings">

                            <div class="mb-4">
                                <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                                    <i class="bi bi-app-indicator text-primary"></i>
                                    <h6 class="fw-bold mb-0 text-dark">Display Preferences</h6>
                                </div>

                                <!-- Setting 1: Application Name -->
                                <div class="mb-4">
                                    <label for="app_name" class="form-label fw-semibold text-dark">
                                        Application Name <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light text-muted border-end-0">
                                            <i class="bi bi-window"></i>
                                        </span>
                                        <input type="text" 
                                               class="form-control border-start-0 ps-0" 
                                               id="app_name" 
                                               name="app_name" 
                                               value="<?php echo htmlspecialchars($currentAppName); ?>" 
                                               maxlength="100" 
                                               required 
                                               placeholder="e.g. Expense Tracker">
                                    </div>
                                    <div class="form-text text-muted mt-1 small">
                                        <i class="bi bi-info-circle me-1"></i>
                                        Displayed in page title and navigation header across the application. Default: <code>Expense Tracker</code>.
                                    </div>
                                </div>

                                <!-- Setting 2: Currency -->
                                <div class="mb-4">
                                    <label for="currency_display" class="form-label fw-semibold text-dark">
                                        Currency Standard
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-success bg-opacity-10 text-success fw-bold border-end-0">
                                            <i class="bi bi-cash-stack me-1"></i> PKR
                                        </span>
                                        <input type="text" 
                                               class="form-control bg-light border-start-0 ps-2 fw-semibold text-dark" 
                                               id="currency_display" 
                                               value="PKR — Pakistani Rupees" 
                                               readonly>
                                        <span class="input-group-text bg-light text-muted border-start-0">
                                            <i class="bi bi-lock-fill" title="Locked to PKR standard"></i>
                                        </span>
                                    </div>
                                    <input type="hidden" name="currency" value="PKR">
                                    <div class="form-text text-muted mt-1 small">
                                        <i class="bi bi-shield-check text-success me-1"></i>
                                        Currency is strictly locked to <strong>PKR</strong> (Pakistani Rupee). Other currencies are disabled.
                                    </div>
                                </div>

                                <!-- Setting 3: Date Format -->
                                <div class="mb-4">
                                    <label for="date_format" class="form-label fw-semibold text-dark">
                                        Date Format
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light text-muted border-end-0">
                                            <i class="bi bi-calendar3"></i>
                                        </span>
                                        <select class="form-select border-start-0 ps-0" id="date_format" name="date_format">
                                            <?php foreach ($allowedDateFormats as $fmtKey => $fmtLabel): ?>
                                                <option value="<?php echo htmlspecialchars($fmtKey); ?>" <?php echo ($currentDateFormat === $fmtKey) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($fmtLabel); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-text text-muted mt-1 small">
                                        <i class="bi bi-clock-history me-1"></i>
                                        Default format: <code>DD-MM-YYYY</code>. Currently previews as: <strong class="text-primary"><?php echo htmlspecialchars($previewDate); ?></strong>.
                                    </div>
                                </div>
                            </div>

                            <hr class="my-4">

                            <!-- Action Buttons -->
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetToDefaults()">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i> Reset to Defaults
                                </button>
                                <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold shadow-sm d-inline-flex align-items-center gap-2">
                                    <i class="bi bi-check2-circle fs-5"></i>
                                    <span>Save Settings</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- 2. Feature 2: Change Password Card -->
                <div class="content-card shadow-sm mb-4">
                    <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-warning bg-opacity-10 text-warning p-2 rounded-3">
                                <i class="bi bi-shield-lock-fill fs-6 text-dark"></i>
                            </span>
                            <span class="fw-bold text-dark">Change Password</span>
                        </div>
                        <span class="badge bg-light text-secondary border">Security</span>
                    </div>

                    <div class="card-body p-4">
                        <p class="text-muted small mb-4">
                            Update your account password to keep your Expense Tracker secure. Passwords are encrypted using strong hashing.
                        </p>

                        <form method="POST" action="settings.php" id="changePasswordForm" autocomplete="off">
                            <input type="hidden" name="action" value="change_password">

                            <!-- Current Password -->
                            <div class="mb-3">
                                <label for="current_password" class="form-label fw-semibold text-dark small mb-1">
                                    Current Password <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted border-end-0">
                                        <i class="bi bi-key-fill"></i>
                                    </span>
                                    <input type="password" 
                                           class="form-control border-start-0 ps-0" 
                                           id="current_password" 
                                           name="current_password" 
                                           required 
                                           placeholder="Enter your current password">
                                </div>
                            </div>

                            <!-- New Password -->
                            <div class="mb-3">
                                <label for="new_password" class="form-label fw-semibold text-dark small mb-1">
                                    New Password <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted border-end-0">
                                        <i class="bi bi-lock-fill"></i>
                                    </span>
                                    <input type="password" 
                                           class="form-control border-start-0 ps-0" 
                                           id="new_password" 
                                           name="new_password" 
                                           required 
                                           minlength="8" 
                                           placeholder="At least 8 characters">
                                </div>
                                <div class="form-text text-muted mt-1 small">
                                    <i class="bi bi-info-circle me-1"></i> Minimum length is 8 characters. Never shared or stored in plain text.
                                </div>
                            </div>

                            <!-- Confirm New Password -->
                            <div class="mb-4">
                                <label for="confirm_password" class="form-label fw-semibold text-dark small mb-1">
                                    Confirm New Password <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted border-end-0">
                                        <i class="bi bi-check-circle-fill"></i>
                                    </span>
                                    <input type="password" 
                                           class="form-control border-start-0 ps-0" 
                                           id="confirm_password" 
                                           name="confirm_password" 
                                           required 
                                           minlength="8" 
                                           placeholder="Re-enter your new password">
                                </div>
                            </div>

                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-warning px-4 py-2 fw-semibold shadow-sm d-inline-flex align-items-center gap-2">
                                    <i class="bi bi-shield-check fs-5"></i>
                                    <span>Change Password</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- 3. Step 9: Database Backup & Restore Card -->
                <div class="content-card shadow-sm">
                    <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary bg-opacity-10 text-primary p-2 rounded-3">
                                <i class="bi bi-database-fill-gear fs-6"></i>
                            </span>
                            <span class="fw-bold text-dark">Database Backup &amp; Restore</span>
                        </div>
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Database: tracker</span>
                    </div>

                    <div class="card-body p-4">
                        <!-- Dynamic Alert Box for Instant Backup Notification -->
                        <div id="backupAlertPlaceholder"></div>

                        <p class="text-muted small mb-4">
                            Safeguard your expense data with full SQL dumps. You can create a complete backup of the <code>tracker</code> database at any time, or restore a previously saved <code>.sql</code> backup file.
                        </p>

                        <div class="row g-4">
                            <!-- Backup Database Card (Requirement 2 & 10) -->
                            <div class="col-12 col-md-6">
                                <div class="p-3 p-md-4 rounded-3 border h-100 d-flex flex-column justify-content-between" style="background-color: #f8fafc;">
                                    <div>
                                        <div class="d-flex align-items-center gap-3 mb-3">
                                            <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                                                <i class="bi bi-cloud-arrow-down-fill fs-4"></i>
                                            </div>
                                            <div>
                                                <h6 class="fw-bold mb-0 text-dark">Backup Database</h6>
                                                <small class="text-muted">Export complete SQL dump</small>
                                            </div>
                                        </div>
                                        <p class="text-secondary small mb-3">
                                            Creates a complete SQL backup of tables, structures, and existing data from <code>tracker</code>, downloaded as <code>tracker_backup_YYYY-MM-DD_HH-MM-SS.sql</code>.
                                        </p>
                                    </div>
                                    <div>
                                        <!-- Requirement 1: Backup Database Button -->
                                        <a href="settings.php?action=backup" 
                                           id="backupDatabaseBtn" 
                                           class="btn btn-primary d-inline-flex align-items-center justify-content-center gap-2 w-100 shadow-sm py-2"
                                           onclick="handleBackupDownloadClick(event)">
                                            <i class="bi bi-download"></i>
                                            <span>Backup Database</span>
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Restore Database Card (Requirement 3 & 10) -->
                            <div class="col-12 col-md-6">
                                <div class="p-3 p-md-4 rounded-3 border h-100 d-flex flex-column justify-content-between" style="background-color: #f8fafc;">
                                    <div>
                                        <div class="d-flex align-items-center gap-3 mb-3">
                                            <div class="rounded-circle bg-warning bg-opacity-10 text-warning d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                                                <i class="bi bi-cloud-arrow-up-fill fs-4"></i>
                                            </div>
                                            <div>
                                                <h6 class="fw-bold mb-0 text-dark">Restore Database</h6>
                                                <small class="text-muted">Import from .sql backup</small>
                                            </div>
                                        </div>
                                        <p class="text-secondary small mb-3">
                                            Select a <code>.sql</code> backup file from your computer to restore into the <code>tracker</code> database. Requires user confirmation before replacing data.
                                        </p>
                                    </div>
                                    <div>
                                        <!-- Requirement 1: Restore Database Button -->
                                        <button type="button" 
                                                class="btn btn-outline-danger d-inline-flex align-items-center justify-content-center gap-2 w-100 py-2" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#restoreDatabaseModal">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                            <span>Restore Database</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Side Overview & Info Column -->
            <div class="col-12 col-lg-4">
                <!-- Current Settings Card -->
                <div class="content-card shadow-sm mb-4">
                    <div class="card-header bg-white py-3 px-4 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-success bg-opacity-10 text-success p-2 rounded-3">
                                <i class="bi bi-check2-square fs-6"></i>
                            </span>
                            <span class="fw-bold text-dark">Current Settings</span>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <ul class="list-unstyled mb-0 d-flex flex-column gap-3">
                            <li class="d-flex align-items-start gap-3">
                                <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                    <i class="bi bi-window-sidebar fs-5"></i>
                                </div>
                                <div class="flex-grow-1 overflow-hidden">
                                    <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.05em;">Application Name</small>
                                    <strong class="text-dark text-truncate d-block" id="previewAppName"><?php echo htmlspecialchars($currentAppName); ?></strong>
                                </div>
                            </li>

                            <li class="d-flex align-items-start gap-3">
                                <div class="bg-success bg-opacity-10 text-success rounded-3 p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                    <i class="bi bi-cash-stack fs-5"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.05em;">Currency</small>
                                    <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-50 px-2 py-1 fw-bold">
                                        PKR (Rs)
                                    </span>
                                </div>
                            </li>

                            <li class="d-flex align-items-start gap-3">
                                <div class="bg-info bg-opacity-10 text-info rounded-3 p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                    <i class="bi bi-calendar-event fs-5"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.05em;">Date Format</small>
                                    <strong class="text-dark" id="previewFormatLabel"><?php echo htmlspecialchars($currentDateFormat); ?></strong>
                                    <div class="small text-muted">Preview: <code><?php echo htmlspecialchars($previewDate); ?></code></div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Database System Card -->
                <div class="content-card shadow-sm mb-4">
                    <div class="card-header bg-white py-3 px-4 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-secondary bg-opacity-10 text-secondary p-2 rounded-3">
                                <i class="bi bi-database fs-6"></i>
                            </span>
                            <span class="fw-bold text-dark">System Information</span>
                        </div>
                    </div>
                    <div class="card-body p-4 small">
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span class="text-muted">Target Database:</span>
                            <code class="fw-bold text-dark">tracker</code>
                        </div>
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span class="text-muted">Backup Format:</span>
                            <span class="fw-semibold text-dark">.sql (DDL + Data)</span>
                        </div>
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span class="text-muted">Restore Security:</span>
                            <span class="text-success fw-semibold"><i class="bi bi-shield-check me-1"></i>Validated .sql</span>
                        </div>
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span class="text-muted">Environment:</span>
                            <span class="text-muted">Local XAMPP MySQL</span>
                        </div>
                        <div class="d-flex justify-content-between py-2">
                            <span class="text-muted">Active Currency:</span>
                            <span class="fw-bold text-success">PKR Only</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Step 9: Restore Database Modal with Confirmation (Requirements 3 & 4) -->
    <div class="modal fade" id="restoreDatabaseModal" tabindex="-1" aria-labelledby="restoreDatabaseModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold d-flex align-items-center gap-2" id="restoreDatabaseModalLabel">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span>Confirm Database Restore</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <form method="POST" action="settings.php" enctype="multipart/form-data" id="restoreDatabaseForm">
                    <input type="hidden" name="action" value="restore">

                    <div class="modal-body p-4">
                        <!-- Clear Confirmation Message (Requirement 3) -->
                        <div class="alert alert-warning border-0 small d-flex align-items-start gap-2 mb-3" style="background-color: #fffbeb; color: #92400e;">
                            <i class="bi bi-exclamation-circle-fill fs-5 text-warning flex-shrink-0 mt-1"></i>
                            <div>
                                <strong>Warning:</strong> Restoring a database backup will overwrite existing tables and records in the <code>tracker</code> database with the backup file data. This action cannot be reversed.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="backup_file" class="form-label fw-semibold text-dark">
                                Select SQL Backup File (.sql) <span class="text-danger">*</span>
                            </label>
                            <input type="file" 
                                   name="backup_file" 
                                   id="backup_file" 
                                   class="form-control" 
                                   accept=".sql" 
                                   required>
                            <div class="form-text text-muted small mt-1">
                                <i class="bi bi-file-earmark-code me-1"></i>
                                Only valid <code>.sql</code> backup files are accepted. Max size: 25MB.
                            </div>
                        </div>

                        <div class="form-check p-2 bg-light rounded border">
                            <input class="form-check-input ms-0 me-2" type="checkbox" id="confirmRestoreCheck" required>
                            <label class="form-check-label small text-dark fw-semibold" for="confirmRestoreCheck">
                                I confirm that I want to restore the selected backup and understand existing data will be replaced.
                            </label>
                        </div>
                    </div>

                    <div class="modal-footer bg-light d-flex justify-content-between">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-danger d-inline-flex align-items-center gap-2 shadow-sm" id="confirmRestoreSubmitBtn">
                            <i class="bi bi-arrow-counterclockwise"></i>
                            <span>Confirm &amp; Restore</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    // Live preview update for Application Name
    const appNameInputEl = document.getElementById('app_name');
    if (appNameInputEl) {
        appNameInputEl.addEventListener('input', function() {
            const val = this.value.trim() || 'Expense Tracker';
            const preview = document.getElementById('previewAppName');
            if (preview) {
                preview.textContent = val;
            }
        });
    }

    // Helper function to restore defaults in the form
    function resetToDefaults() {
        if (confirm('Reset application settings to default values (MUSA / 3465340082, PKR, DD-MM-YYYY)?')) {
            document.getElementById('app_name').value = 'MUSA / 3465340082';
            document.getElementById('date_format').value = 'DD-MM-YYYY';
            const preview = document.getElementById('previewAppName');
            if (preview) {
                preview.textContent = 'MUSA / 3465340082';
            }
        }
    }

    // Requirement 5: Display success message when backup download is initiated
    function handleBackupDownloadClick(e) {
        const placeholder = document.getElementById('backupAlertPlaceholder');
        if (placeholder) {
            placeholder.innerHTML = `
                <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-3 shadow-sm border-0 mb-4" role="alert" style="background-color: #ecfdf5; border-left: 4px solid #10b981 !important; color: #065f46;">
                    <div class="d-flex align-items-center justify-content-center bg-success text-white rounded-circle flex-shrink-0" style="width: 32px; height: 32px;">
                        <i class="bi bi-check-lg fw-bold"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold">Backup Completed</h6>
                        <small>Database backup downloaded successfully.</small>
                    </div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
        }
    }

    // Client-side validation for restore file extension
    const restoreForm = document.getElementById('restoreDatabaseForm');
    if (restoreForm) {
        restoreForm.addEventListener('submit', function(e) {
            const fileInput = document.getElementById('backup_file');
            if (fileInput && fileInput.files.length > 0) {
                const fname = fileInput.files[0].name;
                if (!fname.toLowerCase().endsWith('.sql')) {
                    e.preventDefault();
                    alert('Invalid file type. Only .sql files are allowed for restore.');
                    return false;
                }
            }
        });
    }
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
