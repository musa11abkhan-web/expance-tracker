<?php
/**
 * Login Page
 * Project: Expense Tracker
 * Branding: MUSA / 3465340082
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

// Anti-caching headers
sendNoCacheHeaders();

// If already logged in, redirect straight to dashboard
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$siteTitle = function_exists('getSetting') ? getSetting('app_name', 'MUSA / 3465340082') : 'MUSA / 3465340082';
$errorMessage = '';
$infoMessage = '';

if (!empty($_SESSION['login_message'])) {
    $infoMessage = $_SESSION['login_message'];
    unset($_SESSION['login_message']);
}

$usernameInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameInput = isset($_POST['username']) ? trim($_POST['username']) : '';
    $passwordInput = isset($_POST['password']) ? (string)$_POST['password'] : '';

    if ($usernameInput === '' || $passwordInput === '') {
        $errorMessage = 'Please enter both username and password.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `username` = :username LIMIT 1");
            $stmt->execute([':username' => $usernameInput]);
            $user = $stmt->fetch();

            if ($user && password_verify($passwordInput, $user['password'])) {
                // Successful login
                loginUser($user);

                // Redirect to target URL or dashboard
                $redirectUrl = 'index.php';
                if (!empty($_SESSION['redirect_after_login'])) {
                    $redirectUrl = $_SESSION['redirect_after_login'];
                    unset($_SESSION['redirect_after_login']);
                }
                header('Location: ' . $redirectUrl);
                exit;
            } else {
                $errorMessage = 'Invalid username or password. Please try again.';
            }
        } catch (PDOException $e) {
            error_log("Login Query Error: " . $e->getMessage());
            $errorMessage = 'A database error occurred. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MUSA / 3465340082</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo file_exists(__DIR__ . '/assets/css/style.css') ? filemtime(__DIR__ . '/assets/css/style.css') : time(); ?>">

    <style>
        body.login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle at 50% 20%, #1e293b 0%, #0f172a 100%);
            padding: 1.5rem;
            color: #1e293b;
        }

        .login-card {
            width: 100%;
            max-width: 440px;
            background: #ffffff;
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .login-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);
            padding: 2.25rem 2rem 2rem;
            text-align: center;
            color: #ffffff;
        }

        .login-brand-icon {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, #2563eb, #0d9488);
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            color: #ffffff;
            box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.4);
            margin-bottom: 1rem;
        }

        .login-title {
            font-size: 1.35rem;
            font-weight: 700;
            letter-spacing: -0.01em;
            margin-bottom: 0.25rem;
            color: #ffffff;
        }

        .login-subtitle {
            font-size: 0.8rem;
            color: #94a3b8;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .login-body {
            padding: 2.25rem 2rem 2rem;
        }

        .form-control:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 0.25rem rgba(37, 99, 235, 0.15);
        }

        .btn-login {
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
            border: none;
            padding: 0.75rem 1.25rem;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        .btn-login:hover {
            background: linear-gradient(135deg, #1e40af 0%, #1d4ed8 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35);
        }

        .credentials-hint {
            background-color: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 0.5rem;
            padding: 0.85rem 1rem;
            font-size: 0.8rem;
            color: #475569;
        }
    </style>
</head>
<body class="login-page">

    <div class="login-card">
        <!-- Brand Header with Title MUSA / 3465340082 -->
        <div class="login-header">
            <div class="login-brand-icon">
                <i class="bi bi-wallet2"></i>
            </div>
            <h1 class="login-title"><?php echo htmlspecialchars($siteTitle); ?></h1>
            <div class="login-subtitle">Expense Tracker &bull; Secure Access</div>
        </div>

        <div class="login-body">
            <!-- Info / Logout Message -->
            <?php if (!empty($infoMessage)): ?>
                <div class="alert alert-info alert-dismissible fade show d-flex align-items-center gap-2 mb-3 py-2 px-3 small border-0" role="alert" style="background-color: #eff6ff; color: #1e40af;">
                    <i class="bi bi-info-circle-fill flex-shrink-0"></i>
                    <div><?php echo htmlspecialchars($infoMessage); ?></div>
                    <button type="button" class="btn-close ms-auto p-2" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Error Message -->
            <?php if (!empty($errorMessage)): ?>
                <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 mb-3 py-2 px-3 small border-0" role="alert" style="background-color: #fef2f2; color: #991b1b;">
                    <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
                    <div><?php echo htmlspecialchars($errorMessage); ?></div>
                    <button type="button" class="btn-close ms-auto p-2" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" action="login.php" autocomplete="off">
                <!-- Username Field -->
                <div class="mb-3">
                    <label for="username" class="form-label small fw-semibold text-secondary mb-1">
                        <i class="bi bi-person me-1"></i> Username
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted border-end-0">
                            <i class="bi bi-person-fill"></i>
                        </span>
                        <input type="text" 
                               class="form-control border-start-0 ps-0" 
                               id="username" 
                               name="username" 
                               value="<?php echo htmlspecialchars($usernameInput); ?>" 
                               required 
                               autofocus 
                               placeholder="Enter username">
                    </div>
                </div>

                <!-- Password Field -->
                <div class="mb-4">
                    <label for="password" class="form-label small fw-semibold text-secondary mb-1">
                        <i class="bi bi-lock me-1"></i> Password
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted border-end-0">
                            <i class="bi bi-lock-fill"></i>
                        </span>
                        <input type="password" 
                               class="form-control border-start-0 ps-0" 
                               id="password" 
                               name="password" 
                               required 
                               placeholder="Enter password">
                        <button class="btn btn-outline-secondary border-start-0" type="button" id="togglePasswordBtn" title="Toggle password visibility">
                            <i class="bi bi-eye" id="togglePasswordIcon"></i>
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn btn-primary btn-login w-100 d-inline-flex align-items-center justify-content-center gap-2 shadow-sm">
                    <i class="bi bi-box-arrow-in-right fs-5"></i>
                    <span>Log In</span>
                </button>
            </form>

            <!-- Initial Admin Setup Hint -->
            <div class="credentials-hint mt-4 text-center">
                <i class="bi bi-shield-lock me-1 text-primary"></i>
                <strong>Default Administrator Access:</strong><br>
                Username: <code>admin</code> &bull; Password: <code>admin123</code><br>
                <span class="text-muted small">You can change your password anytime in Settings.</span>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password visibility toggle
        const toggleBtn = document.getElementById('togglePasswordBtn');
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.getElementById('togglePasswordIcon');
        if (toggleBtn && passwordInput && toggleIcon) {
            toggleBtn.addEventListener('click', function() {
                const isPassword = passwordInput.getAttribute('type') === 'password';
                passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
                toggleIcon.className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
            });
        }
    </script>
</body>
</html>
