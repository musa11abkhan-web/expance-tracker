<?php
/**
 * Project: Expense Tracker
 * Page: Edit Expense
 * Currency: PKR (Pakistani Rupees)
 */

require_once __DIR__ . '/config/db.php';
requireLogin();

$pageTitle = 'Edit Expense';
$pageHeader = 'Edit Expense';
$activePage = 'expenses';

$paymentMethods = [
    'Cash',
    'Bank',
    'Easypaisa',
    'JazzCash',
    'Other'
];

$id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
if ($id <= 0) {
    $_SESSION['error_message'] = 'Invalid expense ID.';
    header('Location: expenses.php');
    exit;
}

// Fetch existing record
$fetchStmt = $pdo->prepare("
    SELECT * 
    FROM expenses 
    WHERE id = :id 
    LIMIT 1
");
$fetchStmt->execute([':id' => $id]);
$expense = $fetchStmt->fetch();

if (!$expense) {
    $_SESSION['error_message'] = 'Expense not found.';
    header('Location: expenses.php');
    exit;
}

// Form initial state from DB
$title = $expense['title'];
$amountInput = number_format((float)$expense['amount'], 2, '.', '');
$paymentMethod = $expense['payment_method'];
$expenseDate = $expense['expense_date'];
$notes = $expense['notes'] ?? '';
$errors = [];

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Validate Expense Name
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    if ($title === '') {
        $errors['title'] = 'Please enter an expense name.';
    } elseif (mb_strlen($title) > 255) {
        $errors['title'] = 'Expense name cannot exceed 255 characters.';
    }

    // 2. Validate Amount (PKR)
    $amountInput = isset($_POST['amount']) ? trim($_POST['amount']) : '';
    if ($amountInput === '') {
        $errors['amount'] = 'Please enter the expense amount.';
    } elseif (!is_numeric($amountInput) || (float)$amountInput <= 0) {
        $errors['amount'] = 'Amount must be greater than 0 PKR.';
    } else {
        $amount = round((float)$amountInput, 2);
    }

    // 3. Validate Payment Method
    $paymentMethod = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : 'Cash';
    if (!in_array($paymentMethod, $paymentMethods, true)) {
        $paymentMethod = 'Cash';
    }

    // 4. Validate Date
    $expenseDate = isset($_POST['expense_date']) ? trim($_POST['expense_date']) : '';
    if ($expenseDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expenseDate) || !strtotime($expenseDate)) {
        $errors['expense_date'] = 'Please enter a valid date (YYYY-MM-DD).';
    }

    // 5. Optional Notes
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    if (mb_strlen($notes) > 1000) {
        $errors['notes'] = 'Description cannot exceed 1000 characters.';
    }

    if (empty($errors)) {
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
                ':title'          => $title,
                ':amount'         => $amount,
                ':payment_method' => $paymentMethod,
                ':expense_date'   => $expenseDate,
                ':notes'          => ($notes !== '' ? $notes : null),
                ':id'             => $id,
            ]);

            $_SESSION['success_message'] = "Expense \"{$title}\" updated successfully (" . formatPKR($amount) . ")!";
            header('Location: expenses.php');
            exit;

        } catch (PDOException $e) {
            error_log("Edit expense page error: " . $e->getMessage());
            $errors['database'] = 'A database error occurred while updating the expense.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="main-content">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <main class="p-3 p-md-4">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-4 gap-2">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1 small text-muted">
                        <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="expenses.php" class="text-decoration-none">All Expenses</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Edit #<?php echo $id; ?></li>
                    </ol>
                </nav>
                <h4 class="fw-bold mb-0 text-slate-800">Edit Expense #<?php echo $id; ?></h4>
            </div>
            <div>
                <a href="expenses.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
                    <i class="bi bi-arrow-left"></i>
                    <span>Back to All Expenses</span>
                </a>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-4" role="alert">
                <div class="d-flex align-items-start gap-2">
                    <i class="bi bi-exclamation-triangle-fill fs-5 mt-1 text-danger"></i>
                    <div class="flex-grow-1">
                        <strong>Please resolve the following error(s):</strong>
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
            <div class="col-12 col-lg-8">
                <div class="content-card shadow-sm">
                    <div class="card-header py-3 px-4 d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-pencil-square text-primary fs-5"></i>
                            <h6 class="mb-0 fw-bold">Edit Details</h6>
                        </div>
                        <span class="badge bg-light text-secondary border">
                            Currency: <strong>PKR</strong>
                        </span>
                    </div>

                    <div class="p-3 p-md-4">
                        <form action="edit-expense.php?id=<?php echo $id; ?>" method="POST" novalidate>
                            <div class="row g-3">
                                <!-- 1. Expense Name -->
                                <div class="col-12">
                                    <label for="title" class="form-label fw-semibold text-dark small mb-1">
                                        Expense Name <span class="text-danger">*</span>
                                    </label>
                                    <input 
                                        type="text" 
                                        name="title" 
                                        id="title" 
                                        class="form-control <?php echo isset($errors['title']) ? 'is-invalid' : ''; ?>" 
                                        value="<?php echo htmlspecialchars($title); ?>" 
                                        maxlength="255" 
                                        required
                                    >
                                    <?php if (isset($errors['title'])): ?>
                                        <div class="invalid-feedback">
                                            <?php echo htmlspecialchars($errors['title']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- 2. Amount (PKR) -->
                                <div class="col-12 col-md-6">
                                    <label for="amount" class="form-label fw-semibold text-dark small mb-1">
                                        Amount (PKR) <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group has-validation">
                                        <span class="input-group-text bg-light fw-bold text-dark">PKR</span>
                                        <input 
                                            type="number" 
                                            name="amount" 
                                            id="amount" 
                                            class="form-control <?php echo isset($errors['amount']) ? 'is-invalid' : ''; ?>" 
                                            step="0.01" 
                                            min="0.01" 
                                            value="<?php echo htmlspecialchars($amountInput); ?>" 
                                            required
                                        >
                                        <?php if (isset($errors['amount'])): ?>
                                            <div class="invalid-feedback">
                                                <?php echo htmlspecialchars($errors['amount']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- 3. Date -->
                                <div class="col-12 col-md-6">
                                    <label for="expense_date" class="form-label fw-semibold text-dark small mb-1">
                                        Date <span class="text-danger">*</span>
                                    </label>
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

                                <!-- 4. Payment Method -->
                                <div class="col-12 col-md-6">
                                    <label for="payment_method" class="form-label fw-semibold text-dark small mb-1">
                                        Payment Method
                                    </label>
                                    <select name="payment_method" id="payment_method" class="form-select">
                                        <?php foreach ($paymentMethods as $pm): ?>
                                            <option value="<?php echo htmlspecialchars($pm); ?>" <?php echo ($paymentMethod === $pm) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($pm); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- 5. Notes / Description -->
                                <div class="col-12">
                                    <label for="notes" class="form-label fw-semibold text-dark small mb-1">
                                        Description <span class="text-muted fw-normal">(Optional)</span>
                                    </label>
                                    <textarea 
                                        name="notes" 
                                        id="notes" 
                                        class="form-control <?php echo isset($errors['notes']) ? 'is-invalid' : ''; ?>" 
                                        rows="3" 
                                        maxlength="1000"
                                    ><?php echo htmlspecialchars($notes); ?></textarea>
                                    <?php if (isset($errors['notes'])): ?>
                                        <div class="invalid-feedback">
                                            <?php echo htmlspecialchars($errors['notes']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Buttons -->
                                <div class="col-12 pt-3 border-top d-flex flex-wrap align-items-center justify-content-between gap-2">
                                    <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold d-inline-flex align-items-center gap-2 shadow-sm">
                                        <i class="bi bi-check2-circle"></i>
                                        <span>Update Expense</span>
                                    </button>
                                    <a href="expenses.php" class="btn btn-outline-secondary px-3 py-2 shadow-sm">
                                        Cancel
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Side Info Card -->
            <div class="col-12 col-lg-4">
                <div class="content-card p-4 shadow-sm mb-4">
                    <h6 class="fw-bold mb-3 d-flex align-items-center gap-2">
                        <i class="bi bi-info-circle text-primary"></i>
                        <span>Record Details</span>
                    </h6>
                    <ul class="list-unstyled mb-0 d-flex flex-column gap-2 small text-muted">
                        <li><strong>Expense ID:</strong> #<?php echo $id; ?></li>
                        <li><strong>Created On:</strong> <?php echo date('d M Y, h:i A', strtotime($expense['created_at'])); ?></li>
                        <li><strong>Currency:</strong> PKR</li>
                        <li><strong>Current Amount:</strong> <span class="text-danger fw-bold"><?php echo formatPKR($expense['amount']); ?></span></li>
                    </ul>
                </div>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
