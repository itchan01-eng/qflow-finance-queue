
<?php

require_once '../../includes/functions.php';

qflow_start_session();

$pdo = require '../../config/database.php';

$error = '';
$success = '';

$accountId = '';
$csrfToken = qflow_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        !qflow_verify_csrf($submittedToken)
    ) {

        $error = 'Your session has expired. Please refresh the page and try again.';

    } else {

        $accountId = trim($_POST['account_id'] ?? '');

        if ($accountId === '') {

            $error = 'Please enter your Student ID.';

        } else {

            try {

                $stmt = $pdo->prepare('
                    SELECT
                        user_id,
                        email,
                        email_verified_at,
                        account_status
                    FROM users
                    WHERE account_id = ?
                    LIMIT 1
                ');

                $stmt->execute([$accountId]);

                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user) {

                    $error = 'We could not find an account with that Student ID.';

                } elseif (!empty($user['email_verified_at'])) {

                    $error = 'Your email address is already verified. You can log in to your account.';

                } else {

                    $userId = (int) $user['user_id'];

                    // Generate a secure verification token.
                    $token = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $token);

                    // Remove previous verification tokens.
                    $delete = $pdo->prepare('
                        DELETE FROM email_verification_tokens
                        WHERE user_id = ?
                    ');

                    $delete->execute([$userId]);

                    // Create a new verification token.
                    $expiresAt = date(
                        'Y-m-d H:i:s',
                        time() + 3600
                    );

                    $insert = $pdo->prepare('
                        INSERT INTO email_verification_tokens
                            (user_id, token_hash, expires_at)
                        VALUES (?, ?, ?)
                    ');

                    $insert->execute([
                        $userId,
                        $tokenHash,
                        $expiresAt
                    ]);

                    // Build the verification URL.
                    $verificationUrl =
                        'https://' .
                        $_SERVER['HTTP_HOST'] .
                        '/verify-email.php?token=' .
                        urlencode($token);

                    /*
                     * IMPORTANT:
                     * Replace the email-sending section below
                     * with your existing QueueFlow email function
                     * if your project uses PHPMailer or another
                     * mail service.
                     */

                    $subject = 'QueueFlow Email Verification';

                    $message = '
                        <html>
                        <body>
                            <h2>Verify Your Email Address</h2>
                            <p>Hello,</p>
                            <p>Please click the link below to verify your QueueFlow account:</p>
                            <p>
                                <a href="' . htmlspecialchars(
                                    $verificationUrl,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) . '">
                                    Verify My Email
                                </a>
                            </p>
                            <p>This link will expire in 1 hour.</p>
                            <p>If you did not create this account, you may ignore this email.</p>
                            <br>
                            <p>QueueFlow<br>RMMC-MI Finance Queuing Management System</p>
                        </body>
                        </html>
                    ';

                    $headers = [
                        'MIME-Version: 1.0',
                        'Content-type: text/html; charset=UTF-8',
                        'From: QueueFlow <no-reply@yourdomain.com>'
                    ];

                    $mailSent = mail(
                        $user['email'],
                        $subject,
                        $message,
                        implode("\r\n", $headers)
                    );

                    if ($mailSent) {

                        $success = 'A new verification link has been sent to your registered email address. Please check your inbox.';

                        $accountId = '';

                    } else {

                        // Remove the token if email delivery fails.
                        $delete = $pdo->prepare('
                            DELETE FROM email_verification_tokens
                            WHERE user_id = ?
                        ');

                        $delete->execute([$userId]);

                        $error = 'We could not send your verification email. Please try again later.';

                    }
                }

            } catch (Throwable $exception) {

                error_log(
                    'QueueFlow resend verification error: ' .
                    $exception->getMessage()
                );

                $error = 'Something went wrong while processing your request. Please try again later.';
            }
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

    <title>Resend Verification | QueueFlow</title>
</head>

<body>

<main class="login-page">

    <div class="login-container">

        <div class="brand">
            <img
                src="../assets/img/icon.png"
                alt="QueueFlow Logo"
                class="brand-logo"
            >
        </div>

        <section class="heading">
            <h1>Email Verification</h1>
            <p>
                Enter your Student ID to receive a new
                verification link for your account.
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

        <form method="POST" action="resend-verification.php">

            <!-- Corrected CSRF token output -->
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
                    placeholder="Enter your Student ID"
                    maxlength="50"
                    autocomplete="username"
                    value="<?php echo htmlspecialchars(
                        $accountId,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>"
                    required
                >

            </div>

            <button
                class="button button-primary"
                type="submit"
            >
                Resend Verification Email
            </button>

        </form>

        <div class="divider">
            <span>or</span>
        </div>

        <a
            class="button button-secondary"
            href="../login.php"
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
