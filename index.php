<?php
/**
 * Project: Expense Tracker
 * Page: Dashboard
 * Currency: PKR (Pakistani Rupees)
 */

require_once __DIR__ . '/config/db.php';
requireLogin();

$pageTitle = 'Dashboard';
$pageHeader = 'Dashboard Overview';
$activePage = 'dashboard';

// Handle Flash Messages
$successMessage = '';
$errorMessage = '';

if (!empty($_SESSION['success_message'])) {
    $successMessage = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
} elseif (isset($_GET['success'])) {
    $successMessage = 'Expense has been saved successfully!';
}

if (!empty($_SESSION['error_message'])) {
    $errorMessage = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// -----------------------------------------------------------------------------
// POST Handler: Delete Expense from Dashboard
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $deleteId = (int)($_POST['expense_id'] ?? 0);

    if ($deleteId > 0) {
        try {
            $chkStmt = $pdo->prepare("SELECT title, amount FROM expenses WHERE id = :id LIMIT 1");
            $chkStmt->execute([':id' => $deleteId]);
            $expToDelete = $chkStmt->fetch();

            if ($expToDelete) {
                $delStmt = $pdo->prepare("DELETE FROM expenses WHERE id = :id");
                $delStmt->execute([':id' => $deleteId]);
                $_SESSION['success_message'] = "Expense \"{$expToDelete['title']}\" (" . formatPKR($expToDelete['amount']) . ") was deleted successfully.";
            } else {
                $_SESSION['error_message'] = "Expense not found or already deleted.";
            }
        } catch (PDOException $e) {
            error_log("Dashboard Delete Error: " . $e->getMessage());
            $_SESSION['error_message'] = "A database error occurred while deleting the expense.";
        }
    }
    header('Location: index.php');
    exit;
}

// -----------------------------------------------------------------------------
// Fetch Real Summary Metrics & Recent Expenses from MySQL
// -----------------------------------------------------------------------------
try {
    // 1. Total Expenses (Lifetime)
    $stmtTotal = $pdo->query("SELECT COALESCE(SUM(amount), 0) AS total_amount FROM expenses");
    $totalExpenses = (float)$stmtTotal->fetchColumn();

    // 2. Today's Expenses
    $stmtToday = $pdo->query("
        SELECT COALESCE(SUM(amount), 0) AS today_amount 
        FROM expenses 
        WHERE expense_date = CURRENT_DATE()
    ");
    $todayExpenses = (float)$stmtToday->fetchColumn();

    // 3. This Month's Expenses
    $stmtMonth = $pdo->query("
        SELECT COALESCE(SUM(amount), 0) AS month_amount 
        FROM expenses 
        WHERE YEAR(expense_date) = YEAR(CURRENT_DATE()) 
          AND MONTH(expense_date) = MONTH(CURRENT_DATE())
    ");
    $thisMonthExpenses = (float)$stmtMonth->fetchColumn();

    // 4. Total Number of Expenses
    $stmtCount = $pdo->query("SELECT COUNT(*) AS total_count FROM expenses");
    $expensesCount = (int)$stmtCount->fetchColumn();

    // 5. Recent Expenses: Latest 5 records
    $stmtRecent = $pdo->query("
        SELECT 
            id, 
            title, 
            amount, 
            payment_method, 
            expense_date, 
            notes
        FROM expenses
        ORDER BY expense_date DESC, id DESC
        LIMIT 5
    ");
    $recentExpenses = $stmtRecent->fetchAll();

} catch (PDOException $e) {
    error_log("Dashboard Database Query Error: " . $e->getMessage());
    $totalExpenses = 0.0;
    $todayExpenses = 0.0;
    $thisMonthExpenses = 0.0;
    $expensesCount = 0;
    $recentExpenses = [];
    $errorMessage = "Unable to load dashboard data from database: " . htmlspecialchars($e->getMessage());
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Main Content Area -->
<div class="main-content">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <main class="p-3 p-md-4">
        <!-- Success Alert Message -->
        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-4 shadow-sm" role="alert">
                <i class="bi bi-check-circle-fill fs-5 me-2 text-success"></i>
                <div class="flex-grow-1">
                    <strong>Success!</strong> <?php echo htmlspecialchars($successMessage); ?>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Error Alert Message -->
        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4 shadow-sm" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-5 me-2 text-danger"></i>
                <div class="flex-grow-1">
                    <strong>Notice:</strong> <?php echo htmlspecialchars($errorMessage); ?>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Welcome Banner & Quick Action Buttons -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
            <div>
                <h4 class="fw-bold mb-1 text-slate-800">Financial Summary</h4>
                <p class="text-muted mb-0 small">Keep track of your everyday spending in Pakistani Rupees (PKR).</p>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="badge bg-light text-dark border px-3 py-2 d-none d-sm-inline-flex align-items-center">
                    <i class="bi bi-calendar-check text-primary me-1"></i>
                    Current Month: <strong><?php echo date('F Y'); ?></strong>
                </span>
                <a href="expenses.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
                    <i class="bi bi-receipt-cutoff"></i>
                    <span>All Expenses</span>
                </a>
                <a href="add-expense.php" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
                    <i class="bi bi-plus-lg"></i>
                    <span>Add Expense</span>
                </a>
            </div>
        </div>

        <!-- 3. Dashboard Summary Cards -->
        <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-4 g-3 g-md-4 mb-4">
            <!-- Card 1: Total Expenses -->
            <div class="col">
                <div class="summary-card card-total">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="card-title-text">Total Expenses</div>
                            <div class="card-value"><?php echo formatPKR($totalExpenses); ?></div>
                            <div class="card-subtitle">Lifetime spending</div>
                        </div>
                        <div class="card-icon-wrapper">
                            <i class="bi bi-wallet2"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 2: Today's Expenses -->
            <div class="col">
                <div class="summary-card card-today">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="card-title-text">Today's Expenses</div>
                            <div class="card-value"><?php echo formatPKR($todayExpenses); ?></div>
                            <div class="card-subtitle"><?php echo date('d M Y'); ?></div>
                        </div>
                        <div class="card-icon-wrapper">
                            <i class="bi bi-clock-history"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 3: This Month's Expenses -->
            <div class="col">
                <div class="summary-card card-month">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="card-title-text">This Month's Expenses</div>
                            <div class="card-value"><?php echo formatPKR($thisMonthExpenses); ?></div>
                            <div class="card-subtitle"><?php echo date('F Y'); ?></div>
                        </div>
                        <div class="card-icon-wrapper">
                            <i class="bi bi-calendar-month"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 4: Number of Expenses -->
            <div class="col">
                <div class="summary-card card-count">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="card-title-text">Total Transactions</div>
                            <div class="card-value"><?php echo number_format($expensesCount); ?></div>
                            <div class="card-subtitle">Recorded entries</div>
                        </div>
                        <div class="card-icon-wrapper">
                            <i class="bi bi-receipt"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Recent Expenses Table Section -->
        <div class="content-card shadow-sm">
            <div class="card-header py-3 px-4 d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <h5 class="mb-0 fw-bold">Recent Expenses</h5>
                    <small class="text-muted">Showing latest 5 recorded transactions</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1">
                        <?php echo count($recentExpenses); ?> Records
                    </span>
                    <a href="add-expense.php" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
                        <i class="bi bi-plus-lg"></i>
                        <span class="d-none d-sm-inline">Add Expense</span>
                    </a>
                    <a href="expenses.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
                        <i class="bi bi-arrow-right"></i>
                        <span>All Expenses</span>
                    </a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-custom align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col" style="width: 140px;">Date</th>
                            <th scope="col">Expense Name</th>
                            <th scope="col" style="width: 160px;">Payment Method</th>
                            <th scope="col" class="text-end" style="width: 170px;">Amount (PKR)</th>
                            <th scope="col" class="text-end" style="width: 130px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentExpenses)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <div class="text-muted py-3">
                                        <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                                        <h6 class="fw-bold mb-1 text-dark">No expenses recorded yet.</h6>
                                        <p class="small text-secondary mb-3">Track your daily spending by recording your first expense transaction.</p>
                                        <div class="d-flex justify-content-center gap-2">
                                            <a href="add-expense.php" class="btn btn-primary btn-sm shadow-sm">
                                                <i class="bi bi-plus-lg me-1"></i> Add Expense
                                            </a>
                                            <a href="expenses.php" class="btn btn-outline-secondary btn-sm shadow-sm">
                                                <i class="bi bi-receipt me-1"></i> All Expenses
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentExpenses as $expense): ?>
                                <?php
                                    $expenseDateFormatted = function_exists('formatExpenseDate') 
                                        ? formatExpenseDate($expense['expense_date']) 
                                        : date('d-m-Y', strtotime($expense['expense_date']));
                                ?>
                                <tr>
                                    <!-- Date -->
                                    <td>
                                        <div class="d-flex align-items-center gap-2 text-nowrap">
                                            <i class="bi bi-calendar3 text-muted small"></i>
                                            <span class="fw-medium text-dark"><?php echo htmlspecialchars($expenseDateFormatted); ?></span>
                                        </div>
                                    </td>

                                    <!-- Expense Name -->
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
                                            <a href="view-expense.php?id=<?php echo (int)$expense['id']; ?>" 
                                               class="btn btn-light action-btn border" 
                                               title="View Details">
                                                <i class="bi bi-eye text-primary"></i>
                                            </a>
                                            <a href="edit-expense.php?id=<?php echo (int)$expense['id']; ?>" 
                                               class="btn btn-light action-btn border" 
                                               title="Edit Expense">
                                                <i class="bi bi-pencil text-primary"></i>
                                            </a>
                                            <form method="POST" 
                                                  action="index.php" 
                                                  class="d-inline" 
                                                  onsubmit="return confirm('Are you sure you want to delete &quot;<?php echo htmlspecialchars(addslashes($expense['title'])); ?>&quot;?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="expense_id" value="<?php echo (int)$expense['id']; ?>">
                                                <button type="submit" 
                                                        class="btn btn-light action-btn border" 
                                                        title="Delete Expense">
                                                    <i class="bi bi-trash text-danger"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Table Footer Summary -->
            <div class="p-3 bg-light border-top d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2 small text-muted">
                <div>
                    Displaying latest 5 recorded transactions.
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span>All values in <strong>PKR</strong></span>
                    <a href="expenses.php" class="text-decoration-none fw-semibold">
                        View All Expenses &rarr;
                    </a>
                </div>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
