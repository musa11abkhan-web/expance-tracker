<?php
/**
 * Project: Expense Tracker
 * Page: Add Expense
 * Currency: PKR (Pakistani Rupees)
 */

require_once __DIR__ . '/config/db.php';
requireLogin();

$pageTitle = 'Add Expense';
$pageHeader = 'Add New Expense';
$activePage = 'add-expense';

// Payment Method options
$paymentMethods = [
    'Cash',
    'Bank',
    'Easypaisa',
    'JazzCash',
    'Other'
];

// Form initial state
$title = '';
$amountInput = '';
$paymentMethod = 'Cash';
$expenseDate = date('Y-m-d');
$notes = '';
$errors = [];

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Validate Expense Name (Title)
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    if ($title === '') {
        $errors['title'] = 'Please enter an expense name.';
    } elseif (mb_strlen($title) > 255) {
        $errors['title'] = 'Expense name cannot exceed 255 characters.';
    }

    // 2. Validate Amount (PKR, must be > 0)
    $amountInput = isset($_POST['amount']) ? trim($_POST['amount']) : '';
    if ($amountInput === '') {
        $errors['amount'] = 'Please enter the expense amount.';
    } elseif (!is_numeric($amountInput)) {
        $errors['amount'] = 'Amount must be a valid number.';
    } elseif ((float)$amountInput <= 0) {
        $errors['amount'] = 'Amount must be greater than 0 PKR.';
    } else {
        $amount = round((float)$amountInput, 2);
    }

    // 3. Validate Expense Date
    $expenseDate = isset($_POST['expense_date']) ? trim($_POST['expense_date']) : '';
    if ($expenseDate === '') {
        $errors['expense_date'] = 'Please choose a date.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expenseDate) || !strtotime($expenseDate)) {
        $errors['expense_date'] = 'Please enter a valid date (YYYY-MM-DD).';
    }

    // 4. Validate Payment Method
    $paymentMethod = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : 'Cash';
    if (!in_array($paymentMethod, $paymentMethods, true)) {
        $paymentMethod = 'Cash';
    }

    // 5. Optional Description / Notes
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    if (mb_strlen($notes) > 1000) {
        $errors['notes'] = 'Description cannot exceed 1000 characters.';
    }

    // Save if no errors
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO expenses (title, category_id, amount, payment_method, expense_date, notes)
                VALUES (:title, NULL, :amount, :payment_method, :expense_date, :notes)
            ");

            $stmt->execute([
                ':title'          => $title,
                ':amount'         => $amount,
                ':payment_method' => $paymentMethod,
                ':expense_date'   => $expenseDate,
                ':notes'          => ($notes !== '' ? $notes : null),
            ]);

            $_SESSION['success_message'] = "Expense \"{$title}\" of " . formatPKR($amount) . " has been saved successfully!";
            header('Location: index.php?success=1');
            exit;

        } catch (PDOException $e) {
            error_log("Database Error in add-expense.php: " . $e->getMessage());
            $errors['database'] = 'An unexpected database error occurred. Please try again.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="main-content">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <main class="p-3 p-md-4">
        <!-- Top Breadcrumb & Heading -->
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-4 gap-2">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1 small text-muted">
                        <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Add Expense</li>
                    </ol>
                </nav>
                <h4 class="fw-bold mb-0 text-slate-800">Add New Expense</h4>
            </div>
            <div>
                <a href="expenses.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
                    <i class="bi bi-receipt-cutoff"></i>
                    <span>All Expenses</span>
                </a>
            </div>
        </div>

        <!-- Validation Error Alert Banner -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-4" role="alert">
                <div class="d-flex align-items-start gap-2">
                    <i class="bi bi-exclamation-triangle-fill fs-5 mt-1 text-danger"></i>
                    <div class="flex-grow-1">
                        <strong>Please check the following error(s):</strong>
                        <ul class="mb-0 mt-1 ps-3 small">
                            <?php foreach ($errors as $errorMsg): ?>
                                <li><?php echo htmlspecialchars($errorMsg); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Main Form Card -->
            <div class="col-12 col-lg-8">
                <div class="content-card shadow-sm">
                    <div class="card-header py-3 px-4 d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-plus-circle text-primary fs-5"></i>
                            <h6 class="mb-0 fw-bold">Expense Information</h6>
                        </div>
                        <span class="badge bg-light text-secondary border">
                            Currency: <strong class="text-dark">PKR</strong>
                        </span>
                    </div>

                    <div class="p-3 p-md-4">
                        <form action="add-expense.php" method="POST" id="expenseForm" novalidate>
                            <div class="row g-3">
                                <!-- 1. Expense Name -->
                                <div class="col-12">
                                    <label for="title" class="form-label fw-semibold text-dark small mb-1">
                                        Expense Name <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group has-validation">
                                        <span class="input-group-text bg-light text-muted">
                                            <i class="bi bi-pencil-square"></i>
                                        </span>
                                        <input 
                                            type="text" 
                                            name="title" 
                                            id="title" 
                                            class="form-control <?php echo isset($errors['title']) ? 'is-invalid' : ''; ?>" 
                                            placeholder="e.g., Grocery Shopping, Electricity Bill, Office Lunch, Fuel" 
                                            value="<?php echo htmlspecialchars($title); ?>" 
                                            maxlength="255"
                                            required 
                                            autofocus
                                        >
                                        <?php if (isset($errors['title'])): ?>
                                            <div class="invalid-feedback">
                                                <?php echo htmlspecialchars($errors['title']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted">Enter a clear name for what you spent money on.</small>
                                </div>

                                <!-- 2. Amount (PKR) -->
                                <div class="col-12 col-md-6">
                                    <label for="amount" class="form-label fw-semibold text-dark small mb-1">
                                        Amount (PKR) <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group has-validation">
                                        <span class="input-group-text bg-light fw-bold text-dark">
                                            PKR
                                        </span>
                                        <input 
                                            type="number" 
                                            name="amount" 
                                            id="amount" 
                                            class="form-control <?php echo isset($errors['amount']) ? 'is-invalid' : ''; ?>" 
                                            step="0.01" 
                                            min="0.01" 
                                            placeholder="0.00" 
                                            value="<?php echo htmlspecialchars($amountInput); ?>" 
                                            required
                                        >
                                        <?php if (isset($errors['amount'])): ?>
                                            <div class="invalid-feedback">
                                                <?php echo htmlspecialchars($errors['amount']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted">Enter the amount spent in Pakistani Rupees.</small>
                                </div>

                                <!-- 3. Date -->
                                <div class="col-12 col-md-6">
                                    <label for="expense_date" class="form-label fw-semibold text-dark small mb-1">
                                        Date <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group has-validation">
                                        <span class="input-group-text bg-light text-muted">
                                            <i class="bi bi-calendar3"></i>
                                        </span>
                                        <input 
                                            type="date" 
                                            name="expense_date" 
                                            id="expense_date" 
                                            class="form-control <?php echo isset($errors['expense_date']) ? 'is-invalid' : ''; ?>" 
                                            value="<?php echo htmlspecialchars($expenseDate); ?>" 
                                            required
                                        >
                                        <?php if (isset($errors['expense_date'])): ?>
                                            <div class="invalid-feedback">
                                                <?php echo htmlspecialchars($errors['expense_date']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted">Date the expense occurred.</small>
                                </div>

                                <!-- 4. Payment Method -->
                                <div class="col-12 col-md-6">
                                    <label for="payment_method" class="form-label fw-semibold text-dark small mb-1">
                                        Payment Method
                                    </label>
                                    <div class="input-group has-validation">
                                        <span class="input-group-text bg-light text-muted">
                                            <i class="bi bi-credit-card-2-front"></i>
                                        </span>
                                        <select 
                                            name="payment_method" 
                                            id="payment_method" 
                                            class="form-select"
                                        >
                                            <?php foreach ($paymentMethods as $methodOption): ?>
                                                <option 
                                                    value="<?php echo htmlspecialchars($methodOption); ?>" 
                                                    <?php echo ($paymentMethod === $methodOption) ? 'selected' : ''; ?>
                                                >
                                                    <?php echo htmlspecialchars($methodOption); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <small class="text-muted">How was this expense paid?</small>
                                </div>

                                <!-- 5. Description / Notes (Optional) -->
                                <div class="col-12">
                                    <label for="notes" class="form-label fw-semibold text-dark small mb-1">
                                        Description <span class="text-muted fw-normal">(Optional)</span>
                                    </label>
                                    <textarea 
                                        name="notes" 
                                        id="notes" 
                                        class="form-control <?php echo isset($errors['notes']) ? 'is-invalid' : ''; ?>" 
                                        rows="3" 
                                        placeholder="Add any additional details, receipt numbers, or notes here..."
                                        maxlength="1000"
                                    ><?php echo htmlspecialchars($notes); ?></textarea>
                                    <?php if (isset($errors['notes'])): ?>
                                        <div class="invalid-feedback">
                                            <?php echo htmlspecialchars($errors['notes']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Action Buttons -->
                                <div class="col-12 pt-3 border-top d-flex flex-wrap align-items-center justify-content-between gap-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold d-inline-flex align-items-center gap-2 shadow-sm">
                                            <i class="bi bi-check-circle"></i>
                                            <span>Save Expense</span>
                                        </button>

                                        <button type="button" id="clearFormBtn" class="btn btn-outline-secondary px-3 py-2 d-inline-flex align-items-center gap-1 shadow-sm">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                            <span>Clear</span>
                                        </button>
                                    </div>

                                    <a href="index.php" class="btn btn-link text-decoration-none text-muted small">
                                        Cancel and return to Dashboard
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Helpful Quick Guide Card -->
            <div class="col-12 col-lg-4">
                <div class="content-card mb-4 shadow-sm">
                    <div class="card-header py-3 px-4">
                        <h6 class="mb-0 fw-bold d-flex align-items-center gap-2">
                            <i class="bi bi-info-circle text-primary"></i>
                            <span>Quick Tips</span>
                        </h6>
                    </div>
                    <div class="p-3 p-md-4">
                        <ul class="list-unstyled mb-0 d-flex flex-column gap-3 small text-muted">
                            <li class="d-flex align-items-start gap-2">
                                <i class="bi bi-check-circle-fill text-success mt-1"></i>
                                <div>
                                    <strong class="text-dark">Simple Entry:</strong>
                                    <div>Just provide an <strong>Expense Name</strong>, the <strong>Amount</strong> in PKR, and select the <strong>Date</strong>.</div>
                                </div>
                            </li>
                            <li class="d-flex align-items-start gap-2">
                                <i class="bi bi-check-circle-fill text-success mt-1"></i>
                                <div>
                                    <strong class="text-dark">All Amounts in PKR:</strong>
                                    <div>Calculations and summaries are consistently maintained in Pakistani Rupees (PKR).</div>
                                </div>
                            </li>
                            <li class="d-flex align-items-start gap-2">
                                <i class="bi bi-check-circle-fill text-success mt-1"></i>
                                <div>
                                    <strong class="text-dark">Instant Dashboard Updates:</strong>
                                    <div>As soon as you save an expense, all dashboard summary cards and reports update instantly.</div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="content-card bg-primary bg-opacity-10 border-primary border-opacity-25 shadow-sm">
                    <div class="p-3 d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center p-3 fs-4" style="width: 48px; height: 48px;">
                            <i class="bi bi-wallet2"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-primary"><?php echo htmlspecialchars($appName); ?></div>
                            <div class="small text-secondary">Simple, Professional &amp; Clean Financial Tracking</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const clearBtn = document.getElementById('clearFormBtn');
    const form = document.getElementById('expenseForm');
    const todayDate = <?php echo json_encode(date('Y-m-d')); ?>;

    if (clearBtn && form) {
        clearBtn.addEventListener('click', function () {
            document.getElementById('title').value = '';
            document.getElementById('amount').value = '';
            document.getElementById('notes').value = '';
            document.getElementById('payment_method').value = 'Cash';
            document.getElementById('expense_date').value = todayDate;

            form.querySelectorAll('.is-invalid').forEach(function (el) {
                el.classList.remove('is-invalid');
            });
            form.querySelectorAll('.invalid-feedback').forEach(function (el) {
                el.style.display = 'none';
            });

            document.getElementById('title').focus();
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
