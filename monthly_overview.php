<?php
/**
 * Project: Expense Tracker
 * Page: Monthly Expense Overview (Step 10)
 * Database: tracker
 * Expenses Table: expenses
 * Categories Table: categories
 * Currency: PKR (Pakistani Rupees)
 */

require_once __DIR__ . '/config/db.php';
requireLogin();

$activePage = 'monthly_overview';

// 12 Standard Calendar Months (Requirement 3)
$monthsList = [
    1  => 'January',
    2  => 'February',
    3  => 'March',
    4  => 'April',
    5  => 'May',
    6  => 'June',
    7  => 'July',
    8  => 'August',
    9  => 'September',
    10 => 'October',
    11 => 'November',
    12 => 'December'
];

$currentMonth = (int)date('n'); // 1 to 12
$currentYear  = (int)date('Y');

// Requirement 4: Automatically select current month when page opens
$selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : $currentMonth;
if ($selectedMonth < 1 || $selectedMonth > 12) {
    $selectedMonth = $currentMonth;
}

$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : $currentYear;
if ($selectedYear < 2000 || $selectedYear > 2100) {
    $selectedYear = $currentYear;
}

$selectedMonthName = $monthsList[$selectedMonth];
$pageTitle = 'Monthly Overview - ' . $selectedMonthName . ' ' . $selectedYear;
$pageHeader = 'Monthly Expense Overview';

// Determine available years from tracker.expenses
try {
    $yearsStmt = $pdo->query("SELECT DISTINCT YEAR(expense_date) AS yr FROM expenses WHERE expense_date IS NOT NULL ORDER BY yr DESC");
    $availableYears = $yearsStmt ? $yearsStmt->fetchAll(PDO::FETCH_COLUMN) : [];
} catch (PDOException $e) {
    $availableYears = [];
}
if (!in_array($currentYear, $availableYears, true)) {
    $availableYears[] = $currentYear;
}
rsort($availableYears);

// -----------------------------------------------------------------------------
// Requirement 5 & 9: Summary Cards Calculations for Selected Month (PDO Prepared Statements)
// -----------------------------------------------------------------------------
try {
    $summaryStmt = $pdo->prepare("
        SELECT 
            COUNT(*) AS total_count,
            COALESCE(SUM(amount), 0) AS total_amount,
            COALESCE(MAX(amount), 0) AS highest_amount,
            COALESCE(AVG(amount), 0) AS average_amount
        FROM expenses
        WHERE YEAR(expense_date) = :year AND MONTH(expense_date) = :month
    ");
    $summaryStmt->execute([
        ':year'  => $selectedYear,
        ':month' => $selectedMonth
    ]);
    $summary = $summaryStmt->fetch();

    $totalExpensesCount = (int)$summary['total_count'];
    $totalMonthlyAmount = (float)$summary['total_amount'];
    $highestExpense     = (float)$summary['highest_amount'];
    $averageExpense     = (float)$summary['average_amount'];

} catch (PDOException $e) {
    die("Database Error: " . htmlspecialchars($e->getMessage()));
}

// -----------------------------------------------------------------------------
// Requirement 6 & 9: Daily Expense Aggregation for Selected Month
// -----------------------------------------------------------------------------
try {
    $dailyStmt = $pdo->prepare("
        SELECT 
            expense_date,
            COUNT(*) AS expense_count,
            SUM(amount) AS total_amount
        FROM expenses
        WHERE YEAR(expense_date) = :year AND MONTH(expense_date) = :month
        GROUP BY expense_date
        ORDER BY expense_date DESC
    ");
    $dailyStmt->execute([
        ':year'  => $selectedYear,
        ':month' => $selectedMonth
    ]);
    $dailyExpenses = $dailyStmt->fetchAll();

} catch (PDOException $e) {
    $dailyExpenses = [];
}

// -----------------------------------------------------------------------------
// Step 12: Complete Expense Details for Selected Month (PDO Prepared Statements)
// -----------------------------------------------------------------------------
try {
    $detailsStmt = $pdo->prepare("
        SELECT 
            id, 
            title, 
            amount, 
            payment_method, 
            expense_date, 
            notes
        FROM expenses
        WHERE YEAR(expense_date) = :year AND MONTH(expense_date) = :month
        ORDER BY expense_date DESC, id DESC
    ");
    $detailsStmt->execute([
        ':year'  => $selectedYear,
        ':month' => $selectedMonth
    ]);
    $expenseDetails = $detailsStmt->fetchAll();
} catch (PDOException $e) {
    $expenseDetails = [];
}

$appName = function_exists('getSetting') ? getSetting('app_name', 'MUSA / 3465340082') : 'MUSA / 3465340082';
$pngExportUrl = 'monthly_overview.php?month=' . $selectedMonth . '&year=' . $selectedYear . '&export=png';

// -----------------------------------------------------------------------------
// Date-by-Date Expense Calculation for Monthly Bill (Print & PNG Export)
// -----------------------------------------------------------------------------
$daysInMonth = (int)date('t', strtotime(sprintf('%04d-%02d-01', $selectedYear, $selectedMonth)));

try {
    $billItemsStmt = $pdo->prepare("
        SELECT 
            id, 
            title, 
            amount, 
            expense_date, 
            DAY(expense_date) AS day_num
        FROM expenses
        WHERE YEAR(expense_date) = :year AND MONTH(expense_date) = :month
        ORDER BY expense_date ASC, id ASC
    ");
    $billItemsStmt->execute([
        ':year'  => $selectedYear,
        ':month' => $selectedMonth
    ]);
    $rawBillExpenses = $billItemsStmt->fetchAll();
} catch (PDOException $e) {
    $rawBillExpenses = [];
}

$expensesByDay = [];
for ($d = 1; $d <= $daysInMonth; $d++) {
    $expensesByDay[$d] = [];
}

$monthlyTotal = 0.0;
foreach ($rawBillExpenses as $item) {
    $d = (int)$item['day_num'];
    $amt = (float)$item['amount'];
    $monthlyTotal += $amt;
    $expensesByDay[$d][] = [
        'id'               => $item['id'],
        'title'            => $item['title'],
        'amount'           => $amt,
        'formatted_amount' => 'PKR ' . (fmod($amt, 1) == 0 ? number_format($amt, 0) : number_format($amt, 2))
    ];
}

$dateByDateBill = [];
for ($d = 1; $d <= $daysInMonth; $d++) {
    $dateStr = sprintf('%02d-%02d-%04d', $d, $selectedMonth, $selectedYear);
    $items = isset($expensesByDay[$d]) ? $expensesByDay[$d] : [];
    $dailyTotal = 0.0;
    foreach ($items as $it) {
        $dailyTotal += $it['amount'];
    }
    $dailyTotalFormatted = ($dailyTotal > 0) ? ('PKR ' . (fmod($dailyTotal, 1) == 0 ? number_format($dailyTotal, 0) : number_format($dailyTotal, 2))) : 'PKR 0';
    $dateByDateBill[] = [
        'day'                   => $d,
        'date_string'           => $dateStr,
        'items'                 => $items,
        'daily_total'           => $dailyTotal,
        'daily_total_formatted' => $dailyTotalFormatted,
        'has_expenses'          => !empty($items)
    ];
}

$monthlyTotalDisplay = ($monthlyTotal > 0) ? ('PKR ' . (fmod($monthlyTotal, 1) == 0 ? number_format($monthlyTotal, 0) : number_format($monthlyTotal, 2))) : 'PKR 0';

// -----------------------------------------------------------------------------
// Handle Monthly Bill PNG Image Export (Date-by-Date Report)
// -----------------------------------------------------------------------------
if ((isset($_GET['export']) && strtolower($_GET['export']) === 'png') || (isset($_GET['action']) && strtolower($_GET['action']) === 'export_png')) {
    if (ob_get_level()) {
        ob_end_clean();
    }

    $fontRegular = __DIR__ . '/vendor/dompdf/vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf';
    $fontBold = __DIR__ . '/vendor/dompdf/vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf';
    if (!file_exists($fontRegular)) {
        $fontRegular = 'C:/Windows/Fonts/arial.ttf';
        $fontBold = 'C:/Windows/Fonts/arialbd.ttf';
    }

    $imgWidth = 720;
    $topHeaderHeight = 90;
    $bottomHeight = 90;

    $contentHeight = 0;
    foreach ($dateByDateBill as $dBlock) {
        $rowsCount = max(1, count($dBlock['items']));
        $contentHeight += 28 + 24 + ($rowsCount * 22) + 24 + 18;
    }

    $imgHeight = $topHeaderHeight + $contentHeight + $bottomHeight;

    $im = imagecreatetruecolor($imgWidth, $imgHeight);
    $white = imagecolorallocate($im, 255, 255, 255);
    $black = imagecolorallocate($im, 0, 0, 0);
    $gray = imagecolorallocate($im, 100, 100, 100);
    $lightGray = imagecolorallocate($im, 230, 230, 230);

    // Clean white background
    imagefilledrectangle($im, 0, 0, $imgWidth, $imgHeight, $white);

    // Bill Title: MONTHLY EXPENSE BILL
    $titleText = "MONTHLY EXPENSE BILL";
    $subtitleText = "Month: " . $selectedMonthName . " " . $selectedYear;
    if (file_exists($fontBold) && function_exists('imagettftext')) {
        imagettftext($im, 17, 0, 40, 42, $black, $fontBold, $titleText);
        imagettftext($im, 12, 0, 40, 70, $black, $fontRegular, $subtitleText);
    } else {
        imagestring($im, 5, 40, 22, $titleText, $black);
        imagestring($im, 4, 40, 52, $subtitleText, $black);
    }

    imageline($im, 40, 82, $imgWidth - 40, 82, $black);

    $currY = 100;
    $tableLeft = 40;
    $tableRight = $imgWidth - 40;

    foreach ($dateByDateBill as $dBlock) {
        // Date Header: ## DATE: DD-MM-YYYY
        $dateTitle = "## DATE: " . $dBlock['date_string'];
        if (file_exists($fontBold) && function_exists('imagettftext')) {
            imagettftext($im, 11, 0, $tableLeft, $currY + 14, $black, $fontBold, $dateTitle);
        } else {
            imagestring($im, 4, $tableLeft, $currY, $dateTitle, $black);
        }
        $currY += 26;

        // Table Header
        if (file_exists($fontBold) && function_exists('imagettftext')) {
            imagettftext($im, 9.5, 0, $tableLeft + 8, $currY + 14, $black, $fontBold, "Product / Expense Name");
            $bbox = imagettfbbox(9.5, 0, $fontBold, "Amount");
            $textWidth = abs($bbox[4] - $bbox[0]);
            imagettftext($im, 9.5, 0, $tableRight - $textWidth - 8, $currY + 14, $black, $fontBold, "Amount");
        } else {
            imagestring($im, 3, $tableLeft + 8, $currY + 2, "Product / Expense Name", $black);
            imagestring($im, 3, $tableRight - 70, $currY + 2, "Amount", $black);
        }
        $currY += 18;
        imageline($im, $tableLeft, $currY, $tableRight, $currY, $black);
        $currY += 4;

        // Items
        if (!empty($dBlock['items'])) {
            foreach ($dBlock['items'] as $it) {
                $title = $it['title'];
                if (strlen($title) > 55) {
                    $title = substr($title, 0, 52) . '...';
                }
                $amtStr = $it['formatted_amount'];
                if (file_exists($fontRegular) && function_exists('imagettftext')) {
                    imagettftext($im, 9.5, 0, $tableLeft + 8, $currY + 14, $black, $fontRegular, $title);
                    $bbox = imagettfbbox(9.5, 0, $fontRegular, $amtStr);
                    $textWidth = abs($bbox[4] - $bbox[0]);
                    imagettftext($im, 9.5, 0, $tableRight - $textWidth - 8, $currY + 14, $black, $fontRegular, $amtStr);
                } else {
                    imagestring($im, 3, $tableLeft + 8, $currY + 2, $title, $black);
                    imagestring($im, 3, $tableRight - 100, $currY + 2, $amtStr, $black);
                }
                $currY += 20;
            }
        } else {
            if (file_exists($fontRegular) && function_exists('imagettftext')) {
                imagettftext($im, 9.5, 0, $tableLeft + 8, $currY + 14, $black, $fontRegular, "No expenses");
                $bbox = imagettfbbox(9.5, 0, $fontRegular, "PKR 0");
                $textWidth = abs($bbox[4] - $bbox[0]);
                imagettftext($im, 9.5, 0, $tableRight - $textWidth - 8, $currY + 14, $black, $fontRegular, "PKR 0");
            } else {
                imagestring($im, 3, $tableLeft + 8, $currY + 2, "No expenses", $black);
                imagestring($im, 3, $tableRight - 60, $currY + 2, "PKR 0", $black);
            }
            $currY += 20;
        }

        // Line before Daily Total
        imageline($im, $tableLeft, $currY, $tableRight, $currY, $black);
        $currY += 4;

        // Daily Total
        $totalVal = $dBlock['daily_total_formatted'];
        if (file_exists($fontBold) && function_exists('imagettftext')) {
            imagettftext($im, 10, 0, $tableLeft + 8, $currY + 14, $black, $fontBold, "Daily Total:");
            $bbox = imagettfbbox(10, 0, $fontBold, $totalVal);
            $textWidth = abs($bbox[4] - $bbox[0]);
            imagettftext($im, 10, 0, $tableRight - $textWidth - 8, $currY + 14, $black, $fontBold, $totalVal);
        } else {
            imagestring($im, 4, $tableLeft + 8, $currY + 2, "Daily Total:", $black);
            imagestring($im, 4, $tableRight - 120, $currY + 2, $totalVal, $black);
        }
        $currY += 22;

        // Divider after date block
        imageline($im, $tableLeft, $currY, $tableRight, $currY, $black);
        $currY += 18;
    }

    // Monthly Total Section
    $currY += 10;
    imageline($im, $tableLeft, $currY, $tableRight, $currY, $black);
    imageline($im, $tableLeft, $currY + 2, $tableRight, $currY + 2, $black);
    $currY += 10;

    $monthlyTitle = "## MONTHLY TOTAL:";
    if (file_exists($fontBold) && function_exists('imagettftext')) {
        imagettftext($im, 12, 0, $tableLeft, $currY + 16, $black, $fontBold, $monthlyTitle);
        $bbox = imagettfbbox(12, 0, $fontBold, $monthlyTotalDisplay);
        $textWidth = abs($bbox[4] - $bbox[0]);
        imagettftext($im, 12, 0, $tableRight - $textWidth, $currY + 16, $black, $fontBold, $monthlyTotalDisplay);
    } else {
        imagestring($im, 4, $tableLeft, $currY + 2, $monthlyTitle, $black);
        imagestring($im, 4, $tableRight - 140, $currY + 2, $monthlyTotalDisplay, $black);
    }

    $currY += 30;
    imageline($im, $tableLeft, $currY, $tableRight, $currY, $black);

    $pngFilename = sprintf('monthly_expenses_%04d-%02d.png', $selectedYear, $selectedMonth);

    header('Content-Type: image/png');
    header('Content-Disposition: attachment; filename="' . $pngFilename . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    imagepng($im);
    imagedestroy($im);
    exit;
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>


<div class="main-content">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <main class="p-3 p-md-4">
        <!-- Top Breadcrumb & Heading -->
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-4 gap-2 no-print">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1 small text-muted">
                        <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="expenses.php" class="text-decoration-none">All Expenses</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Monthly Overview</li>
                    </ol>
                </nav>
                <h4 class="fw-bold mb-0 text-slate-800">
                    <i class="bi bi-calendar-month-fill text-primary me-2"></i>Monthly Expense Overview
                </h4>
                <p class="text-muted small mb-0">
                    Review and analyze your expenditures for <?php echo htmlspecialchars($selectedMonthName . ' ' . $selectedYear); ?>.
                </p>
            </div>
        </div>

        <!-- Print-Only Styling: Print ONLY Complete Expense Details Section -->
        <style>
            @media print {
                @page {
                    size: auto;
                    margin: 12mm 15mm;
                }

                html, body {
                    background: #ffffff !important;
                    color: #0f172a !important;
                    font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif !important;
                    margin: 0 !important;
                    padding: 0 !important;
                    width: 100% !important;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }

                /* Hide top nav, sidebar, footer, breadcrumbs, buttons, forms, alert messages */
                .sidebar,
                .sidebar-overlay,
                .top-navbar,
                header.top-navbar,
                .main-content .top-navbar,
                .main-content > .top-navbar,
                .main-content > header,
                .navbar,
                nav,
                header,
                footer,
                .breadcrumb,
                .no-print,
                .btn,
                button,
                form,
                #monthSelectorForm,
                .alert,
                #exportReportWatermarkHeader,
                #monthlyBillPrintArea {
                    display: none !important;
                }

                .main-content > *:not(main) {
                    display: none !important;
                }

                /* Ensure parent containers display without extra paddings or margins */
                .app-wrapper,
                .main-content,
                main,
                .monthly-report-export-area,
                #monthlyReportExportArea {
                    display: block !important;
                    position: static !important;
                    margin: 0 !important;
                    padding: 0 !important;
                    width: 100% !important;
                    max-width: 100% !important;
                    background: transparent !important;
                    border: none !important;
                    box-shadow: none !important;
                }

                /* Hide all direct siblings in main except the report container */
                main > *:not(.monthly-report-export-area):not(#monthlyReportExportArea):not(#completeExpenseDetailsSection) {
                    display: none !important;
                }

                /* Hide all siblings inside the report container except Complete Expense Details */
                .monthly-report-export-area > *:not(#completeExpenseDetailsSection):not(.complete-expense-details-section),
                #monthlyReportExportArea > *:not(#completeExpenseDetailsSection):not(.complete-expense-details-section) {
                    display: none !important;
                }

                /* Hide summary cards, charts, and any other content card */
                .summary-card,
                .chart-container-wrapper,
                canvas,
                .content-card:not(#completeExpenseDetailsSection):not(.complete-expense-details-section) {
                    display: none !important;
                }

                /* Display ONLY Complete Expense Details Section */
                #completeExpenseDetailsSection,
                .complete-expense-details-section {
                    display: block !important;
                    visibility: visible !important;
                    width: 100% !important;
                    margin: 0 !important;
                    padding: 0 !important;
                    background: #ffffff !important;
                    border: 1px solid #cbd5e1 !important;
                    box-shadow: none !important;
                    page-break-inside: auto !important;
                    break-inside: auto !important;
                    overflow: visible !important;
                }

                #completeExpenseDetailsSection * {
                    visibility: visible !important;
                }

                /* Ensure buttons/actions inside Complete Expense Details are hidden */
                #completeExpenseDetailsSection .btn,
                #completeExpenseDetailsSection button,
                #completeExpenseDetailsSection .no-print {
                    display: none !important;
                    visibility: hidden !important;
                }

                /* Card header in print */
                #completeExpenseDetailsSection .card-header {
                    background-color: #f8fafc !important;
                    border-bottom: 2px solid #0f172a !important;
                    padding: 12px 16px !important;
                    display: flex !important;
                    justify-content: space-between !important;
                    align-items: center !important;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }

                #completeExpenseDetailsSection .card-header h6 {
                    font-size: 13pt !important;
                    font-weight: 700 !important;
                    color: #0f172a !important;
                    margin: 0 !important;
                }

                /* Enable multi-page printing without table clipping */
                #completeExpenseDetailsSection .table-responsive {
                    display: block !important;
                    overflow: visible !important;
                    width: 100% !important;
                }

                #completeExpenseDetailsSection table.table {
                    width: 100% !important;
                    border-collapse: collapse !important;
                    page-break-inside: auto !important;
                    break-inside: auto !important;
                }

                /* Repeat table header on every printed page */
                #completeExpenseDetailsSection thead {
                    display: table-header-group !important;
                }

                #completeExpenseDetailsSection tfoot {
                    display: table-footer-group !important;
                }

                /* Prevent split table rows across page breaks */
                #completeExpenseDetailsSection tr {
                    page-break-inside: avoid !important;
                    break-inside: avoid !important;
                }

                #completeExpenseDetailsSection th {
                    background-color: #f1f5f9 !important;
                    color: #0f172a !important;
                    font-size: 10pt !important;
                    font-weight: 700 !important;
                    border-bottom: 1.5px solid #cbd5e1 !important;
                    padding: 8px 10px !important;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }

                #completeExpenseDetailsSection td {
                    font-size: 9.5pt !important;
                    color: #1e293b !important;
                    border-bottom: 1px solid #e2e8f0 !important;
                    padding: 7px 10px !important;
                    vertical-align: middle !important;
                }

                /* Badges styling */
                #completeExpenseDetailsSection .badge {
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                    border: 1px solid rgba(0, 0, 0, 0.1) !important;
                }

                #completeExpenseDetailsSection tfoot tr td {
                    font-weight: 700 !important;
                    font-size: 10pt !important;
                    background-color: #f8fafc !important;
                    border-top: 2px solid #cbd5e1 !important;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }
            }
        </style>

        <!-- Requirement 3 & 4: Month Selector at Top of Page -->
        <div class="content-card mb-4 p-3 p-md-4">
            <form method="GET" action="monthly_overview.php" id="monthSelectorForm" class="row g-3 align-items-end">
                <div class="col-12 col-md-4 col-lg-3">
                    <label for="monthSelect" class="form-label fw-semibold text-dark small mb-1">
                        <i class="bi bi-calendar-event text-primary me-1"></i>Select Month
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-calendar3"></i></span>
                        <!-- Requirement 3: 12 Calendar Months -->
                        <select name="month" id="monthSelect" class="form-select fw-semibold" onchange="this.form.submit()">
                            <?php foreach ($monthsList as $num => $name): ?>
                                <option value="<?php echo $num; ?>" <?php echo ($selectedMonth === $num) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($name); ?>
                                    <?php echo ($num === $currentMonth && $selectedYear === $currentYear) ? ' (Current)' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="col-12 col-md-3 col-lg-3">
                    <label for="yearSelect" class="form-label fw-semibold text-dark small mb-1">
                        <i class="bi bi-clock-history text-primary me-1"></i>Select Year
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-calendar-check"></i></span>
                        <select name="year" id="yearSelect" class="form-select fw-semibold" onchange="this.form.submit()">
                            <?php foreach ($availableYears as $yr): ?>
                                <option value="<?php echo (int)$yr; ?>" <?php echo ($selectedYear === (int)$yr) ? 'selected' : ''; ?>>
                                    <?php echo (int)$yr; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="col-12 col-md-2 col-lg-2">
                    <button type="submit" class="btn btn-primary w-100 d-inline-flex align-items-center justify-content-center gap-1 shadow-sm">
                        <i class="bi bi-funnel"></i>
                        <span>Apply</span>
                    </button>
                </div>

                <div class="col-12 col-md-3 col-lg-4 text-md-end d-flex align-items-center justify-content-md-end gap-2 flex-wrap">
                    <span class="badge bg-light text-secondary border px-3 py-2 small">
                        Currency: <strong class="text-dark">PKR</strong>
                    </span>
                    <button type="button" onclick="window.print();" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 shadow-sm" title="Print Complete Expense Details">
                        <i class="bi bi-printer"></i>
                        <span>Print</span>
                    </button>
                    <a href="<?php echo htmlspecialchars($pngExportUrl); ?>" class="btn btn-sm btn-outline-success d-inline-flex align-items-center gap-1 shadow-sm" title="Download Monthly Expenses as Image (PNG)">
                        <i class="bi bi-file-earmark-image"></i>
                        <span>PNG</span>
                    </a>
                </div>
            </form>
        </div>

        <!-- Step 12: Report Export Area (Captured by html2canvas for Image Download) -->
        <div id="monthlyReportExportArea" class="monthly-report-export-area">
            <!-- Dedicated Report Header (Displayed in Exported Image) -->
            <div id="exportReportWatermarkHeader" class="p-3 mb-4 bg-white border rounded shadow-sm" style="display: none;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="fw-bold mb-1 text-primary">
                            <i class="bi bi-wallet2 me-2"></i><?php echo htmlspecialchars($appName); ?>
                        </h4>
                        <div class="text-secondary fw-semibold">
                            Monthly Expense Report &mdash; <?php echo htmlspecialchars($selectedMonthName . ' ' . $selectedYear); ?>
                        </div>
                    </div>
                    <div class="text-end small">
                        <div><span class="text-muted">Generated Date:</span> <strong><?php echo date('d-m-Y h:i A'); ?></strong></div>
                        <div><span class="text-muted">Currency:</span> <strong>PKR</strong></div>
                        <div class="mt-1"><span class="badge bg-danger fs-6 px-3 py-1">Total: <?php echo formatPKR($totalMonthlyAmount); ?></span></div>
                    </div>
                </div>
            </div>

        <?php if ($totalExpensesCount === 0): ?>
            <!-- Requirement 12: Prominent "No expenses found for this month." message -->
            <div class="alert alert-info d-flex align-items-center gap-3 p-3 p-md-4 mb-4 shadow-sm border-0" role="alert" style="background-color: #eff6ff; border-left: 4px solid #3b82f6 !important; color: #1e40af;">
                <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px;">
                    <i class="bi bi-info-circle-fill fs-4"></i>
                </div>
                <div class="flex-grow-1">
                    <h5 class="mb-1 fw-bold">No expenses found for this month.</h5>
                    <p class="mb-0 small text-secondary">
                        No transactions have been recorded for <strong><?php echo htmlspecialchars($selectedMonthName . ' ' . $selectedYear); ?></strong> in the tracker database.
                    </p>
                </div>
                <div class="d-none d-md-block">
                    <a href="add-expense.php" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1">
                        <i class="bi bi-plus-lg"></i>
                        <span>Add New Expense</span>
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Requirement 5: Summary Cards for Selected Month (Responsive on Desktop, Tablet & Mobile) -->
        <div class="row g-3 mb-4">
            <!-- 1. Total Monthly Expenses -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="summary-card card-total">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="card-title-text">Total Monthly Expenses</div>
                            <!-- Requirement 11: Display in PKR -->
                            <div class="card-value text-danger">
                                <?php echo formatPKR($totalMonthlyAmount); ?>
                            </div>
                            <div class="card-subtitle"><?php echo htmlspecialchars($selectedMonthName . ' ' . $selectedYear); ?></div>
                        </div>
                        <div class="card-icon-wrapper">
                            <i class="bi bi-wallet2"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Number of Expenses -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="summary-card card-count">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="card-title-text">Number of Expenses</div>
                            <div class="card-value text-dark">
                                <?php echo number_format($totalExpensesCount); ?>
                            </div>
                            <div class="card-subtitle">Recorded transactions</div>
                        </div>
                        <div class="card-icon-wrapper">
                            <i class="bi bi-receipt"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Highest Expense -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="summary-card card-today">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="card-title-text">Highest Expense</div>
                            <!-- Requirement 11: Display in PKR -->
                            <div class="card-value text-warning-emphasis">
                                <?php echo formatPKR($highestExpense); ?>
                            </div>
                            <div class="card-subtitle">Peak single expenditure</div>
                        </div>
                        <div class="card-icon-wrapper">
                            <i class="bi bi-arrow-up-circle"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. Average Expense -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="summary-card card-month">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="card-title-text">Average Expense</div>
                            <!-- Requirement 11: Display in PKR -->
                            <div class="card-value text-success">
                                <?php echo formatPKR($averageExpense); ?>
                            </div>
                            <div class="card-subtitle">Per transaction average</div>
                        </div>
                        <div class="card-icon-wrapper">
                            <i class="bi bi-calculator"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>



        <!-- Requirement 6: Daily Expense Table for Selected Month -->
        

        <!-- Step 12: Complete Expense Details Table for Selected Month -->
        <div id="completeExpenseDetailsSection" class="content-card mb-4 complete-expense-details-section">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-receipt-cutoff text-primary"></i>
                    <h6 class="mb-0 fw-bold">Complete Expense Details <span class="d-none d-print-inline text-muted fw-normal small">(<?php echo htmlspecialchars($selectedMonthName . ' ' . $selectedYear); ?>)</span></h6>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-light text-secondary border">
                        <?php echo number_format($totalExpensesCount); ?> Total Expense<?php echo ($totalExpensesCount === 1) ? '' : 's'; ?>
                    </span>
                    <button type="button" onclick="window.print();" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm no-print" title="Print Complete Expense Details">
                        <i class="bi bi-printer"></i>
                        <span>Print</span>
                    </button>
                     <a href="<?php echo htmlspecialchars($pngExportUrl); ?>" class="btn btn-outline-success btn-sm d-inline-flex align-items-center gap-1 shadow-sm" id="btnDownloadImage" title="Download Monthly Expenses as PNG">
                    <i class="bi bi-file-earmark-image-fill"></i>
                    <span id="btnDownloadImageText">Download PNG</span>
                </a>
                </div>
            </div>

            <?php if (empty($expenseDetails)): ?>
                <!-- Empty state for complete expense details -->
                <div class="p-5 text-center text-muted">
                    <div class="badge bg-light text-secondary p-3 rounded-circle mb-3 fs-3">
                        <i class="bi bi-inbox"></i>
                    </div>
                    <h6 class="fw-bold mb-1 text-secondary">No expenses found for this month.</h6>
                    <p class="small text-muted mb-0">No transaction records exist for <?php echo htmlspecialchars($selectedMonthName . ' ' . $selectedYear); ?>.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th style="width: 140px;">Date</th>
                                <th>Expense Title</th>
                                <th style="width: 160px;">Payment Method</th>
                                <th class="text-end" style="width: 160px;">Amount (PKR)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expenseDetails as $idx => $exp): ?>
                                <?php
                                    $rawExpDate = $exp['expense_date'];
                                    $formattedExpDate = function_exists('formatExpenseDate') 
                                        ? formatExpenseDate($rawExpDate) 
                                        : date('d M Y', strtotime($rawExpDate));
                                    $pm       = !empty($exp['payment_method']) ? $exp['payment_method'] : 'Cash';
                                    $amt      = (float)$exp['amount'];
                                ?>
                                <tr>
                                    <td class="text-muted small"><?php echo $idx + 1; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi bi-calendar-event text-muted"></i>
                                            <span class="fw-medium text-dark"><?php echo htmlspecialchars($formattedExpDate); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-slate-800 text-break">
                                            <?php echo htmlspecialchars($exp['title']); ?>
                                        </div>
                                        <?php if (!empty($exp['notes'])): ?>
                                            <div class="small text-muted text-break"><?php echo htmlspecialchars($exp['notes']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <i class="bi bi-credit-card-2-front me-1 text-muted"></i>
                                            <?php echo htmlspecialchars($pm); ?>
                                        </span>
                                    </td>
                                    <td class="text-end fw-bold text-danger">
                                        <?php echo formatPKR($amt); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light border-top">
                            <tr class="fw-bold">
                                <td colspan="4" class="text-end">Total Monthly Expenses:</td>
                                <td class="text-end text-danger"><?php echo formatPKR($totalMonthlyAmount); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div> <!-- / #monthlyReportExportArea -->
    </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
