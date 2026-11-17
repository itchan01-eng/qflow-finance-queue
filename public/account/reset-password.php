<?php

require_once '../includes/functions.php';

qflow_start_session();

$pdo = require '../config/database.php';

$error = '';
$success = '';
$firstLoginBlocked = false;

// Read and normalize the token.
$token = $_GET['token'] ?? $_POST['token'] ?? '';

if (is_string($token)) {
    $token = trim($token);
} else {
    $token = '';
}

$tokenHash = '';
$validToken = false;

// Validate token format and check database.
if (
    $token !== '' &&
    preg_match('/^[a-f0-9]{64}$/', $token)
) {

    $tokenHash = hash('sha256', $token);

    try {

        // Check the reset token and associated account.
        $check = $pdo->prepare('
            SELECT
                t.token_id,
                t.user_id,
                t.expires_at,
                u.must_change_password,
                u.account_status
            FROM password_reset_tokens t
            INNER JOIN users u ON u.user_id = t.user_id
            WHERE t.token_hash = ?
            LIMIT 1
        ');

        $check->execute([$tokenHash]);

        $reset = $check->fetch(PDO::FETCH_ASSOC);

        if (!$reset) {

            $error = 'This password reset link is invalid or has already been used. Please request a new one.';

        } elseif ((int) $reset['must_change_password'] === 1) {

            // First-time users must use their temporary password.
            $delete = $pdo->prepare('
                DELETE FROM password_reset_tokens
                WHERE user_id = ?
            ');

            $delete->execute([
                (int) $reset['user_id']
            ]);

            $firstLoginBlocked = true;

            $error = 'You are using a temporary password. For security purposes, you must log in and change your password before using the password reset feature.';

        } elseif ($reset['account_status'] !== 'active') {

            $error = 'This account is not currently active. Please contact the administrator.';

        } elseif (strtotime($reset['expires_at']) <= time()) {

            $delete = $pdo->prepare('
                DELETE FROM password_reset_tokens
                WHERE token_id = ?
            ');

            $delete->execute([
                (int) $reset['token_id']
            ]);

            $error = 'This password reset link has expired. Please request a new one.';

        } else {

            $validToken = true;

        }

    } catch (Throwable $exception) {

        error_log(
            'QueueFlow reset password check error: ' .
            $exception->getMessage()
        );

        $error = 'We could not validate your reset link. Please try again later.';

    }

} else {

    $error = 'This password reset link is invalid. Please request a new one.';

}


/*
|--------------------------------------------------------------------------
| PROCESS PASSWORD RESET
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {

    $csrfToken = $_POST['csrf_token'] ?? '';

    if (is_string($csrfToken)) {
        $csrfToken = trim($csrfToken);
    } else {
        $csrfToken = '';
    }

    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!qflow_verify_csrf($csrfToken)) {

        $error = 'Your session has expired. Please refresh the page and try again.';

    } elseif (
        !is_string($password) ||
        strlen($password) < 8
    ) {

        $error = 'Your new password must contain at least 8 characters.';

    } elseif (strlen($password) > 72) {

        $error = 'Your password must not exceed 72 characters.';

    } elseif (
        !is_string($confirmPassword) ||
        $password !== $confirmPassword
    ) {

        $error = 'Your passwords do not match. Please try again.';

    } else {

        try {

            $pdo->beginTransaction();

            /*
             * Lock the token and associated user account.
             * Recheck must_change_password before allowing a reset.
             */
            $stmt = $pdo->prepare('
                SELECT
                    t.user_id,
                    t.expires_at,
                    u.must_change_password,
                    u.account_status
                FROM password_reset_tokens t
                INNER JOIN users u ON u.user_id = t.user_id
                WHERE t.token_hash = ?
                LIMIT 1
                FOR UPDATE
            ');

            $stmt->execute([$tokenHash]);

            $reset = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reset) {

                $pdo->commit();

                $validToken = false;

                $error = 'This password reset link is invalid or has already been used. Please request a new one.';

            } elseif ((int) $reset['must_change_password'] === 1) {

                // Invalidate all reset tokens for first-time users.
                $delete = $pdo->prepare('
                    DELETE FROM password_reset_tokens
                    WHERE user_id = ?
                ');

                $delete->execute([
                    (int) $reset['user_id']
                ]);

                $pdo->commit();

                $validToken = false;
                $firstLoginBlocked = true;

                $error = 'You are using a temporary password. You must log in and change your password before using the password reset feature.';

            } elseif ($reset['account_status'] !== 'active') {

                $pdo->commit();

                $validToken = false;

                $error = 'This account is not currently active. Please contact the administrator.';

            } elseif (strtotime($reset['expires_at']) <= time()) {

                $delete = $pdo->prepare('
                    DELETE FROM password_reset_tokens
                    WHERE token_hash = ?
                ');

                $delete->execute([$tokenHash]);

                $pdo->commit();

                $validToken = false;

                $error = 'This password reset link has expired. Please request a new one.';

            } else {

                $userId = (int) $reset['user_id'];

                // Hash the new password.
                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                if ($hashedPassword === false) {
                    throw new RuntimeException(
                        'Password hashing failed.'
                    );
                }

                // Update the user's password.
                $update = $pdo->prepare('
                    UPDATE users
                    SET password = ?
                    WHERE user_id = ?
                      AND must_change_password = 0
                      AND account_status = ?
                ');

                $update->execute([
                    $hashedPassword,
                    $userId,
                    'active'
                ]);

                if ($update->rowCount() !== 1) {

                    // Confirm that the account still exists.
                    $checkUser = $pdo->prepare('
                        SELECT user_id
                        FROM users
                        WHERE user_id = ?
                          AND must_change_password = 0
                          AND account_status = ?
                        LIMIT 1
                    ');

                    $checkUser->execute([
                        $userId,
                        'active'
                    ]);

                    if (!$checkUser->fetchColumn()) {
                        throw new RuntimeException(
                            'The account could not be updated.'
                        );
                    }
                }

                // Delete all reset tokens belonging to this user.
                $delete = $pdo->prepare('
                    DELETE FROM password_reset_tokens
                    WHERE user_id = ?
                ');

                $delete->execute([$userId]);

                $pdo->commit();

                $validToken = false;

                $success = 'Your password has been successfully reset. You can now log in using your new password.';

            }

        } catch (Throwable $exception) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'QueueFlow reset password error: ' .
                $exception->getMessage()
            );

            $error = 'We could not reset your password right now. Please try again later.';

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

    <link rel="icon" type="image/x-icon" href="assets/img/icon.png">
    <link rel="stylesheet" href="assets/css/login.css">

    <title>Reset Password | QueueFlow</title>

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

        <?php if ($success !== '') { ?>

            <section class="heading">

                <h1>Password Reset Complete!</h1>

                <p>
                    Your account password has been updated.
                </p>

            </section>

            <div class="success-message" role="status">

                <?php echo htmlspecialchars(
                    $success,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>

            </div>

            <a
                class="button button-primary"
                href="login.php"
                style="text-decoration: none;"
            >
                Continue to Login
            </a>

        <?php } elseif ($validToken) { ?>

            <section class="heading">

                <h1>Create New Password</h1>

                <p>
                    Enter your new password below to regain access to your account.
                </p>

            </section>

            <?php if ($error !== '') { ?>

                <div class="error-message" role="alert">

                    <?php echo htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>

                </div>

            <?php } ?>

            <form method="POST" action="reset-password.php">

                <input
                    type="hidden"
                    name="token"
                    value="<?php echo htmlspecialchars(
                        $token,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>"
                >

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

                    <label for="password">
                        New Password
                    </label>

                    <div class="input-wrapper">

                        <input
                            class="password-input"
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter your new password"
                            minlength="8"
                            maxlength="72"
                            autocomplete="new-password"
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
                            placeholder="Confirm your new password"
                            minlength="8"
                            maxlength="72"
                            autocomplete="new-password"
                            required
                        >

                        <button
                            class="toggle-password"
                            type="button"
                            id="toggleConfirmPassword"
                            aria-controls="confirm_password"
                            aria-pressed="false"
                        >
                            Show
                        </button>

                    </div>

                </div>

                <p class="footer-note" style="margin-bottom: 15px;">
                    Your password must contain at least 8 characters.
                </p>

                <button class="button button-primary" type="submit">
                    Reset Password
                </button>

            </form>

        <?php } else { ?>

            <section class="heading">

                <?php if ($firstLoginBlocked) { ?>

                    <h1>First-Time Login Required</h1>

                    <p>
                        You must change your temporary password before using the password reset feature.
                    </p>

                <?php } else { ?>

                    <h1>Reset Link Unavailable</h1>

                    <p>
                        Your password reset link cannot be used.
                    </p>

                <?php } ?>

            </section>

            <div class="error-message" role="alert">

                <?php echo htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>

            </div>

            <?php if ($firstLoginBlocked) { ?>

                <a
                    class="button button-primary"
                    href="login.php"
                    style="text-decoration: none;"
                >
                    Log In to QueueFlow
                </a>

            <?php } else { ?>

                <a
                    class="button button-primary"
                    href="forgot-password.php"
                    style="text-decoration: none;"
                >
                    Request Another Reset Link
                </a>

            <?php } ?>

        <?php } ?>

        <div class="divider">
            <span>or</span>
        </div>

        <a
            class="button button-secondary"
            href="login.php"
            style="text-decoration: none;"
        >
            Back to Login
        </a>

        <p class="footer-note">
            QueueFlow · RMMC-MI Finance Queuing Management System
        </p>

    </div>

</main>

<script>

    function setupPasswordToggle(inputId, buttonId) {

        const passwordInput = document.getElementById(inputId);
        const toggleButton = document.getElementById(buttonId);

        if (!passwordInput || !toggleButton) {
            return;
        }

        toggleButton.addEventListener('click', function() {

            const isVisible = passwordInput.type === 'text';

            passwordInput.type = isVisible ? 'password' : 'text';

            toggleButton.textContent = isVisible ? 'Show' : 'Hide';

            toggleButton.setAttribute(
                'aria-pressed',
                String(!isVisible)
            );

        });

    }

    setupPasswordToggle('password', 'togglePassword');

    setupPasswordToggle(
        'confirm_password',
        'toggleConfirmPassword'
    );

</script>

</body>
</html>