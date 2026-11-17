
<?php

require_once '../includes/functions.php';

qflow_start_session();

// Redirect users who are already logged in.
if (!empty($_SESSION['user_id'])) {

    if (!empty($_SESSION['must_change_password'])) {

        $passwordPages = [
            'Student' => 'account/change-password.php',
            'Staff' => 'account/change-password.php',
            'Administrator' => 'account/change-password.php'
        ];

        $destination = $passwordPages[qflow_current_role()] ?? 'login.php';

        header('Location: ' . $destination);
    } else {
        header('Location: dashboard.php');
    }

    exit;
}

// Database connection.
$pdo = require '../config/database.php';

$error = '';
$accountId = '';

// Process login.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $accountId = trim((string) ($_POST['account_id'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $csrfToken = $_POST['csrf_token'] ?? null;

    if (!qflow_verify_csrf($csrfToken)) {

        $error = 'Your session has expired. Please refresh and try again.';

    } elseif ($accountId === '' || $password === '') {

        $error = 'Please enter your Student ID and password.';

    } else {

        $stmt = $pdo->prepare("
            SELECT
                u.user_id,
                u.account_id,
                u.first_name,
                u.last_name,
                u.password,
                u.account_status,
                u.email_verified_at,
                u.must_change_password,
                r.role_name
            FROM users u
            INNER JOIN roles r ON r.role_id = u.role_id
            WHERE u.account_id = ?
            LIMIT 1
        ");

        $stmt->execute([$accountId]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {

            $error = 'Invalid Student ID or password.';

        } elseif (empty($user['email_verified_at'])) {

            $error = 'Please verify your email address before logging in.';

        } elseif ($user['account_status'] !== 'active') {

            $error = 'Your account is not active. Please contact the administrator.';

        } elseif (!in_array(
            $user['role_name'],
            ['Student', 'Staff', 'Administrator'],
            true
        )) {

            $error = 'Your account role is not authorized to access QueueFlow.';

        } else {

            // Regenerate session ID after successful authentication.
            session_regenerate_id(true);

            // Store authenticated user information.
            $_SESSION['user_id'] = (int) $user['user_id'];
            $_SESSION['account_id'] = $user['account_id'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['role_name'] = $user['role_name'];
            $_SESSION['must_change_password'] = (int) $user['must_change_password'];

            // Generate a new CSRF token.
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            // Redirect temporary-password users to their role-specific page.
            if ((int) $user['must_change_password'] === 1) {

                $passwordPages = [
                    'Student' => 'account/change-password.php',
                    'Staff' => 'account/change-password.php',
                    'Administrator' => 'account/change-password.php'
                ];

                header('Location: ' . $passwordPages[$user['role_name']]);
                exit;
            }

            // All roles use the shared dashboard.
            header('Location: dashboard.php');
            exit;
        }
    }
}

// Escape output.
function login_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$csrfToken = qflow_csrf_token();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="icon" type="image/x-icon" href="assets/img/icon.png">
    <link rel="stylesheet" href="assets/css/login.css">

    <title>Login</title>

</head>

<body>

<main class="login-page">

    <div class="login-container">

        <div class="brand">
            <img
                src="assets/img/icon.png"
                alt="QueueFlow logo"
                class="brand-logo"
            >
        </div>

        <section class="heading">
            <h1>Welcome back!</h1>
            <p>Please login to get started!</p>
        </section>

        <?php if ($error !== '') { ?>

            <div class="error-message" role="alert">

                <?php echo htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>

                <?php if ($showResendVerification) { ?>

                    <p style="margin-top: 10px;">
                        <a href="resend-verification.php">
                            Resend verification email
                        </a>
                    </p>

                <?php } ?>

            </div>

        <?php } ?>

        <form method="POST" action="login.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo htmlspecialchars(
                    $csrfToken,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>"
            >

            <div class="form-group">

                <label for="account_id">Student ID</label>

                <input
                    type="text"
                    id="account_id"
                    name="account_id"
                    placeholder="Enter your student ID"
                    autocomplete="username"
                    value="<?php echo htmlspecialchars(
                        $accountId,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>"
                    required
                    autofocus
                >

            </div>

            <div class="form-group">

                <label for="password">Password</label>

                <div class="input-wrapper">

                    <input
                        class="password-input"
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required
                    >

                    <button
                        class="toggle-password"
                        type="button"
                        id="togglePassword"
                        aria-controls="password"
                        aria-pressed="false"
                    >
                        Show
                    </button>

                </div>

            </div>

            <div class="forgot-password">

                <a href="forgot-password.php">
                    Forgot Password?
                </a>

            </div>

            <button class="button button-primary" type="submit">
                Log In
            </button>

        </form>

        <div class="divider">
            <span>or</span>
        </div>

        <a
            class="button button-secondary"
            href="register.php"
            style="text-decoration: none;"
        >
            Create an Account
        </a>

        <p class="footer-note">
            QueueFlow · RMMC-MI Finance Queuing Management System
        </p>

    </div>

</main>

<script>

    const passwordInput = document.getElementById('password');
    const togglePassword = document.getElementById('togglePassword');

    togglePassword.addEventListener('click', function () {

        const isVisible = passwordInput.type === 'text';

        passwordInput.type = isVisible ? 'password' : 'text';

        togglePassword.textContent = isVisible ? 'Show' : 'Hide';

        togglePassword.setAttribute(
            'aria-pressed',
            String(!isVisible)
        );

    });

</script>

</body>
</html>
