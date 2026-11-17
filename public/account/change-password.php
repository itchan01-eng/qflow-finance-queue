<?php

require_once '../../includes/functions.php';

qflow_start_session();

// Only authenticated users can access this page.
qflow_require_login();

// Database connection.
$pdo = require '../../config/database.php';

$error = '';

$userId = (int) ($_SESSION['user_id'] ?? 0);
$roleName = $_SESSION['role_name'] ?? '';

// Allow only authorized QueueFlow roles.
if (!in_array($roleName, ['Student', 'Staff', 'Administrator'], true)) {
    http_response_code(403);
    exit('Access denied.');
}

// Get the authenticated user's account.
$stmt = $pdo->prepare("
    SELECT u.user_id, u.password, u.must_change_password, r.role_name
    FROM users u
    INNER JOIN roles r ON r.role_id = u.role_id
    WHERE u.user_id = ?
    LIMIT 1
");

$stmt->execute([$userId]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['role_name'] !== $roleName) {
    http_response_code(403);
    exit('Account not found or unauthorized.');
}

// Redirect users who have already changed their password.
if ((int) $user['must_change_password'] !== 1) {
    $_SESSION['must_change_password'] = 0;
    header('Location: ../dashboard.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| PROCESS PASSWORD CHANGE
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
    $csrfToken = $_POST['csrf_token'] ?? null;

    if (
        !is_string($csrfToken) ||
        !qflow_verify_csrf($csrfToken)
    ) {

        $error = 'Your session has expired. Please refresh and try again.';

    } elseif ($newPassword === '' || $confirmPassword === '') {

        $error = 'Please complete all fields.';

    } elseif (strlen($newPassword) < 8) {

        $error = 'Your new password must contain at least 8 characters.';

    } elseif (strlen($newPassword) > 72) {

        $error = 'Your password must not exceed 72 characters.';

    } elseif ($newPassword !== $confirmPassword) {

        $error = 'Your passwords do not match.';

    } elseif (password_verify($newPassword, $user['password'])) {

        $error = 'Please choose a password different from your temporary password.';

    } else {

        try {

            $newPasswordHash = password_hash(
                $newPassword,
                PASSWORD_DEFAULT
            );

            $stmt = $pdo->prepare("
                UPDATE users
                SET password = ?,
                    must_change_password = 0
                WHERE user_id = ?
                  AND must_change_password = 1
            ");

            $stmt->execute([
                $newPasswordHash,
                $userId
            ]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException(
                    'Your password could not be updated. Please try again.'
                );
            }

            // Update the session.
            $_SESSION['must_change_password'] = 0;

            // Regenerate session ID after password change.
            session_regenerate_id(true);

            // Generate a fresh CSRF token.
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            // All roles use the shared dashboard.
            header('Location: ../dashboard.php');
            exit;

        } catch (Throwable $e) {

            error_log(
                'QueueFlow password change error: '
                . $e->getMessage()
            );

            $error = 'Something went wrong while changing your password. Please try again.';
        }
    }
}

$csrfToken = qflow_csrf_token();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="icon" type="image/x-icon" href="../assets/img/icon.png">
    <link rel="stylesheet" href="../assets/css/login.css">

    <title>First-Time Password Change | QueueFlow</title>

</head>

<body>

<main class="login-page">

    <div class="login-container">

        <div class="brand">
            <img
                src="../assets/img/icon.png"
                alt="QueueFlow logo"
                class="brand-logo"
            >
        </div>

        <section class="heading">

            <h1>First-Time Login</h1>

            <p>
                Password change required.
            </p>

            <p>
                For security purposes, you are required to change your temporary password before accessing your QueueFlow account.
            </p>

        </section>

        <?php if ($error !== '') { ?>

            <div class="error-message" role="alert">
                <?php echo qflow_escape($error); ?>
            </div>

        <?php } ?>

        <form method="POST" action="change-password.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo qflow_escape($csrfToken); ?>"
            >

            <div class="form-group">

                <label for="new_password">
                    New Password
                </label>

                <div class="input-wrapper">

                    <input
                        class="password-input"
                        type="password"
                        id="new_password"
                        name="new_password"
                        placeholder="Enter your new password"
                        autocomplete="new-password"
                        minlength="8"
                        maxlength="72"
                        required
                    >

                    <button
                        class="toggle-password"
                        type="button"
                        data-password="new_password"
                        aria-controls="new_password"
                        aria-pressed="false"
                    >
                        Show
                    </button>

                </div>

            </div>

            <div class="form-group">

                <label for="confirm_password">
                    Confirm New Password
                </label>

                <div class="input-wrapper">

                    <input
                        class="password-input"
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        placeholder="Re-enter your new password"
                        autocomplete="new-password"
                        minlength="8"
                        maxlength="72"
                        required
                    >

                    <button
                        class="toggle-password"
                        type="button"
                        data-password="confirm_password"
                        aria-controls="confirm_password"
                        aria-pressed="false"
                    >
                        Show
                    </button>

                </div>

            </div>

            <p class="footer-note" style="margin-bottom: 15px;">
                Your new password must contain at least 8 characters and must be different from your temporary password.
            </p>

            <button class="button button-primary" type="submit">
                Change Password
            </button>

        </form>

        <form method="POST" action="../logout.php" style="margin-top: 12px;">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo qflow_escape(qflow_csrf_token()); ?>"
            >

            <button
                class="button button-secondary"
                type="submit"
                style="width: 100%;"
            >
                Log Out
            </button>

        </form>

        <p class="footer-note">
            QueueFlow · RMMC-MI Finance Queuing Management System
        </p>

    </div>

</main>

<script>

    document.querySelectorAll('.toggle-password').forEach(function (button) {

        button.addEventListener('click', function () {

            const passwordInput = document.getElementById(
                this.dataset.password
            );

            const isVisible = passwordInput.type === 'text';

            passwordInput.type = isVisible ? 'password' : 'text';

            this.textContent = isVisible ? 'Show' : 'Hide';

            this.setAttribute(
                'aria-pressed',
                String(!isVisible)
            );

        });

    });

</script>

</body>
</html>