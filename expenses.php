<?php
/**
 * Project: Expense Tracker
 * Page: All Expenses
 * Currency: PKR (Pakistani Rupees)
 */

require_once __DIR__ . '/config/db.php';
requireLogin();

$pageTitle = 'All Expenses';
$pageHeader = 'All Expenses';
$activePage = 'expenses';

$paymentMethods = [
    'Cash',
    'Bank',
    'Easypaisa',
    'JazzCash',
    'Other'
];

// Handle Flash Messages
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

// Check for direct view query parameter (e.g. expenses.php?view=ID)
$viewModalExpense = null;
if (isset($_GET['view']) || isset($_GET['view_id'])) {
    $viewReqId = (int)($_GET['view'] ?? $_GET['view_id'] ?? 0);
    if ($viewReqId <= 0) {
        $errorMessage = 'Expense not found.';
    } else {
        try {
            $vStmt = $pdo->prepare("
                SELECT *
                FROM expenses
                WHERE id = :id
                LIMIT 1
            ");
            $vStmt->execute([':id' => $viewReqId]);
            $viewModalExpense = $vStmt->fetch();
            if (!$viewModalExpense) {
                $errorMessage = 'Expense not found.';
            }
        } catch (PDOException $e) {
            $errorMessage = 'Expense not found.';
        }
    }
}

// -----------------------------------------------------------------------------
// POST Handler: Delete Expense (Secure POST request)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $expenseId = (int)($_POST['expense_id'] ?? 0);

    if ($expenseId > 0) {
        try {
            $chkStmt = $pdo->prepare("SELECT title, amount FROM expenses WHERE id = :id LIMIT 1");
            $chkStmt->execute([':id' => $expenseId]);
            $expToDelete = $chkStmt->fetch();

            if ($expToDelete) {
                $delStmt = $pdo->prepare("DELETE FROM expenses WHERE id = :id");
                $delStmt->execute([':id' => $expenseId]);
                $_SESSION['success_message'] = "Expense \"{$expToDelete['title']}\" (" . formatPKR($expToDelete['amount']) . ") was deleted successfully.";
            } else {
                $_SESSION['error_message'] = "Expense not found or already deleted.";
            }
        } catch (PDOException $e) {
            error_log("Delete Expense Error: " . $e->getMessage());
            $_SESSION['error_message'] = "A database error occurred while deleting the expense.";
        }
    } else {
        $_SESSION['error_message'] = "Invalid expense ID specified for deletion.";
    }

    $redirectParams = [];
    if (!empty($_POST['search'])) $redirectParams['search'] = $_POST['search'];
    if (!empty($_POST['filter'])) $redirectParams['filter'] = $_POST['filter'];
    if (!empty($_POST['page'])) $redirectParams['page'] = $_POST['page'];
    $redirectUrl = 'expenses.php' . (!empty($redirectParams) ? '?' . http_build_query($redirectParams) : '');
    header("Location: {$redirectUrl}");
    exit;
}

// -----------------------------------------------------------------------------
// POST Handler: Edit Expense (PDO Prepared Statements)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $editId = (int)($_POST['expense_id'] ?? 0);
    $editTitle = isset($_POST['title']) ? trim($_POST['title']) : '';
    $editAmountInput = isset($_POST['amount']) ? trim($_POST['amount']) : '';
    $editPaymentMethod = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : 'Cash';
    $editExpenseDate = isset($_POST['expense_date']) ? trim($_POST['expense_date']) : '';
    $editNotes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

    $validationErrors = [];

    if ($editId <= 0) {
        $validationErrors[] = 'Invalid expense ID.';
    }
    if ($editTitle === '') {
        $validationErrors[] = 'Expense name is required.';
    } elseif (mb_strlen($editTitle) > 255) {
        $validationErrors[] = 'Expense name cannot exceed 255 characters.';
    }
    if ($editAmountInput === '' || !is_numeric($editAmountInput) || (float)$editAmountInput <= 0) {
        $validationErrors[] = 'Amount must be a valid number greater than 0 PKR.';
    } else {
        $editAmount = round((float)$editAmountInput, 2);
    }
    if (!in_array($editPaymentMethod, $paymentMethods, true)) {
        $editPaymentMethod = 'Cash';
    }
    if ($editExpenseDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $editExpenseDate) || !strtotime($editExpenseDate)) {
        $validationErrors[] = 'Please provide a valid date format (YYYY-MM-DD).';
    }
    if (mb_strlen($editNotes) > 1000) {
        $validationErrors[] = 'Description cannot exceed 1000 characters.';
    }

    if (empty($validationErrors)) {
        try {
            $updateStmt = $pdo->prepare("
                UPDATE expenses
                SET title = :title,
                    amount = :amount,
                    payment_method = :payment_method,
                    expense_date = :expense_date,
                    notes = :notes
                WHERE id = :id
            ");

            $updateStmt->execute([
                ':title'          => $editTitle,
                ':amount'         => $editAmount,
                ':payment_method' => $editPaymentMethod,
                ':expense_date'   => $editExpenseDate,
                ':notes'          => ($editNotes !== '' ? $editNotes : null),
                ':id'             => $editId,
            ]);

            $_SESSION['success_message'] = "Expense \"{$editTitle}\" updated successfully (" . formatPKR($editAmount) . ")!";

        } catch (PDOException $e) {
            error_log("Update Expense Error: " . $e->getMessage());
            $_SESSION['error_message'] = "A database error occurred while updating the expense.";
        }
    } else {
        $_SESSION['error_message'] = implode(' ', $validationErrors);
    }

    $redirectParams = [];
    if (!empty($_POST['search'])) $redirectParams['search'] = $_POST['search'];
    if (!empty($_POST['filter'])) $redirectParams['filter'] = $_POST['filter'];
    if (!empty($_POST['page'])) $redirectParams['page'] = $_POST['page'];
    $redirectUrl = 'expenses.php' . (!empty($redirectParams) ? '?' . http_build_query($redirectParams) : '');
    header("Location: {$redirectUrl}");
    exit;
}

// -----------------------------------------------------------------------------
// Filters & Search Parameters
// -----------------------------------------------------------------------------
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : 'all';

if (!in_array($filter, ['all', 'today', 'this_week', 'this_month'], true)) {
    $filter = 'all';
}

$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

$whereClauses = [];
$queryParams = [];

// 1. Search Filter (Expense Name, Payment Method, Notes)
if ($search !== '') {
    $whereClauses[] = "(e.title LIKE :search_title OR e.payment_method LIKE :search_pm OR e.notes LIKE :search_notes)";
    $searchTerm = '%' . $search . '%';
    $queryParams[':search_title'] = $searchTerm;
    $queryParams[':search_pm']    = $searchTerm;
    $queryParams[':search_notes'] = $searchTerm;
}

// 2. Date Filtering
if ($filter === 'today') {
    $whereClauses[] = "e.expense_date = CURRENT_DATE()";
} elseif ($filter === 'this_week') {
    $whereClauses[] = "YEARWEEK(e.expense_date, 1) = YEARWEEK(CURRENT_DATE(), 1)";
} elseif ($filter === 'this_month') {
    $whereClauses[] = "YEAR(e.expense_date) = YEAR(CURRENT_DATE()) AND MONTH(e.expense_date) = MONTH(CURRENT_DATE())";
}

if ($startDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) && strtotime($startDate)) {
    $whereClauses[] = "e.expense_date >= :start_date";
    $queryParams[':start_date'] = $startDate;
}
if ($endDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate) && strtotime($endDate)) {
    $whereClauses[] = "e.expense_date <= :end_date";
    $queryParams[':end_date'] = $endDate;
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

// -----------------------------------------------------------------------------
// Calculate Totals (Filtered Totals & Overall Lifetime Totals)
// -----------------------------------------------------------------------------
try {
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) AS total_count, COALESCE(SUM(e.amount), 0) AS total_amount 
        FROM expenses e
        {$whereSql}
    ");
    $countStmt->execute($queryParams);
    $filteredSummary = $countStmt->fetch();
    $totalFilteredCount = (int)$filteredSummary['total_count'];
    $totalFilteredAmount = (float)$filteredSummary['total_amount'];

    // Overall Lifetime Metrics
    $lifetimeStmt = $pdo->query("SELECT COUNT(*) AS total_count, COALESCE(SUM(amount), 0) AS total_amount FROM expenses");
    $lifetimeSummary = $lifetimeStmt->fetch();
    $totalLifetimeCount = (int)$lifetimeSummary['total_count'];
    $totalLifetimeAmount = (float)$lifetimeSummary['total_amount'];

} catch (PDOException $e) {
    die("Database Query Error: " . htmlspecialchars($e->getMessage()));
}

// -----------------------------------------------------------------------------
// Export Expenses to CSV
// -----------------------------------------------------------------------------
$isExportRequest = (isset($_GET['action']) && $_GET['action'] === 'export') || isset($_GET['export']);

if ($isExportRequest) {
    if ($totalFilteredCount === 0) {
        $_SESSION['error_message'] = 'No expenses found to export matching the selected criteria.';
        $backParams = [];
        if ($search !== '') $backParams['search'] = $search;
        if ($filter !== 'all') $backParams['filter'] = $filter;
        if ($startDate !== '') $backParams['start_date'] = $startDate;
        if ($endDate !== '') $backParams['end_date'] = $endDate;
        $backUrl = 'expenses.php' . (!empty($backParams) ? '?' . http_build_query($backParams) : '');
        session_write_close();
        header("Location: {$backUrl}");
        exit;
    }

    try {
        $exportSql = "
            SELECT 
                e.id, 
                e.title, 
                e.amount, 
                e.payment_method, 
                e.expense_date, 
                e.notes,
                e.created_at
            FROM expenses e
            {$whereSql}
            ORDER BY e.expense_date DESC, e.id DESC
        ";

        $exportStmt = $pdo->prepare($exportSql);
        foreach ($queryParams as $paramKey => $paramVal) {
            $exportStmt->bindValue($paramKey, $paramVal);
        }
        $exportStmt->execute();

        $downloadFilename = 'expenses_' . date('Y-m-d') . '.csv';

        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $downloadFilename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');

        $output = fopen('php://output', 'w');
        // UTF-8 BOM
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        $csvHeaders = [
            'Expense ID',
            'Expense Name',
            'Amount (PKR)',
            'Payment Method',
            'Date',
            'Description',
            'Created Date'
        ];
        fputcsv($output, $csvHeaders);

        $sanitizeCsvField = function ($val) {
            if ($val === null) return '';
            $str = (string)$val;
            if ($str === '') return '';
            $trimmed = ltrim($str);
            if ($trimmed !== '') {
                $firstChar = $trimmed[0];
                if (in_array($firstChar, ['=', '+', '-', '@', "\t", "\r", '|', '%'], true)) {
                    return "'" . $str;
                }
            }
            return $str;
        };

        while ($row = $exportStmt->fetch(PDO::FETCH_ASSOC)) {
            $formattedAmount = number_format((float)$row['amount'], 2, '.', '');
            $csvRow = [
                $row['id'],
                $sanitizeCsvField($row['title']),
                $formattedAmount,
                $sanitizeCsvField($row['payment_method']),
                $row['expense_date'],
                $sanitizeCsvField($row['notes']),
                $row['created_at']
            ];
            fputcsv($output, $csvRow);
        }

        fclose($output);
        exit;

    } catch (PDOException $e) {
        error_log("CSV Export Error: " . $e->getMessage());
        $_SESSION['error_message'] = 'A database error occurred while generating the CSV export file.';
        header("Location: expenses.php");
        exit;
    }
}

// Build Export URL preserving search and filter parameters
$exportUrlParams = ['action' => 'export'];
if ($search !== '') $exportUrlParams['search'] = $search;
if ($filter !== 'all') $exportUrlParams['filter'] = $filter;
if ($startDate !== '') $exportUrlParams['start_date'] = $startDate;
if ($endDate !== '') $exportUrlParams['end_date'] = $endDate;
$exportUrl = 'expenses.php?' . http_build_query($exportUrlParams);

// -----------------------------------------------------------------------------
// Pagination
// -----------------------------------------------------------------------------
$perPage = 10;
$totalPages = max(1, (int)ceil($totalFilteredCount / $perPage));
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
} elseif ($page > $totalPages && $totalFilteredCount > 0) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;

// -----------------------------------------------------------------------------
// Fetch Expenses List
// -----------------------------------------------------------------------------
try {
    $fetchSql = "
        SELECT 
            e.id, 
            e.title, 
            e.amount, 
            e.payment_method, 
            e.expense_date, 
            e.notes,
            e.created_at
        FROM expenses e
        {$whereSql}
        ORDER BY e.expense_date DESC, e.id DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($fetchSql);
    foreach ($queryParams as $paramKey => $paramVal) {
        $stmt->bindValue($paramKey, $paramVal);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $expensesList = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Database Query Error: " . htmlspecialchars($e->getMessage()));
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="main-content">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <main class="p-3 p-md-4">
        <!-- Toast / Alerts -->
        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-4 shadow-sm" role="alert">
                <i class="bi bi-check-circle-fill fs-5 me-2 text-success"></i>
                <div class="flex-grow-1">
                    <strong>Success!</strong> <?php echo htmlspecialchars($successMessage); ?>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4 shadow-sm" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-5 me-2 text-danger"></i>
                <div class="flex-grow-1">
                    <strong>Notice:</strong> <?php echo htmlspecialchars($errorMessage); ?>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Top Header & Action Row -->
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-4 gap-2">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1 small text-muted">
                        <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">All Expenses</li>
                    </ol>
                </nav>
                <h4 class="fw-bold mb-0 text-slate-800">All Expenses</h4>
            </div>
            <div class="d-flex align-items-center flex-wrap gap-2">
              
                </a>
                <a href="add-expense.php" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
                    <i class="bi bi-plus-lg"></i>
                    <span>Add New Expense</span>
                </a>
            </div>
        </div>

        <!-- Summary Cards Row -->
        <div class="row g-3 mb-4">
            <!-- Filtered Total Card -->
            <div class="col-12 col-sm-6 col-xl-4">
                <div class="content-card p-3 p-md-4 h-100 border-start border-primary border-4 shadow-sm">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-uppercase small fw-bold text-muted mb-1">
                                <?php echo ($filter !== 'all' || $search !== '') ? 'Filtered Total' : 'Total Expenses'; ?>
                            </div>
                            <div class="fs-4 fw-bold text-dark">
                                <?php echo formatPKR($totalFilteredAmount); ?>
                            </div>
                            <small class="text-muted">
                                <?php echo number_format($totalFilteredCount); ?> expense<?php echo ($totalFilteredCount === 1) ? '' : 's'; ?>
                            </small>
                        </div>
                        <div class="rounded-circle bg-primary bg-opacity-10 text-primary p-3 fs-3 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                            <i class="bi bi-wallet2"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Overall Lifetime Total Card -->
            <div class="col-12 col-sm-6 col-xl-4">
                <div class="content-card p-3 p-md-4 h-100 border-start border-success border-4 shadow-sm">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-uppercase small fw-bold text-muted mb-1">Lifetime Total</div>
                            <div class="fs-4 fw-bold text-success">
                                <?php echo formatPKR($totalLifetimeAmount); ?>
                            </div>
                            <small class="text-muted">
                                <?php echo number_format($totalLifetimeCount); ?> total transactions
                            </small>
                        </div>
                        <div class="rounded-circle bg-success bg-opacity-10 text-success p-3 fs-3 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                            <i class="bi bi-cash-stack"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Currency Indicator Card -->
            <div class="col-12 col-sm-12 col-xl-4">
                <div class="content-card p-3 p-md-4 h-100 border-start border-info border-4 shadow-sm">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-uppercase small fw-bold text-muted mb-1">Currency Standard</div>
                            <div class="fs-4 fw-bold text-dark">
                                PKR (Pak Rupee)
                            </div>
                            <small class="text-muted">All amounts in PKR</small>
                        </div>
                        <div class="rounded-circle bg-info bg-opacity-10 text-info p-3 fs-3 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                            <i class="bi bi-check2-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter & Search Toolbar Container -->
        <div class="content-card mb-4 p-3 shadow-sm">
            <div class="row g-3 align-items-center justify-content-between">
                <!-- Date Filter Buttons -->
                <div class="col-12 col-lg-auto">
                    <div class="d-flex align-items-center flex-wrap gap-1">
                        <span class="small fw-semibold text-muted me-2 d-none d-sm-inline">
                            <i class="bi bi-calendar-event me-1"></i>Date:
                        </span>
                        
                        <?php
                            $basePillParams = [];
                            if ($search !== '') $basePillParams['search'] = $search;
                            if ($startDate !== '') $basePillParams['start_date'] = $startDate;
                            if ($endDate !== '') $basePillParams['end_date'] = $endDate;
                        ?>

                        <a href="expenses.php?<?php echo http_build_query(array_merge($basePillParams, ['filter' => 'all'])); ?>" 
                           class="btn btn-sm <?php echo ($filter === 'all') ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                            All
                        </a>

                        <a href="expenses.php?<?php echo http_build_query(array_merge($basePillParams, ['filter' => 'today'])); ?>" 
                           class="btn btn-sm <?php echo ($filter === 'today') ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                            Today
                        </a>

                        <a href="expenses.php?<?php echo http_build_query(array_merge($basePillParams, ['filter' => 'this_week'])); ?>" 
                           class="btn btn-sm <?php echo ($filter === 'this_week') ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                            This Week
                        </a>

                        <a href="expenses.php?<?php echo http_build_query(array_merge($basePillParams, ['filter' => 'this_month'])); ?>" 
                           class="btn btn-sm <?php echo ($filter === 'this_month') ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                            This Month
                        </a>
                    </div>
                </div>

                <!-- Search Box -->
                <div class="col-12 col-lg-5 col-xl-4">
                    <form action="expenses.php" method="GET" class="d-flex gap-2">
                        <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                        <?php if ($startDate !== ''): ?>
                            <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
                        <?php endif; ?>
                        <?php if ($endDate !== ''): ?>
                            <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
                        <?php endif; ?>
                        
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white text-muted">
                                <i class="bi bi-search"></i>
                            </span>
                            <input 
                                type="text" 
                                name="search" 
                                class="form-control form-control-sm" 
                                placeholder="Search by name, payment method..." 
                                value="<?php echo htmlspecialchars($search); ?>"
                            >
                            <?php if ($search !== ''): ?>
                                <?php
                                    $clearParams = $_GET;
                                    unset($clearParams['search']);
                                    $clearUrl = 'expenses.php' . (!empty($clearParams) ? '?' . http_build_query($clearParams) : '');
                                ?>
                                <a href="<?php echo htmlspecialchars($clearUrl); ?>" class="btn btn-outline-secondary" title="Clear Search">
                                    <i class="bi bi-x-lg"></i>
                                </a>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-primary">Search</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Active Filter Status Indicator -->
            <?php if ($search !== '' || $filter !== 'all' || $startDate !== '' || $endDate !== ''): ?>
                <div class="mt-3 pt-2 border-top d-flex flex-wrap align-items-center justify-content-between gap-2 small text-muted">
                    <div>
                        Active filters: 
                        <?php if ($filter !== 'all'): ?>
                            <span class="badge bg-light text-dark border me-1">
                                <?php echo ucwords(str_replace('_', ' ', $filter)); ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($startDate !== '' || $endDate !== ''): ?>
                            <span class="badge bg-light text-dark border me-1">
                                Date: <?php echo htmlspecialchars($startDate ?: 'Start'); ?> to <?php echo htmlspecialchars($endDate ?: 'End'); ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($search !== ''): ?>
                            <span class="badge bg-light text-dark border">
                                Search: "<?php echo htmlspecialchars($search); ?>"
                            </span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <a href="expenses.php" class="text-decoration-none text-danger fw-semibold">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Filters
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Expenses Table Card -->
        <div class="content-card shadow-sm">
            <div class="card-header d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-receipt-cutoff text-primary fs-5"></i>
                    <h6 class="mb-0 fw-bold">Expense Transactions</h6>
                </div>
                <span class="badge bg-light text-secondary border">
                    Showing <?php echo count($expensesList); ?> of <?php echo number_format($totalFilteredCount); ?> Records
                </span>
            </div>

            <div class="table-responsive">
                <table class="table table-custom align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th style="width: 140px;">Date</th>
                            <th>Expense Name</th>
                            <th style="width: 160px;">Payment Method</th>
                            <th class="text-end" style="width: 180px;">Amount (PKR)</th>
                            <th class="text-end" style="width: 130px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($expensesList)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                                        <h6 class="fw-bold text-dark">No expenses found</h6>
                                        <p class="small text-secondary mb-3">
                                            <?php if ($search !== '' || $filter !== 'all'): ?>
                                                Try adjusting your date filters or search terms.
                                            <?php else: ?>
                                                You haven't recorded any expenses yet.
                                            <?php endif; ?>
                                        </p>
                                        <?php if ($search !== '' || $filter !== 'all'): ?>
                                            <a href="expenses.php" class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-arrow-counterclockwise me-1"></i> Clear Filter
                                            </a>
                                        <?php else: ?>
                                            <a href="add-expense.php" class="btn btn-sm btn-primary">
                                                <i class="bi bi-plus-lg me-1"></i> Add First Expense
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($expensesList as $idx => $expense): ?>
                                <?php
                                    $rowNumber = $offset + $idx + 1;
                                    $formattedDate = function_exists('formatExpenseDate') 
                                        ? formatExpenseDate($expense['expense_date']) 
                                        : date('d M Y', strtotime($expense['expense_date']));
                                ?>
                                <tr>
                                    <!-- # -->
                                    <td class="text-muted fw-semibold small">
                                        <?php echo $rowNumber; ?>
                                    </td>

                                    <!-- Date -->
                                    <td>
                                        <div class="d-flex align-items-center gap-2 text-nowrap">
                                            <i class="bi bi-calendar3 text-muted small"></i>
                                            <span class="fw-medium text-dark"><?php echo htmlspecialchars($formattedDate); ?></span>
                                        </div>
                                    </td>

                                    <!-- Expense Name & Notes -->
                                    <td>
                                        <div class="fw-semibold text-dark text-break">
                                            <?php echo htmlspecialchars($expense['title']); ?>
                                        </div>
                                        <?php if (!empty($expense['notes'])): ?>
                                            <small class="text-muted text-truncate d-block" style="max-width: 320px;" title="<?php echo htmlspecialchars($expense['notes']); ?>">
                                                <?php echo htmlspecialchars($expense['notes']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Payment Method -->
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <i class="bi bi-credit-card-2-front me-1 text-muted"></i>
                                            <?php echo htmlspecialchars($expense['payment_method']); ?>
                                        </span>
                                    </td>

                                    <!-- Amount in PKR -->
                                    <td class="text-end">
                                        <span class="fw-bold text-danger">
                                            <?php echo formatPKR($expense['amount']); ?>
                                        </span>
                                    </td>

                                    <!-- Actions -->
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <!-- View Action -->
                                            <a 
                                                href="view-expense.php?id=<?php echo $expense['id']; ?>" 
                                                class="btn btn-light action-btn border view-btn" 
                                                title="View Expense Details"
                                                data-id="<?php echo $expense['id']; ?>"
                                                data-title="<?php echo htmlspecialchars($expense['title'], ENT_QUOTES); ?>"
                                                data-amount="<?php echo htmlspecialchars(formatPKR($expense['amount']), ENT_QUOTES); ?>"
                                                data-payment="<?php echo htmlspecialchars($expense['payment_method'], ENT_QUOTES); ?>"
                                                data-date="<?php echo htmlspecialchars($formattedDate, ENT_QUOTES); ?>"
                                                data-notes="<?php echo htmlspecialchars($expense['notes'] ?? '', ENT_QUOTES); ?>"
                                                data-created="<?php echo htmlspecialchars(!empty($expense['created_at']) ? date('d M Y, h:i A', strtotime($expense['created_at'])) : 'N/A', ENT_QUOTES); ?>"
                                            >
                                                <i class="bi bi-eye text-primary"></i>
                                            </a>

                                            <!-- Edit Action -->
                                            <button 
                                                type="button" 
                                                class="btn btn-light action-btn border edit-btn" 
                                                title="Edit Expense"
                                                data-id="<?php echo $expense['id']; ?>"
                                                data-title="<?php echo htmlspecialchars($expense['title'], ENT_QUOTES); ?>"
                                                data-amount="<?php echo number_format($expense['amount'], 2, '.', ''); ?>"
                                                data-payment="<?php echo htmlspecialchars($expense['payment_method'], ENT_QUOTES); ?>"
                                                data-date="<?php echo htmlspecialchars($expense['expense_date'], ENT_QUOTES); ?>"
                                                data-notes="<?php echo htmlspecialchars($expense['notes'] ?? '', ENT_QUOTES); ?>"
                                            >
                                                <i class="bi bi-pencil text-primary"></i>
                                            </button>

                                            <!-- Delete Action -->
                                            <button 
                                                type="button" 
                                                class="btn btn-light action-btn border delete-btn" 
                                                title="Delete Expense"
                                                data-id="<?php echo $expense['id']; ?>"
                                                data-title="<?php echo htmlspecialchars($expense['title'], ENT_QUOTES); ?>"
                                                data-amount="<?php echo htmlspecialchars(formatPKR($expense['amount']), ENT_QUOTES); ?>"
                                            >
                                                <i class="bi bi-trash text-danger"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>

                    <!-- Total Row -->
                    <?php if (!empty($expensesList)): ?>
                        <tfoot class="table-light border-top">
                            <tr>
                                <th colspan="4" class="text-end fw-bold text-dark py-3">
                                    Total Amount (<?php echo ($filter !== 'all' || $search !== '') ? 'Filtered' : 'All'; ?>):
                                </th>
                                <th class="text-end py-3">
                                    <span class="amount-text text-danger fs-6 fw-bold">
                                        <?php echo formatPKR($totalFilteredAmount); ?>
                                    </span>
                                </th>
                                <th></th>
                            </tr>
                        </tfoot>
                    <?php endif; ?>
                </table>
            </div>

            <!-- Pagination & Summary Footer -->
            <div class="p-3 bg-light border-top d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                <div class="small text-muted">
                    Showing <strong><?php echo ($totalFilteredCount > 0) ? ($offset + 1) : 0; ?></strong> to 
                    <strong><?php echo min($offset + $perPage, $totalFilteredCount); ?></strong> of 
                    <strong><?php echo number_format($totalFilteredCount); ?></strong> entries
                </div>

                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Expenses Page Navigation">
                        <ul class="pagination pagination-sm mb-0">
                            <!-- Previous Page -->
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <?php $prevQuery = array_merge($_GET, ['page' => $page - 1]); ?>
                                <a class="page-link" href="expenses.php?<?php echo http_build_query($prevQuery); ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo; Prev</span>
                                </a>
                            </li>

                            <!-- Page Numbers -->
                            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                <?php $pageQuery = array_merge($_GET, ['page' => $p]); ?>
                                <li class="page-item <?php echo ($page === $p) ? 'active' : ''; ?>">
                                    <a class="page-link" href="expenses.php?<?php echo http_build_query($pageQuery); ?>">
                                        <?php echo $p; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <!-- Next Page -->
                            <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                <?php $nextQuery = array_merge($_GET, ['page' => $page + 1]); ?>
                                <a class="page-link" href="expenses.php?<?php echo http_build_query($nextQuery); ?>" aria-label="Next">
                                    <span aria-hidden="true">Next &raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </main>

<!-- Edit Expense Modal -->
<div class="modal fade" id="editExpenseModal" tabindex="-1" aria-labelledby="editExpenseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="editExpenseModalLabel">
                    <i class="bi bi-pencil-square text-primary me-2"></i>Edit Expense
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form action="expenses.php" method="POST" id="editExpenseForm">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="expense_id" id="edit_expense_id" value="">
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                    <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                    <input type="hidden" name="page" value="<?php echo htmlspecialchars($page); ?>">

                    <div class="row g-3">
                        <!-- Expense Name -->
                        <div class="col-12">
                            <label for="edit_title" class="form-label fw-semibold text-dark small mb-1">
                                Expense Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="title" id="edit_title" class="form-control" maxlength="255" required>
                        </div>

                        <!-- Amount in PKR -->
                        <div class="col-12 col-md-6">
                            <label for="edit_amount" class="form-label fw-semibold text-dark small mb-1">
                                Amount (PKR) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light fw-bold text-dark">PKR</span>
                                <input type="number" name="amount" id="edit_amount" class="form-control" step="0.01" min="0.01" required>
                            </div>
                        </div>

                        <!-- Date -->
                        <div class="col-12 col-md-6">
                            <label for="edit_expense_date" class="form-label fw-semibold text-dark small mb-1">
                                Date <span class="text-danger">*</span>
                            </label>
                            <input type="date" name="expense_date" id="edit_expense_date" class="form-control" required>
                        </div>

                        <!-- Payment Method -->
                        <div class="col-12 col-md-6">
                            <label for="edit_payment_method" class="form-label fw-semibold text-dark small mb-1">
                                Payment Method
                            </label>
                            <select name="payment_method" id="edit_payment_method" class="form-select">
                                <?php foreach ($paymentMethods as $pm): ?>
                                    <option value="<?php echo htmlspecialchars($pm); ?>"><?php echo htmlspecialchars($pm); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Description / Notes -->
                        <div class="col-12">
                            <label for="edit_notes" class="form-label fw-semibold text-dark small mb-1">
                                Description <span class="text-muted fw-normal">(Optional)</span>
                            </label>
                            <textarea name="notes" id="edit_notes" class="form-control" rows="3" maxlength="1000"></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-1 shadow-sm">
                        <i class="bi bi-check2-circle"></i>
                        <span>Save Changes</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold" id="deleteConfirmModalLabel">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Confirm Deletion
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form action="expenses.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="expense_id" id="delete_expense_id" value="">
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                    <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                    <input type="hidden" name="page" value="<?php echo htmlspecialchars($page); ?>">

                    <p class="mb-3">Are you sure you want to permanently delete this expense?</p>

                    <div class="card bg-light border p-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-muted small">Expense Name:</span>
                            <strong id="delete_expense_title" class="text-dark"></strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small">Amount:</span>
                            <span id="delete_expense_amount" class="text-danger fw-bold"></span>
                        </div>
                    </div>

                    <div class="small text-danger mt-3">
                        <i class="bi bi-info-circle me-1"></i> This action cannot be undone.
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger d-inline-flex align-items-center gap-1 shadow-sm">
                        <i class="bi bi-trash-fill"></i>
                        <span>Delete Expense</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Expense Details Modal -->
<div class="modal fade" id="viewExpenseModal" tabindex="-1" aria-labelledby="viewExpenseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold d-flex align-items-center gap-2" id="viewExpenseModalLabel">
                    <i class="bi bi-receipt text-primary"></i>
                    <span>Expense Details</span>
                    <span id="view_modal_id_badge" class="badge bg-secondary bg-opacity-10 text-secondary border fs-6"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-3 p-md-4">
                <div class="p-3 rounded-3 mb-4 border bg-light">
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2">
                        <div>
                            <div class="text-uppercase small fw-bold text-muted mb-1">Expense Name</div>
                            <h5 class="fw-bold text-dark mb-0" id="view_modal_title"></h5>
                        </div>
                        <div class="text-sm-end">
                            <div class="text-uppercase small fw-bold text-muted mb-1">Amount</div>
                            <div class="amount-text text-danger fs-4 fw-bold" id="view_modal_amount"></div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <tbody>
                            <tr>
                                <th class="bg-light text-muted fw-semibold" style="width: 200px;">
                                    <i class="bi bi-hash text-primary me-2"></i>Expense ID
                                </th>
                                <td class="fw-bold text-dark" id="view_modal_id"></td>
                            </tr>
                            <tr>
                                <th class="bg-light text-muted fw-semibold">
                                    <i class="bi bi-type text-primary me-2"></i>Expense Name
                                </th>
                                <td class="fw-medium text-dark" id="view_modal_title_row"></td>
                            </tr>
                            <tr>
                                <th class="bg-light text-muted fw-semibold">
                                    <i class="bi bi-cash-stack text-primary me-2"></i>Amount (PKR)
                                </th>
                                <td>
                                    <span class="amount-text text-danger fw-bold" id="view_modal_amount_row"></span>
                                </td>
                            </tr>
                            <tr>
                                <th class="bg-light text-muted fw-semibold">
                                    <i class="bi bi-credit-card-2-front text-primary me-2"></i>Payment Method
                                </th>
                                <td>
                                    <span class="badge bg-light text-dark border" id="view_modal_payment"></span>
                                </td>
                            </tr>
                            <tr>
                                <th class="bg-light text-muted fw-semibold">
                                    <i class="bi bi-calendar3 text-primary me-2"></i>Date
                                </th>
                                <td class="fw-medium text-dark" id="view_modal_date"></td>
                            </tr>
                            <tr>
                                <th class="bg-light text-muted fw-semibold align-top pt-3">
                                    <i class="bi bi-chat-left-text text-primary me-2"></i>Description
                                </th>
                                <td class="pt-3" id="view_modal_notes"></td>
                            </tr>
                            <tr>
                                <th class="bg-light text-muted fw-semibold">
                                    <i class="bi bi-clock-history text-primary me-2"></i>Recorded At
                                </th>
                                <td class="text-muted" id="view_modal_created"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="modal-footer bg-light d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="d-flex align-items-center gap-2">
                    <a href="#" id="view_modal_edit_btn" class="btn btn-primary d-inline-flex align-items-center gap-1 shadow-sm">
                        <i class="bi bi-pencil-square"></i>
                        <span>Edit Expense</span>
                    </a>
                    <a href="#" id="view_modal_full_page_btn" class="btn btn-outline-primary d-inline-flex align-items-center gap-1 shadow-sm">
                        <i class="bi bi-box-arrow-up-right"></i>
                        <span class="d-none d-sm-inline">Full Page</span>
                    </a>
                </div>
                <button type="button" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1" data-bs-dismiss="modal">
                    <span>Close</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Edit Modal Handling
    const editModalEl = document.getElementById('editExpenseModal');
    let editModalInstance = null;
    if (editModalEl && typeof bootstrap !== 'undefined') {
        editModalInstance = new bootstrap.Modal(editModalEl);
    }

    document.querySelectorAll('.edit-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = this.getAttribute('data-id');
            const title = this.getAttribute('data-title');
            const amount = this.getAttribute('data-amount');
            const payment = this.getAttribute('data-payment');
            const date = this.getAttribute('data-date');
            const notes = this.getAttribute('data-notes');

            document.getElementById('edit_expense_id').value = id;
            document.getElementById('edit_title').value = title;
            document.getElementById('edit_amount').value = amount;
            document.getElementById('edit_payment_method').value = payment;
            document.getElementById('edit_expense_date').value = date;
            document.getElementById('edit_notes').value = notes;

            if (editModalInstance) {
                editModalInstance.show();
            }
        });
    });

    // 2. Delete Confirmation Modal
    const deleteModalEl = document.getElementById('deleteConfirmModal');
    let deleteModalInstance = null;
    if (deleteModalEl && typeof bootstrap !== 'undefined') {
        deleteModalInstance = new bootstrap.Modal(deleteModalEl);
    }

    document.querySelectorAll('.delete-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = this.getAttribute('data-id');
            const title = this.getAttribute('data-title');
            const amount = this.getAttribute('data-amount');

            document.getElementById('delete_expense_id').value = id;
            document.getElementById('delete_expense_title').textContent = title;
            document.getElementById('delete_expense_amount').textContent = amount;

            if (deleteModalInstance) {
                deleteModalInstance.show();
            }
        });
    });

    // 3. View Details Modal
    const viewModalEl = document.getElementById('viewExpenseModal');
    let viewModalInstance = null;
    if (viewModalEl && typeof bootstrap !== 'undefined') {
        viewModalInstance = new bootstrap.Modal(viewModalEl);
    }

    function populateViewModal(data) {
        document.getElementById('view_modal_id_badge').textContent = '#' + data.id;
        document.getElementById('view_modal_id').textContent = '#' + data.id;
        document.getElementById('view_modal_title').textContent = data.title;
        document.getElementById('view_modal_title_row').textContent = data.title;
        document.getElementById('view_modal_amount').textContent = data.amount;
        document.getElementById('view_modal_amount_row').textContent = data.amount;
        document.getElementById('view_modal_payment').textContent = data.payment;
        document.getElementById('view_modal_date').textContent = data.date;
        document.getElementById('view_modal_created').textContent = data.created || 'N/A';

        const notesCell = document.getElementById('view_modal_notes');
        if (data.notes && data.notes.trim() !== '') {
            notesCell.innerHTML = '<div class="p-2 bg-light rounded border text-dark" style="white-space: pre-wrap;">' + data.notes + '</div>';
        } else {
            notesCell.innerHTML = '<span class="text-muted fst-italic">No additional notes provided.</span>';
        }

        document.getElementById('view_modal_edit_btn').href = 'edit-expense.php?id=' + data.id;
        document.getElementById('view_modal_full_page_btn').href = 'view-expense.php?id=' + data.id;
    }

    document.querySelectorAll('.view-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            populateViewModal({
                id: this.getAttribute('data-id'),
                title: this.getAttribute('data-title'),
                amount: this.getAttribute('data-amount'),
                payment: this.getAttribute('data-payment'),
                date: this.getAttribute('data-date'),
                notes: this.getAttribute('data-notes'),
                created: this.getAttribute('data-created')
            });
            if (viewModalInstance) {
                viewModalInstance.show();
            }
        });
    });

    <?php if (!empty($viewModalExpense)): ?>
    if (viewModalInstance) {
        populateViewModal({
            id: <?php echo (int)$viewModalExpense['id']; ?>,
            title: <?php echo json_encode($viewModalExpense['title']); ?>,
            amount: <?php echo json_encode(formatPKR($viewModalExpense['amount'])); ?>,
            payment: <?php echo json_encode($viewModalExpense['payment_method']); ?>,
            date: <?php echo json_encode(function_exists('formatExpenseDate') ? formatExpenseDate($viewModalExpense['expense_date']) : $viewModalExpense['expense_date']); ?>,
            notes: <?php echo json_encode($viewModalExpense['notes'] ?? ''); ?>,
            created: <?php echo json_encode(!empty($viewModalExpense['created_at']) ? date('d M Y, h:i A', strtotime($viewModalExpense['created_at'])) : 'N/A'); ?>
        });
        viewModalInstance.show();
    }
    <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
