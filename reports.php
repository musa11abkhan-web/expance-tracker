<?php
/**
 * Project: Expense Tracker
 * Page: Reports & Expense Summary (Step 5)
 * Currency: PKR (Pakistani Rupees)
 */

require_once __DIR__ . '/config/db.php';
requireLogin();
global $pdo;

$pageTitle = 'Reports & Expense Summary';
$pageHeader = 'Reports & Expense Summary';
$activePage = 'reports';

// Payment Methods Supported
$paymentMethods = [
    'Cash',
    'Bank',
    'Easypaisa',
    'JazzCash',
    'Other'
];

// -----------------------------------------------------------------------------
// 2. Fetch Fixed Benchmark Summary Cards (Directly from expenses table via PDO)
// -----------------------------------------------------------------------------
try {
    // Today's Expenses
    $stmtToday = $pdo->query("
        SELECT COALESCE(SUM(amount), 0) AS today_sum, COUNT(*) AS today_count
        FROM expenses 
        WHERE expense_date = CURRENT_DATE()
    ");
    $todaySummary = $stmtToday->fetch();
    $todayExpenses = (float)$todaySummary['today_sum'];
    $todayCount = (int)$todaySummary['today_count'];

    // This Week's Expenses (Mode 1: Monday as first day of week)
    $stmtWeek = $pdo->query("
        SELECT COALESCE(SUM(amount), 0) AS week_sum, COUNT(*) AS week_count
        FROM expenses 
        WHERE YEARWEEK(expense_date, 1) = YEARWEEK(CURRENT_DATE(), 1)
    ");
    $weekSummary = $stmtWeek->fetch();
    $thisWeekExpenses = (float)$weekSummary['week_sum'];
    $thisWeekCount = (int)$weekSummary['week_count'];

    // This Month's Expenses
    $stmtMonth = $pdo->query("
        SELECT COALESCE(SUM(amount), 0) AS month_sum, COUNT(*) AS month_count
        FROM expenses 
        WHERE YEAR(expense_date) = YEAR(CURRENT_DATE()) 
          AND MONTH(expense_date) = MONTH(CURRENT_DATE())
    ");
    $monthSummary = $stmtMonth->fetch();
    $thisMonthExpenses = (float)$monthSummary['month_sum'];
    $thisMonthCount = (int)$monthSummary['month_count'];

    // Total Expenses (Lifetime)
    $stmtTotal = $pdo->query("
        SELECT COALESCE(SUM(amount), 0) AS total_sum, COUNT(*) AS total_count
        FROM expenses
    ");
    $totalSummary = $stmtTotal->fetch();
    $totalExpenses = (float)$totalSummary['total_sum'];
    $totalCount = (int)$totalSummary['total_count'];

} catch (PDOException $e) {
    die("Database Query Error: " . htmlspecialchars($e->getMessage()));
}

// -----------------------------------------------------------------------------
// 3. Process Filters (Date Range, Category, Payment Method)
// -----------------------------------------------------------------------------
$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
$paymentMethod = isset($_GET['payment_method']) ? trim($_GET['payment_method']) : '';

$whereClauses = [];
$queryParams = [];
$activeFilterDescriptions = [];

// Validate and apply Start Date
if ($startDate !== '') {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) && strtotime($startDate)) {
        $whereClauses[] = "expense_date >= :start_date";
        $queryParams[':start_date'] = $startDate;
        $activeFilterDescriptions[] = "From: " . date('d M Y', strtotime($startDate));
    } else {
        $startDate = '';
    }
}

// Validate and apply End Date
if ($endDate !== '') {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate) && strtotime($endDate)) {
        $whereClauses[] = "expense_date <= :end_date";
        $queryParams[':end_date'] = $endDate;
        $activeFilterDescriptions[] = "To: " . date('d M Y', strtotime($endDate));
    } else {
        $endDate = '';
    }
}

// Validate and apply Payment Method Filter
if ($paymentMethod !== '' && in_array($paymentMethod, $paymentMethods, true)) {
    $whereClauses[] = "payment_method = :payment_method";
    $queryParams[':payment_method'] = $paymentMethod;
    $activeFilterDescriptions[] = "Payment: " . $paymentMethod;
} else {
    $paymentMethod = '';
}

$hasActiveFilters = !empty($activeFilterDescriptions);
$whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

// -----------------------------------------------------------------------------
// 4. Fetch Filtered Calculations & Summaries via PDO Prepared Statements
// -----------------------------------------------------------------------------
try {
    // 4.1 Filtered Totals
    $stmtFilteredTotal = $pdo->prepare("
        SELECT 
            COUNT(id) AS filtered_count,
            COALESCE(SUM(amount), 0) AS filtered_amount,
            COALESCE(AVG(amount), 0) AS filtered_avg
        FROM expenses
        {$whereSql}
    ");
    $stmtFilteredTotal->execute($queryParams);
    $filteredStats = $stmtFilteredTotal->fetch();
    $filteredCount = (int)$filteredStats['filtered_count'];
    $filteredAmount = (float)$filteredStats['filtered_amount'];
    $filteredAvg = (float)$filteredStats['filtered_avg'];

    // 4.2 Daily Expense Summary
    $stmtDailySummary = $pdo->prepare("
        SELECT 
            expense_date,
            COUNT(id) AS expense_count,
            COALESCE(SUM(amount), 0) AS total_amount
        FROM expenses
        {$whereSql}
        GROUP BY expense_date
        ORDER BY expense_date DESC
    ");
    $stmtDailySummary->execute($queryParams);
    $dailySummaries = $stmtDailySummary->fetchAll();

} catch (PDOException $e) {
    die("Database Query Error: " . htmlspecialchars($e->getMessage()));
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Main Content Area -->
<div class="main-content">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <main class="p-3 p-md-4">

        <!-- Print-Only Header (Appears only when printing report) -->
        <div class="d-none d-print-block print-header">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div>
                    <h3 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars(getSetting('app_name', 'MUSA / 3465340082')); ?> — Financial Summary Report</h3>
                    <small class="text-muted">Official Expense Summary &amp; Analytics in Pakistani Rupees (PKR)</small>
                </div>
                <div class="text-end">
                    <small class="text-muted d-block">Generated On:</small>
                    <strong><?php echo date('d M Y, h:i A'); ?></strong>
                </div>
            </div>
            <div class="p-2 bg-light border rounded small mt-2">
                <strong>Report Scope:</strong> 
                <?php if ($hasActiveFilters): ?>
                    <?php echo htmlspecialchars(implode(' | ', $activeFilterDescriptions)); ?>
                <?php else: ?>
                    All Records (Lifetime)
                <?php endif; ?>
                &nbsp;|&nbsp; <strong>Total Filtered:</strong> <?php echo formatPKR($filteredAmount); ?> (<?php echo $filteredCount; ?> expenses)
            </div>
        </div>

        <!-- Top Header & Action Controls -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3 no-print">
            <div>
                <h4 class="fw-bold mb-1 text-slate-800">Reports & Expense Summary</h4>
                <p class="text-muted mb-0 small">Comprehensive expense analytics and daily breakdown in PKR.</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" onclick="window.print();" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1 shadow-sm">
                    <i class="bi bi-printer"></i>
                    <span>Print Report</span>
                </button>
                <a href="add-expense.php" class="btn btn-primary d-flex align-items-center gap-1 shadow-sm">
                    <i class="bi bi-plus-lg"></i>
                    <span class="d-none d-sm-inline">Add Expense</span>
                </a>
            </div>
        </div>

        <!-- Requirement 2: Summary Cards (Today, This Week, This Month, Total) -->
        <div class="row g-3 g-md-4 mb-4">
            <!-- Card 1: Today's Expenses -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="summary-card card-today">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="card-title-text">Today's Expenses</div>
                            <div class="card-value"><?php echo formatPKR($todayExpenses); ?></div>
                            <div class="card-subtitle"><?php echo date('d M Y'); ?> &bull; <?php echo $todayCount; ?> recorded</div>
                        </div>
                        <div class="card-icon-wrapper">
                            <i class="bi bi-clock-history"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 2: This Week's Expenses -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="summary-card card-month">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="card-title-text">This Week's Expenses</div>
                            <div class="card-value"><?php echo formatPKR($thisWeekExpenses); ?></div>
                            <div class="card-subtitle">Current week &bull; <?php echo $thisWeekCount; ?> recorded</div>
                        </div>
                        <div class="card-icon-wrapper">
                            <i class="bi bi-calendar-week"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 3: This Month's Expenses -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="summary-card card-month">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="card-title-text">This Month's Expenses</div>
                            <div class="card-value"><?php echo formatPKR($thisMonthExpenses); ?></div>
                            <div class="card-subtitle"><?php echo date('F Y'); ?> &bull; <?php echo $thisMonthCount; ?> recorded</div>
                        </div>
                        <div class="card-icon-wrapper">
                            <i class="bi bi-calendar-month"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 4: Total Expenses -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="summary-card card-total">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="card-title-text">Total Expenses</div>
                            <div class="card-value"><?php echo formatPKR($totalExpenses); ?></div>
                            <div class="card-subtitle">Lifetime spending &bull; <?php echo $totalCount; ?> recorded</div>
                        </div>
                        <div class="card-icon-wrapper">
                            <i class="bi bi-wallet2"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters Card (Date Range, Payment Method, Apply, Reset) -->
       

        <!-- Filtered Overview Summary Banner (Always visible on print and desktop) -->
        <div class="card border-0 shadow-sm bg-primary text-white mb-4 rounded-3 p-3 p-md-4">
            <div class="row g-3 align-items-center">
                <div class="col-12 col-md-4">
                    <div class="text-white-50 small text-uppercase fw-semibold letter-spacing">Current Report Total</div>
                    <div class="fs-2 fw-bold text-white"><?php echo formatPKR($filteredAmount); ?></div>
                    <div class="text-white-50 small">
                        <?php echo $hasActiveFilters ? 'Based on active filters' : 'Lifetime cumulative spending'; ?>
                    </div>
                </div>
                <div class="col-6 col-md-4 border-start border-white border-opacity-25 ps-md-4">
                    <div class="text-white-50 small text-uppercase fw-semibold">Total Transactions</div>
                    <div class="fs-3 fw-bold text-white"><?php echo number_format($filteredCount); ?></div>
                    <div class="text-white-50 small">Expenses recorded</div>
                </div>
                <div class="col-6 col-md-4 border-start border-white border-opacity-25 ps-md-4">
                    <div class="text-white-50 small text-uppercase fw-semibold">Average / Transaction</div>
                    <div class="fs-3 fw-bold text-white"><?php echo formatPKR($filteredAvg); ?></div>
                    <div class="text-white-50 small">Average expense amount</div>
                </div>
            </div>
        </div>



        <!-- Requirement 8: Daily Expense Summary -->
        <div class="content-card mb-4">
            <div class="card-header bg-white py-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-calendar-event-fill text-primary"></i>
                    <h6 class="mb-0 fw-bold">Daily Expense Summary</h6>
                </div>
                <span class="badge bg-light text-dark border small">
                    <?php echo count($dailySummaries); ?> Days Recorded
                </span>
            </div>

            <?php if (empty($dailySummaries)): ?>
                <!-- Requirement 13: Handle cases where there are no expenses -->
                <div class="p-5 text-center text-muted">
                    <div class="badge bg-light text-secondary p-3 rounded-circle mb-3 fs-3">
                        <i class="bi bi-calendar-x"></i>
                    </div>
                    <h5 class="fw-bold mb-1 text-secondary">No expenses found.</h5>
                    <p class="small text-muted mb-0">No expenses recorded for the selected date range or filter options.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Day of Week</th>
                                <th class="text-center">Number of Expenses</th>
                                <th class="text-end">Total Amount</th>
                                <th style="min-width: 140px;">Daily Share (%)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dailySummaries as $day): 
                                $dayAmount = (float)$day['total_amount'];
                                $dayCount = (int)$day['expense_count'];
                                $dayDate = $day['expense_date'];
                                $dayPercentage = ($filteredAmount > 0) ? round(($dayAmount / $filteredAmount) * 100, 1) : 0;
                                $isToday = ($dayDate === date('Y-m-d'));
                            ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi bi-calendar3 text-muted"></i>
                                            <span class="fw-semibold text-dark">
                                                <?php echo date('d M Y', strtotime($dayDate)); ?>
                                            </span>
                                            <?php if ($isToday): ?>
                                                <span class="badge bg-warning text-dark border px-2 py-0 small" style="font-size: 0.7rem;">Today</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="text-muted">
                                            <?php echo date('l', strtotime($dayDate)); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border px-2 py-1 fw-semibold">
                                            <?php echo number_format($dayCount); ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <span class="amount-text text-dark">
                                            <?php echo formatPKR($dayAmount); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress flex-grow-1" style="height: 6px;">
                                                <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $dayPercentage; ?>%;" aria-valuenow="<?php echo $dayPercentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                            <small class="text-muted fw-semibold" style="min-width: 42px; text-align: right;">
                                                <?php echo $dayPercentage; ?>%
                                            </small>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-light fw-bold">
                                <td colspan="2">Total</td>
                                <td class="text-center"><?php echo number_format($filteredCount); ?></td>
                                <td class="text-end text-primary"><?php echo formatPKR($filteredAmount); ?></td>
                                <td>100.0%</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
