
<?php

// Load environment variables from the project root .env file.
$envFile = dirname(__DIR__) . '/.env';

if (file_exists($envFile)) {
    $envVariables = parse_ini_file($envFile, false, INI_SCANNER_RAW);

    if ($envVariables !== false) {
        foreach ($envVariables as $key => $value) {
            if (!isset($_ENV[$key]) && getenv($key) === false) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
}

require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/send-email.php';

qflow_start_session();

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$pdo = require dirname(__DIR__) . '/config/database.php';

$error = '';
$success = '';

$accountId = '';
$firstName = '';
$lastName = '';
$email = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (
        isset($_POST["account_id"]) &&
        isset($_POST["password"]) &&
        isset($_POST["csrf_token"]) &&
        isset($_POST["last_name"]) &&
        isset($_POST["first_name"]) &&
        isset($_POST["email"]) &&
        isset($_POST["confirm_password"]) &&
        isset($_POST["terms"])
    ) {

        $firstName = trim($_POST["first_name"]);
        $lastName = trim($_POST["last_name"]);
        $email = trim($_POST["email"]);
        $accountId = trim($_POST["account_id"]);

        $password = $_POST["password"];
        $confirmPassword = $_POST["confirm_password"];
        $csrfToken = $_POST["csrf_token"];
        $termsAccepted = $_POST["terms"];

        if (qflow_verify_csrf($csrfToken)) {

            if (strlen($accountId) < 8) {
                $error = "Your student ID must contain at least 8 characters.";

            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Your email address is invalid. Please enter a valid email address.";

            } elseif (strlen($password) < 8) {
                $error = "Your password must contain at least 8 characters.";

            } elseif ($password !== $confirmPassword) {
                $error = "Your password does not match. Please try again.";

            } elseif ($termsAccepted !== "on" && $termsAccepted !== "1") {
                $error = "Please agree to the Terms and Conditions and Privacy Policy.";

            } else {

                $roleCheck = $pdo->prepare(
                    'SELECT role_id
                     FROM roles
                     WHERE role_name = ?
                     LIMIT 1'
                );

                $roleCheck->execute(['Student']);
                $studentRoleId = $roleCheck->fetchColumn();

                if ($studentRoleId) {

                    $appUrl = rtrim(
                        (string) getenv('QUEUEFLOW_APP_URL'),
                        '/'
                    );

                    if ($appUrl === '') {
                        $error = 'The application email verification URL is not configured. Please contact the administrator.';

                    } else {

                        try {

                            $hashedPassword = password_hash(
                                $password,
                                PASSWORD_DEFAULT
                            );

                            // Generate a secure verification token.
                            $token = bin2hex(random_bytes(32));
                            $tokenHash = hash('sha256', $token);

                            $expiresAt = date(
                                'Y-m-d H:i:s',
                                time() + (30 * 60)
                            );

                            $pdo->beginTransaction();

                            // Create the student account.
                            // Students must verify their email before activation.
                            $insert = $pdo->prepare(
                                'INSERT INTO users (
                                    role_id,
                                    account_id,
                                    first_name,
                                    last_name,
                                    email,
                                    password,
                                    account_status,
                                    email_verified_at,
                                    must_change_password
                                ) VALUES (?, ?, ?, ?, ?, ?, ?, NULL, 0)'
                            );

                            $insert->execute([
                                (int) $studentRoleId,
                                $accountId,
                                $firstName,
                                $lastName,
                                $email,
                                $hashedPassword,
                                'pending'
                            ]);

                            $userId = (int) $pdo->lastInsertId();

                            // Store the hashed verification token.
                            $tokenInsert = $pdo->prepare(
                                'INSERT INTO email_verification_tokens (
                                    user_id,
                                    token_hash,
                                    expires_at
                                ) VALUES (?, ?, ?)'
                            );

                            $tokenInsert->execute([
                                $userId,
                                $tokenHash,
                                $expiresAt
                            ]);

                            $pdo->commit();

                            // Construct the verification link.
                            $verificationLink = $appUrl .
                                '/verify-email.php?token=' .
                                urlencode($token);

                            $safeFirstName = htmlspecialchars(
                                $firstName,
                                ENT_QUOTES,
                                'UTF-8'
                            );

                            $safeLink = htmlspecialchars(
                                $verificationLink,
                                ENT_QUOTES,
                                'UTF-8'
                            );

                            // Verification email.
                            $emailMessage = '
                            <!DOCTYPE html>
                            <html>
                            <head>
                                <meta charset="UTF-8">
                            </head>

                            <body style="margin:0;padding:30px 15px;background:#f7f7f9;font-family:Arial,sans-serif;color:#202124;">

                                <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e9e9ed;">

                                    <div style="padding:28px 30px;text-align:center;border-bottom:1px solid #e9e9ed;">
                                        <h2 style="margin:0;color:#c8102e;">QueueFlow</h2>

                                        <p style="margin:8px 0 0;color:#737780;font-size:13px;">
                                            RMMC-MI Finance Queuing Management System
                                        </p>
                                    </div>

                                    <div style="padding:35px 30px;">

                                        <p style="font-size:13px;color:#c8102e;font-weight:bold;letter-spacing:1px;">
                                            EMAIL VERIFICATION
                                        </p>

                                        <h2 style="font-size:24px;margin:10px 0 18px;">
                                            Verify your email address
                                        </h2>

                                        <p style="font-size:15px;line-height:1.7;color:#555;">
                                            Hello ' . $safeFirstName . ',
                                        </p>

                                        <p style="font-size:15px;line-height:1.7;color:#555;">
                                            Thank you for registering with QueueFlow!
                                            To complete your student account registration,
                                            please verify your email address by clicking
                                            the button below.
                                        </p>

                                        <div style="text-align:center;margin:30px 0;">
                                            <a
                                                href="' . $safeLink . '"
                                                style="display:inline-block;background:#c8102e;color:#ffffff;text-decoration:none;padding:14px 28px;border-radius:6px;font-weight:bold;"
                                            >
                                                Verify Email Address
                                            </a>
                                        </div>

                                        <p style="font-size:13px;line-height:1.7;color:#737780;">
                                            This verification link will expire in
                                            30 minutes.
                                        </p>

                                        <p style="font-size:13px;line-height:1.7;color:#737780;">
                                            If the button does not work, copy and
                                            paste this link into your browser:
                                            <br><br>

                                            <a
                                                href="' . $safeLink . '"
                                                style="color:#c8102e;word-break:break-all;"
                                            >
                                                ' . $safeLink . '
                                            </a>
                                        </p>

                                        <p style="font-size:13px;line-height:1.7;color:#737780;">
                                            If you did not create this account,
                                            you can safely ignore this email.
                                        </p>

                                    </div>

                                    <div style="padding:20px 30px;background:#f7f7f9;text-align:center;">
                                        <p style="margin:0;font-size:12px;color:#737780;">
                                            QueueFlow · RMMC-MI Finance Queuing Management System
                                        </p>
                                    </div>

                                </div>

                            </body>
                            </html>';

                            // Send the verification email.
                            $emailSent = sendEmail(
                                $email,
                                'QueueFlow: Verify Your Email Address',
                                $emailMessage
                            );

                            if ($emailSent) {

                                $success = 'Your account has been created! We sent a verification link to your email address. Please check your inbox and spam folder.';

                                $accountId = '';
                                $firstName = '';
                                $lastName = '';
                                $email = '';

                            } else {

                                error_log(
                                    'QueueFlow: Registration verification email failed for user ID ' .
                                    $userId
                                );

                                $error = 'Your account was created, but we could not send the verification email. Please use the resend verification option to try again.';

                            }

                        } catch (PDOException $exception) {

                            if ($pdo->inTransaction()) {
                                $pdo->rollBack();
                            }

                            if ($exception->getCode() === '23000') {
                                $error = "Your student ID or email address is already being used. Please try another one.";

                            } else {

                                error_log(
                                    'QFlow registration error: ' .
                                    $exception->getMessage()
                                );

                                $error = 'Registration could not be completed. Please try again.';
                            }

                        } catch (Throwable $exception) {

                            if ($pdo->inTransaction()) {
                                $pdo->rollBack();
                            }

                            error_log(
                                'QFlow registration error: ' .
                                $exception->getMessage()
                            );

                            $error = 'Registration could not be completed. Please try again.';
                        }
                    }

                } else {
                    $error = "Student role is not configured yet. Please contact the administrator.";
                }
            }

        } else {
            $error = "Your session has expired. Please refresh the page and try again.";
        }

    } else {
        $error = "Please fill in all required details before you continue.";
    }
}

$csrfToken = qflow_csrf_token();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="icon" type="image/x-icon" href="/assets/img/icon.png">
    <link rel="stylesheet" href="assets/css/register.css">

    <title>Register</title>
</head>

<body>

<main class="register-page">
    <div class="register-container">

        <div class="brand">
            <img src="assets/img/icon.png" alt="QueueFlow logo" class="brand-logo">
        </div>

        <section class="heading">
            <h1>Let's get registered!</h1>
            <p>Fill in the required details to get started!</p>
        </section>

        <?php if (!empty($error)) { ?>

            <div class="message message-error" role="alert">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>

                <?php if (str_contains($error, 'could not send')) { ?>
                    <p>
                        <a href="resend-verification.php">
                            Resend verification email
                        </a>
                    </p>
                <?php } ?>
            </div>

        <?php } elseif (!empty($success)) { ?>

            <div class="message message-success" role="status">
                <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
            </div>

        <?php } ?>

        <form method="post" action="register.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>"
            >

            <div class="form-group">
                <label for="account_id">Student ID</label>

                <input
                    type="text"
                    id="account_id"
                    name="account_id"
                    placeholder="Enter your student ID"
                    maxlength="50"
                    autocomplete="username"
                    value="<?php echo htmlspecialchars($accountId, ENT_QUOTES, 'UTF-8'); ?>"
                    required
                >
            </div>

            <div class="form-row">

                <div class="form-group">
                    <label for="first_name">First Name</label>

                    <input
                        type="text"
                        id="first_name"
                        name="first_name"
                        placeholder="Enter your first name"
                        maxlength="100"
                        autocomplete="given-name"
                        value="<?php echo htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8'); ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="last_name">Last Name</label>

                    <input
                        type="text"
                        id="last_name"
                        name="last_name"
                        placeholder="Enter your last name"
                        maxlength="100"
                        autocomplete="family-name"
                        value="<?php echo htmlspecialchars($lastName, ENT_QUOTES, 'UTF-8'); ?>"
                        required
                    >
                </div>

            </div>

            <div class="form-group">
                <label for="email">Email Address</label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Enter your email address"
                    maxlength="254"
                    autocomplete="email"
                    value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>

                <div class="password-wrapper">

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Create your password"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >

                    <button
                        type="button"
                        class="toggle-password"
                        data-target="password"
                    >
                        Show
                    </button>

                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>

                <div class="password-wrapper">

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        placeholder="Confirm your password"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >

                    <button
                        type="button"
                        class="toggle-password"
                        data-target="confirm_password"
                    >
                        Show
                    </button>

                </div>
            </div>

            <div class="terms">

                <input
                    type="checkbox"
                    id="terms"
                    name="terms"
                    value="1"
                    required
                >

                <label for="terms">
                    I agree to the
                    <a href="#" onclick="return false;">Terms and Conditions</a>
                    and
                    <a href="#" onclick="return false;">Privacy Policy</a>.
                </label>

            </div>

            <button type="submit" class="button button-primary">
                Register
            </button>

        </form>

        <div class="divider">
            <span>or</span>
        </div>

        <a href="login.php" class="button button-secondary">
            Already have an account? Login
        </a>

        <p class="footer-note">
            QueueFlow · RMMC-MI Finance Queuing Management System
        </p>

    </div>
</main>

<script>
    document.querySelectorAll('.toggle-password').forEach(function (button) {

        button.addEventListener('click', function () {

            const input = document.getElementById(button.dataset.target);
            const showPassword = input.type === 'password';

            input.type = showPassword ? 'text' : 'password';
            button.textContent = showPassword ? 'Hide' : 'Show';

        });

    });

    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');

    confirmPassword.addEventListener('input', function () {

        confirmPassword.setCustomValidity(
            confirmPassword.value !== password.value
                ? 'Passwords do not match.'
                : ''
        );

    });

    password.addEventListener('input', function () {

        if (confirmPassword.value !== '') {

            confirmPassword.setCustomValidity(
                confirmPassword.value !== password.value
                    ? 'Passwords do not match.'
                    : ''
            );

        }

    });
</script>

</body>
</html>
