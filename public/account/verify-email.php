
<?php

require_once '../../includes/functions.php';

qflow_start_session();

$pdo = require '../../config/database.php';

$message = '';
$error = '';

$token = trim((string) ($_GET['token'] ?? ''));

$accountType = '';
$successTitle = 'Email Verified!';
$successSubtitle = 'Your email verification is complete.';

/*
|--------------------------------------------------------------------------
| VERIFY EMAIL TOKEN
|--------------------------------------------------------------------------
*/

if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {

    $error = 'Invalid verification link.';

} else {

    $tokenHash = hash('sha256', $token);

    try {

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT
                t.user_id,
                t.expires_at,
                u.role_id,
                u.account_id,
                u.email_verified_at,
                u.account_status
            FROM email_verification_tokens t
            INNER JOIN users u ON u.user_id = t.user_id
            WHERE t.token_hash = ?
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([$tokenHash]);

        $verification = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$verification) {

            $pdo->rollBack();

            $error = 'This verification link is invalid or has already been used.';

        } elseif (!in_array((int) $verification['role_id'], [1, 2], true)) {

            $pdo->rollBack();

            $error = 'This account type cannot use this verification link. Please contact the administrator.';

        } elseif (strtotime($verification['expires_at']) < time()) {

            $stmt = $pdo->prepare("
                DELETE FROM email_verification_tokens
                WHERE token_hash = ?
            ");

            $stmt->execute([$tokenHash]);

            $pdo->commit();

            $error = 'This verification link has expired. Please contact the administrator for a new link.';

        } elseif (!empty($verification['email_verified_at'])) {

            $stmt = $pdo->prepare("
                DELETE FROM email_verification_tokens
                WHERE token_hash = ?
            ");

            $stmt->execute([$tokenHash]);

            $pdo->commit();

            $accountType = (int) $verification['role_id'] === 1
                ? 'student'
                : 'staff';

            $message = 'Your email address has already been verified. You may log in.';

        } elseif ($verification['account_status'] !== 'pending') {

            $pdo->rollBack();

            $error = 'This account cannot be verified in its current status. Please contact the administrator.';

        } else {

            $stmt = $pdo->prepare("
                UPDATE users
                SET
                    email_verified_at = NOW(),
                    account_status = 'active'
                WHERE user_id = ?
                  AND account_status = 'pending'
                  AND email_verified_at IS NULL
            ");

            $stmt->execute([
                (int) $verification['user_id']
            ]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException(
                    'The account could not be verified.'
                );
            }

            $stmt = $pdo->prepare("
                DELETE FROM email_verification_tokens
                WHERE token_hash = ?
            ");

            $stmt->execute([$tokenHash]);

            $pdo->commit();

            // Identify the account type.
            $accountType = (int) $verification['role_id'] === 1
                ? 'student'
                : 'staff';

            // Different messages for students and staff.
            if ($accountType === 'student') {

                $message = 'Your email has been verified successfully. You can now log in using your Student ID and temporary password. You will be required to create a new password.';

            } else {

                $message = 'Your email has been verified successfully. You can now log in using your Staff ID and temporary password. You will be required to create a new password.';

            }
        }

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            'QueueFlow email verification error: ' . $e->getMessage()
        );

        $error = 'Something went wrong while verifying your email. Please try again.';
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="icon" type="image/x-icon" href="../assets/img/icon.png">
    <link rel="stylesheet" href="../assets/css/login.css">

    <title>Email Verification | QueueFlow</title>

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

            <?php if ($message !== '') { ?>

                <h1><?php echo qflow_escape($successTitle); ?></h1>

                <p>
                    <?php echo qflow_escape($successSubtitle); ?>
                </p>

            <?php } else { ?>

                <h1>Verification Failed</h1>

                <p>
                    We couldn't verify your email address.
                </p>

            <?php } ?>

        </section>


        <?php if ($message !== '') { ?>

            <div
                class="success-message"
                role="status"
                style="margin-bottom: 20px;"
            >

                <?php echo qflow_escape($message); ?>

            </div>

            <a
                class="button button-primary"
                href="../login.php"
                style="display: block; text-decoration: none; text-align: center;"
            >
                Proceed to Login
            </a>

        <?php } else { ?>

            <div
                class="error-message"
                role="alert"
                style="margin-bottom: 20px;"
            >

                <?php echo qflow_escape($error); ?>

            </div>

            <a
                class="button button-primary"
                href="../login.php"
                style="display: block; text-decoration: none; text-align: center;"
            >
                Back to Login
            </a>

        <?php } ?>


        <p class="footer-note">
            QueueFlow · RMMC-MI Finance Queuing Management System
        </p>

    </div>

</main>

</body>
</html>
