<?php
/**
 * Project: Expense Tracker
 * Page: Expense Details / View Expense
 * Currency: PKR (Pakistani Rupees)
 */

require_once __DIR__ . '/config/db.php';
requireLogin();

$activePage = 'expenses';

$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0);

$expense = null;
$notFound = false;

if ($id <= 0) {
    $notFound = true;
} else {
    try {
        $stmt = $pdo->prepare("
            SELECT *
            FROM expenses
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $expense = $stmt->fetch();

        if (!$expense) {
            $notFound = true;
        }
    } catch (PDOException $e) {
        error_log("View Expense Error: " . $e->getMessage());
        $notFound = true;
    }
}

if ($notFound || !$expense) {
    $pageTitle = 'Expense Not Found';
    $pageHeader = 'Expense Details';
} else {
    $pageTitle = 'Expense Details #' . $expense['id'];
    $pageHeader = 'Expense Details #' . $expense['id'];
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="main-content">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <main class="p-3 p-md-4">
        <?php if ($notFound || !$expense): ?>
            <div class="mb-4">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1 small text-muted">
                        <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="expenses.php" class="text-decoration-none">All Expenses</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Expense Details</li>
                    </ol>
                </nav>
                <h4 class="fw-bold mb-0 text-slate-800">Expense Details</h4>
            </div>

            <div class="row justify-content-center">
                <div class="col-12 col-md-8 col-lg-6">
                    <div class="content-card text-center p-4 p-md-5 shadow-sm">
                        <div class="mb-3">
                            <div class="rounded-circle bg-danger bg-opacity-10 text-danger d-inline-flex align-items-center justify-content-center" style="width: 72px; height: 72px;">
                                <i class="bi bi-exclamation-triangle-fill fs-1"></i>
                            </div>
                        </div>
                        <h4 class="fw-bold text-dark mb-2">Expense not found.</h4>
                        <p class="text-muted mb-4">
                            The requested expense record does not exist or may have been deleted.
                        </p>
                        <div class="d-flex justify-content-center gap-2">
                            <a href="expenses.php" class="btn btn-primary d-inline-flex align-items-center gap-2 shadow-sm">
                                <i class="bi bi-arrow-left"></i>
                                <span>Back to All Expenses</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <?php
                $formattedExpenseDate = function_exists('formatExpenseDate') 
                    ? formatExpenseDate($expense['expense_date']) 
                    : date('d M Y', strtotime($expense['expense_date']));
                $formattedCreatedDate = !empty($expense['created_at']) 
                    ? date('d M Y, h:i A', strtotime($expense['created_at'])) 
                    : 'N/A';
            ?>

            <!-- Top Header & Action Row -->
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-4 gap-2">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-1 small text-muted">
                            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="expenses.php" class="text-decoration-none">All Expenses</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Details #<?php echo $expense['id']; ?></li>
                        </ol>
                    </nav>
                    <h4 class="fw-bold mb-0 text-slate-800">Expense Details #<?php echo $expense['id']; ?></h4>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <a href="expenses.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
                        <i class="bi bi-arrow-left"></i>
                        <span>Back to All Expenses</span>
                    </a>
                    <a href="edit-expense.php?id=<?php echo $expense['id']; ?>" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
                        <i class="bi bi-pencil-square"></i>
                        <span>Edit Expense</span>
                    </a>
                </div>
            </div>

            <div class="row g-4">
                <!-- Main Information Column -->
                <div class="col-12 col-lg-8">
                    <div class="content-card shadow-sm">
                        <div class="card-header py-3 px-4 d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-receipt text-primary fs-5"></i>
                                <h6 class="mb-0 fw-bold">Expense Information</h6>
                            </div>
                            <span class="badge bg-light text-secondary border">
                                Currency: <strong>PKR</strong>
                            </span>
                        </div>

                        <div class="p-3 p-md-4">
                            <!-- Amount & Title Banner -->
                            <div class="p-3 p-md-4 rounded-3 mb-4 border" style="background-color: #f8fafc;">
                                <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                                    <div>
                                        <div class="text-uppercase small fw-bold text-muted mb-1">Expense Name</div>
                                        <h4 class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($expense['title']); ?></h4>
                                    </div>
                                    <div class="text-md-end">
                                        <div class="text-uppercase small fw-bold text-muted mb-1">Amount</div>
                                        <div class="amount-text text-danger fs-3 fw-bold">
                                            <?php echo formatPKR($expense['amount']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Detailed Attributes Table -->
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle mb-0">
                                    <tbody>
                                        <tr>
                                            <th class="bg-light text-muted fw-semibold" style="width: 200px;">
                                                <i class="bi bi-hash text-primary me-2"></i>Expense ID
                                            </th>
                                            <td class="fw-bold text-dark">
                                                #<?php echo $expense['id']; ?>
                                            </td>
                                        </tr>

                                        <tr>
                                            <th class="bg-light text-muted fw-semibold">
                                                <i class="bi bi-type text-primary me-2"></i>Expense Name
                                            </th>
                                            <td class="fw-medium text-dark">
                                                <?php echo htmlspecialchars($expense['title']); ?>
                                            </td>
                                        </tr>

                                        <tr>
                                            <th class="bg-light text-muted fw-semibold">
                                                <i class="bi bi-cash-stack text-primary me-2"></i>Amount
                                            </th>
                                            <td>
                                                <span class="amount-text text-danger fs-6 fw-bold">
                                                    <?php echo formatPKR($expense['amount']); ?>
                                                </span>
                                            </td>
                                        </tr>

                                        <tr>
                                            <th class="bg-light text-muted fw-semibold">
                                                <i class="bi bi-credit-card-2-front text-primary me-2"></i>Payment Method
                                            </th>
                                            <td>
                                                <span class="payment-badge">
                                                    <?php echo htmlspecialchars($expense['payment_method']); ?>
                                                </span>
                                            </td>
                                        </tr>

                                        <tr>
                                            <th class="bg-light text-muted fw-semibold">
                                                <i class="bi bi-calendar3 text-primary me-2"></i>Expense Date
                                            </th>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="fw-medium text-dark"><?php echo htmlspecialchars($formattedExpenseDate); ?></span>
                                                    <small class="text-muted">(<?php echo htmlspecialchars($expense['expense_date']); ?>)</small>
                                                </div>
                                            </td>
                                        </tr>

                                        <tr>
                                            <th class="bg-light text-muted fw-semibold align-top pt-3">
                                                <i class="bi bi-chat-left-text text-primary me-2"></i>Description / Notes
                                            </th>
                                            <td class="pt-3">
                                                <?php if (!empty($expense['notes'])): ?>
                                                    <div class="p-3 bg-light rounded-2 border text-dark" style="white-space: pre-wrap;"><?php echo htmlspecialchars($expense['notes']); ?></div>
                                                <?php else: ?>
                                                    <span class="text-muted fst-italic">No additional notes provided.</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>

                                        <tr>
                                            <th class="bg-light text-muted fw-semibold">
                                                <i class="bi bi-clock-history text-primary me-2"></i>Recorded At
                                            </th>
                                            <td class="text-muted">
                                                <?php echo htmlspecialchars($formattedCreatedDate); ?>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-4 pt-3 border-top d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <a href="edit-expense.php?id=<?php echo $expense['id']; ?>" class="btn btn-primary px-4 py-2 fw-semibold d-inline-flex align-items-center gap-2 shadow-sm">
                                        <i class="bi bi-pencil-square"></i>
                                        <span>Edit Expense</span>
                                    </a>
                                    <a href="expenses.php" class="btn btn-outline-secondary px-3 py-2 d-inline-flex align-items-center gap-2 shadow-sm">
                                        <i class="bi bi-arrow-left"></i>
                                        <span>Back to All Expenses</span>
                                    </a>
                                </div>
                                <span class="text-muted small">
                                    Record ID: #<?php echo $expense['id']; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Side Summary Column -->
                <div class="col-12 col-lg-4">
                    <div class="content-card p-4 mb-4 shadow-sm">
                        <h6 class="fw-bold mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-info-circle text-primary"></i>
                            <span>Quick Summary</span>
                        </h6>
                        <ul class="list-unstyled mb-0 d-flex flex-column gap-3 small">
                            <li class="d-flex justify-content-between pb-2 border-bottom">
                                <span class="text-muted">Expense ID:</span>
                                <strong class="text-dark">#<?php echo $expense['id']; ?></strong>
                            </li>
                            <li class="d-flex justify-content-between pb-2 border-bottom">
                                <span class="text-muted">Currency:</span>
                                <strong class="text-dark">PKR</strong>
                            </li>
                            <li class="d-flex justify-content-between pb-2 border-bottom">
                                <span class="text-muted">Amount:</span>
                                <span class="text-danger fw-bold fs-6"><?php echo formatPKR($expense['amount']); ?></span>
                            </li>
                            <li class="d-flex justify-content-between pb-2 border-bottom">
                                <span class="text-muted">Payment:</span>
                                <span class="text-dark fw-semibold"><?php echo htmlspecialchars($expense['payment_method']); ?></span>
                            </li>
                            <li class="d-flex justify-content-between">
                                <span class="text-muted">Date:</span>
                                <span class="text-dark"><?php echo htmlspecialchars($formattedExpenseDate); ?></span>
                            </li>
                        </ul>
                    </div>

                    <div class="content-card p-4 shadow-sm">
                        <h6 class="fw-bold mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-compass text-primary"></i>
                            <span>Quick Actions</span>
                        </h6>
                        <div class="d-grid gap-2">
                            <a href="edit-expense.php?id=<?php echo $expense['id']; ?>" class="btn btn-outline-primary btn-sm d-flex align-items-center justify-content-center gap-2 shadow-sm">
                                <i class="bi bi-pencil-square"></i>
                                <span>Edit This Expense</span>
                            </a>
                            <a href="expenses.php" class="btn btn-outline-secondary btn-sm d-flex align-items-center justify-content-center gap-2 shadow-sm">
                                <i class="bi bi-receipt"></i>
                                <span>All Expenses</span>
                            </a>
                            <a href="add-expense.php" class="btn btn-primary btn-sm d-flex align-items-center justify-content-center gap-2 shadow-sm">
                                <i class="bi bi-plus-lg"></i>
                                <span>Add New Expense</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
