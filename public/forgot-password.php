<?php

require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/send-email.php';

qflow_start_session();

$pdo = require dirname(__DIR__) . '/config/database.php';

$error = '';
$success = '';
$email = '';

// Load environment configuration from the project root.
$envPath = dirname(__DIR__) . '/.env';

$env = parse_ini_file(
    $envPath,
    false,
    INI_SCANNER_RAW
);

if ($env === false) {
    error_log('QueueFlow: Unable to read .env file: ' . $envPath);
    die('Unable to load application configuration.');
}

// Get application URL from the loaded environment array.
$appUrl = rtrim(
    trim($env['QUEUEFLOW_APP_URL'] ?? ''),
    '/'
);

if ($appUrl === '') {
    error_log('QueueFlow: QUEUEFLOW_APP_URL is missing from .env.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = strtolower(trim($_POST['email'] ?? ''));
    $csrfToken = $_POST['csrf_token'] ?? '';

    // Validate CSRF token.
    if (
        !is_string($csrfToken) ||
        !qflow_verify_csrf($csrfToken)
    ) {

        $error = 'Your session has expired. Please refresh the page and try again.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = 'Please enter a valid email address.';

    } elseif ($appUrl === '') {

        $error = 'Password reset is temporarily unavailable. Please contact the administrator.';

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | FIND ACCOUNT
            |--------------------------------------------------------------------------
            */

            // Get the account and check its first-login password status.
            $stmt = $pdo->prepare('
                SELECT
                    user_id,
                    first_name,
                    email,
                    must_change_password
                FROM users
                WHERE LOWER(email) = LOWER(?)
                  AND account_status = ?
                LIMIT 1
            ');

            $stmt->execute([
                $email,
                'active'
            ]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {

                $error = 'No active account was found with this email address. Please check your email and try again.';

            /*
            |--------------------------------------------------------------------------
            | PREVENT FIRST-TIME USERS FROM RESETTING PASSWORD
            |--------------------------------------------------------------------------
            */

            } elseif ((int) $user['must_change_password'] === 1) {

                $error = 'You are using a temporary password. For security purposes, you must log in and change your password before using the Forgot Password feature.';

            } else {

                $userId = (int) $user['user_id'];

                /*
                |--------------------------------------------------------------------------
                | CHECK PASSWORD RESET COOLDOWN
                |--------------------------------------------------------------------------
                */

                // Check the latest password reset request.
                $latestToken = $pdo->prepare('
                    SELECT created_at
                    FROM password_reset_tokens
                    WHERE user_id = ?
                    ORDER BY created_at DESC
                    LIMIT 1
                ');

                $latestToken->execute([$userId]);

                $lastCreated = $latestToken->fetchColumn();

                $canSend = true;

                // 60-second request cooldown.
                if ($lastCreated) {

                    $lastRequestTime = strtotime($lastCreated);

                    if (
                        $lastRequestTime !== false &&
                        (time() - $lastRequestTime) < 60
                    ) {
                        $canSend = false;
                    }
                }

                if (!$canSend) {

                    $error = 'A password reset link was recently requested for this account. Please wait 60 seconds before requesting another one.';

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | GENERATE PASSWORD RESET TOKEN
                    |--------------------------------------------------------------------------
                    */

                    // Generate a secure reset token.
                    $token = bin2hex(random_bytes(32));

                    // Store only the hashed token.
                    $tokenHash = hash('sha256', $token);

                    // Token expires after 30 minutes.
                    $expiresAt = date(
                        'Y-m-d H:i:s',
                        time() + (30 * 60)
                    );

                    // Save the reset token.
                    $pdo->beginTransaction();

                    // Delete previous reset tokens.
                    $delete = $pdo->prepare('
                        DELETE FROM password_reset_tokens
                        WHERE user_id = ?
                    ');

                    $delete->execute([$userId]);

                    // Insert the new reset token.
                    $insert = $pdo->prepare('
                        INSERT INTO password_reset_tokens (
                            user_id,
                            token_hash,
                            expires_at
                        ) VALUES (?, ?, ?)
                    ');

                    $insert->execute([
                        $userId,
                        $tokenHash,
                        $expiresAt
                    ]);

                    $pdo->commit();

                    /*
                    |--------------------------------------------------------------------------
                    | BUILD RESET EMAIL
                    |--------------------------------------------------------------------------
                    */

                    // Build the password reset URL.
                    $resetLink = $appUrl .
                        '/reset-password.php?token=' .
                        urlencode($token);

                    $safeFirstName = htmlspecialchars(
                        $user['first_name'] ?? 'there',
                        ENT_QUOTES,
                        'UTF-8'
                    );

                    $safeLink = htmlspecialchars(
                        $resetLink,
                        ENT_QUOTES,
                        'UTF-8'
                    );

                    // Password reset email.
                    $emailMessage = '

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Reset Your Password</title>
    </head>

    <body style="
        margin:0;
        padding:30px 15px;
        background:#f5f6fa;
        font-family:Arial,sans-serif;
        color:#202124;
    ">

        <div style="
            max-width:560px;
            margin:0 auto;
            background:#ffffff;
            border:1px solid #eeeeF2;
            border-radius:12px;
            overflow:hidden;
        ">

            <div style="
                padding:28px 30px;
                text-align:center;
                border-bottom:1px solid #eeeeF2;
            ">

                <h2 style="margin:0;color:#c8102e;">
                    QueueFlow
                </h2>

                <p style="
                    margin:8px 0 0;
                    font-size:13px;
                    color:#777d87;
                ">
                    RMMC-MI Finance Queuing Management System
                </p>

            </div>

            <div style="padding:35px 30px;">

                <p style="
                    color:#c8102e;
                    font-size:12px;
                    font-weight:bold;
                    letter-spacing:1px;
                ">
                    PASSWORD RESET
                </p>

                <h2 style="
                    margin:10px 0 18px;
                    font-size:24px;
                ">
                    Reset your password
                </h2>

                <p style="
                    font-size:15px;
                    line-height:1.7;
                    color:#555;
                ">
                    Hello ' . $safeFirstName . ',
                </p>

                <p style="
                    font-size:15px;
                    line-height:1.7;
                    color:#555;
                ">
                    We received a request to reset your
                    QueueFlow account password. Click
                    the button below to create a new password.
                </p>

                <div style="
                    text-align:center;
                    margin:30px 0;
                ">

                    <a href="' . $safeLink . '"
                        style="
                            display:inline-block;
                            background:#c8102e;
                            color:#ffffff;
                            text-decoration:none;
                            padding:14px 28px;
                            border-radius:6px;
                            font-weight:bold;
                        ">
                        Reset Password
                    </a>

                </div>

                <p style="
                    font-size:13px;
                    line-height:1.7;
                    color:#777d87;
                ">
                    This password reset link expires
                    in 30 minutes.
                </p>

                <p style="
                    font-size:13px;
                    line-height:1.7;
                    color:#777d87;
                ">
                    If you did not request a password
                    reset, you can safely ignore this email.
                    Your password will remain unchanged.
                </p>

            </div>

            <div style="
                padding:20px 30px;
                background:#f7f7f9;
                text-align:center;
            ">

                <p style="
                    margin:0;
                    font-size:12px;
                    color:#777d87;
                ">
                    QueueFlow · RMMC-MI Finance Queuing Management System
                </p>

            </div>

        </div>

    </body>
</html>';

                    /*
                    |--------------------------------------------------------------------------
                    | SEND RESET EMAIL
                    |--------------------------------------------------------------------------
                    */

                    $sent = sendEmail(
                        $user['email'],
                        'QueueFlow: Reset Your Password',
                        $emailMessage
                    );

                    if ($sent) {

                        $success = 'Your password reset link has been sent successfully. Please check your inbox and spam folder.';

                        $email = '';

                    } else {

                        error_log(
                            'QueueFlow: Password reset email failed for user ID ' .
                            $userId
                        );

                        // Remove the unused token if sending fails.
                        $deleteFailed = $pdo->prepare('
                            DELETE FROM password_reset_tokens
                            WHERE user_id = ?
                              AND token_hash = ?
                        ');

                        $deleteFailed->execute([
                            $userId,
                            $tokenHash
                        ]);

                        $error = 'We could not send your password reset email. Please try again later or contact the administrator.';

                    }
                }
            }

        } catch (Throwable $exception) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'QueueFlow forgot password error: ' .
                $exception->getMessage()
            );

            $error = 'Something went wrong while processing your request. Please try again later.';

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

    <title>Forgot Password | QueueFlow</title>

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

                <h1>Forgot Password?</h1>

                <p>
                    Enter your registered email address to receive a password reset link.
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

            <?php if ($success !== '') { ?>

                <div class="success-message" role="status">

                    <?php echo htmlspecialchars(
                        $success,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>

                </div>

            <?php } ?>

            <form method="POST" action="forgot-password.php">

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

                    <label for="email">
                        Email Address
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="Enter your registered email"
                        autocomplete="email"
                        maxlength="100"
                        value="<?php echo htmlspecialchars(
                            $email,
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"
                        required
                        autofocus
                    >

                </div>

                <button class="button button-primary" type="submit">
                    Send Reset Link
                </button>

            </form>

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

</body>
</html>