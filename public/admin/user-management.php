<?php
require_once '../../includes/functions.php';
require_once '../../includes/send-email.php';

qflow_start_session();
qflow_require_role(['Administrator'], '/login.php');

$pdo = require '../../config/database.php';

$pageTitle = 'Account Management';
$message = '';
$error = '';

$envPath = dirname(__DIR__, 2) . '/.env';
$env = is_file($envPath)
    ? parse_ini_file($envPath, false, INI_SCANNER_RAW)
    : [];

$appUrl = rtrim(trim($env['QUEUEFLOW_APP_URL'] ?? ''), '/');

if ($appUrl === '') {
    $error = 'Application URL is missing from the .env file.';
}

if (isset($_GET['success'])) {
    if ($_GET['success'] === 'added') {
        $message = 'Student account created. The temporary password and verification link have been emailed.';
    } elseif ($_GET['success'] === 'updated') {
        $message = 'Student account updated successfully.';
    } elseif ($_GET['success'] === 'status') {
        $message = 'Student account status updated.';
    } elseif ($_GET['success'] === 'deleted') {
        $message = 'Student account permanently deleted successfully.';
    }
}

/*
|--------------------------------------------------------------------------
| CREATE STUDENT ACCOUNT
|--------------------------------------------------------------------------
*/

if (isset($_POST['add_student'])) {
    $accountId = trim($_POST['account_id'] ?? '');
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (
        $accountId === '' ||
        $firstName === '' ||
        $lastName === '' ||
        $email === ''
    ) {
        $error = 'Please complete all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($appUrl === '') {
        $error = 'Application URL is not configured.';
    } else {
        try {
            $temporaryPassword = 'Qf!' . bin2hex(random_bytes(6)) . 'aA';

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO users (
                    role_id,
                    account_id,
                    first_name,
                    last_name,
                    email,
                    password,
                    account_status,
                    email_verified_at,
                    must_change_password
                )
                VALUES (1, ?, ?, ?, ?, ?, 'pending', NULL, 1)
            ");

            $stmt->execute([
                $accountId,
                $firstName,
                $lastName,
                $email,
                password_hash($temporaryPassword, PASSWORD_DEFAULT)
            ]);

            $userId = (int) $pdo->lastInsertId();

            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expiresAt = date('Y-m-d H:i:s', time() + 1800);

            $stmt = $pdo->prepare("
                INSERT INTO email_verification_tokens (
                    user_id,
                    token_hash,
                    expires_at
                )
                VALUES (?, ?, ?)
            ");

            $stmt->execute([
                $userId,
                $tokenHash,
                $expiresAt
            ]);

            $verifyLink = $appUrl . '/verify-email.php?token=' . urlencode($token);

            $safeName = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
            $safeAccountId = htmlspecialchars($accountId, ENT_QUOTES, 'UTF-8');
            $safePassword = htmlspecialchars($temporaryPassword, ENT_QUOTES, 'UTF-8');
            $safeLink = htmlspecialchars($verifyLink, ENT_QUOTES, 'UTF-8');

            $subject = 'QueueFlow - Your Student Account';

            $body = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Your QueueFlow Account</title>
            </head>
            <body style="margin:0;padding:30px 15px;background:#f4f6f9;font-family:Arial,sans-serif;">
                <div style="max-width:550px;margin:auto;background:#ffffff;padding:32px;border-radius:12px;">

                    <h2 style="text-align:center;color:#c81920;">QUEUEFLOW</h2>

                    <h2 style="color:#19263b;">Welcome, ' . $safeName . '!</h2>

                    <p style="color:#475467;line-height:1.7;">
                        Your student account for the RMMC-MI Finance Office
                        Queue Management System has been created.
                    </p>

                    <h3 style="color:#19263b;">Your Login Credentials</h3>

                    <div style="background:#f8f9fb;padding:18px;border-radius:8px;">
                        <p style="margin:5px 0;color:#475467;">
                            <strong>Student ID:</strong> ' . $safeAccountId . '
                        </p>
                        <p style="margin:5px 0;color:#475467;">
                            <strong>Temporary Password:</strong>
                        </p>
                        <p style="margin:5px 0;color:#c81920;font-size:18px;font-weight:bold;letter-spacing:1px;">
                            ' . $safePassword . '
                        </p>
                    </div>

                    <p style="color:#b54708;font-size:13px;line-height:1.6;">
                        This is a temporary password. After verifying your email
                        and logging in, you will be required to create a new password.
                    </p>

                    <div style="text-align:center;margin:28px 0;">
                        <a href="' . $safeLink . '"
                           style="display:inline-block;background:#c81920;color:#ffffff;padding:14px 24px;border-radius:7px;text-decoration:none;font-weight:bold;">
                            Verify My Email
                        </a>
                    </div>

                    <p style="color:#667085;font-size:13px;line-height:1.6;">
                        This verification link expires in 30 minutes.
                    </p>

                    <p style="color:#667085;font-size:13px;">
                        If you did not expect this email, please contact the Finance Office.
                    </p>

                    <hr style="border:none;border-top:1px solid #eaecf0;margin:25px 0;">

                    <p style="color:#98a2b3;font-size:12px;text-align:center;">
                        QueueFlow | RMMC-MI Finance Office
                    </p>
                </div>
            </body>
            </html>';

            $sent = sendEmail($email, $subject, $body);

            if (!$sent) {
                throw new RuntimeException('The verification email could not be sent.');
            }

            $pdo->commit();

            header('Location: user-management.php?success=added');
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = $e instanceof PDOException
                ? 'Could not create the account. The Student ID or email may already be registered.'
                : 'Could not send the account email. Please check the email configuration and try again.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| UPDATE STUDENT DETAILS
|--------------------------------------------------------------------------
*/

if (isset($_POST['update_student'])) {
    $studentId = (int) ($_POST['student_id'] ?? 0);
    $accountId = trim($_POST['account_id'] ?? '');
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $newPassword = (string) ($_POST['password'] ?? '');

    if ($accountId === '' || $firstName === '' || $lastName === '' || $email === '') {
        $error = 'Please complete all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($newPassword !== '' && strlen($newPassword) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT email
                FROM users
                WHERE user_id = ? AND role_id = 1
            ");
            $stmt->execute([$studentId]);
            $oldEmail = $stmt->fetchColumn();

            if ($oldEmail === false) {
                throw new RuntimeException('Student account not found.');
            }

            $emailChanged = strcasecmp((string) $oldEmail, $email) !== 0;

            $pdo->beginTransaction();

            if ($newPassword !== '') {
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET account_id = ?,
                        first_name = ?,
                        last_name = ?,
                        email = ?,
                        password = ?,
                        must_change_password = 1
                    WHERE user_id = ? AND role_id = 1
                ");

                $stmt->execute([
                    $accountId,
                    $firstName,
                    $lastName,
                    $email,
                    password_hash($newPassword, PASSWORD_DEFAULT),
                    $studentId
                ]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET account_id = ?,
                        first_name = ?,
                        last_name = ?,
                        email = ?
                    WHERE user_id = ? AND role_id = 1
                ");

                $stmt->execute([
                    $accountId,
                    $firstName,
                    $lastName,
                    $email,
                    $studentId
                ]);
            }

            if ($emailChanged) {
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET email_verified_at = NULL,
                        account_status = 'pending'
                    WHERE user_id = ? AND role_id = 1
                ");
                $stmt->execute([$studentId]);

                $stmt = $pdo->prepare("
                    DELETE FROM email_verification_tokens
                    WHERE user_id = ?
                ");
                $stmt->execute([$studentId]);
            }

            $pdo->commit();

            header('Location: user-management.php?success=updated');
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = $e instanceof PDOException
                ? 'Could not update the account. The Student ID or email may already be in use.'
                : $e->getMessage();
        }
    }
}

/*
|--------------------------------------------------------------------------
| CHANGE ACCOUNT STATUS
|--------------------------------------------------------------------------
*/

if (isset($_POST['update_status'])) {
    $studentId = (int) ($_POST['student_id'] ?? 0);
    $status = $_POST['account_status'] ?? '';

    if (!in_array($status, ['active', 'inactive', 'locked'], true)) {
        $error = 'Invalid account status.';
    } else {
        if ($status === 'active') {
            $stmt = $pdo->prepare("
                SELECT email_verified_at
                FROM users
                WHERE user_id = ? AND role_id = 1
            ");
            $stmt->execute([$studentId]);

            if (!$stmt->fetchColumn()) {
                $error = 'This account cannot be activated because its email is not verified.';
            }
        }

        if ($error === '') {
            $stmt = $pdo->prepare("
                UPDATE users
                SET account_status = ?
                WHERE user_id = ? AND role_id = 1
            ");
            $stmt->execute([$status, $studentId]);

            header('Location: user-management.php?success=status');
            exit;
        }
    }
}

/*
|--------------------------------------------------------------------------
| DELETE STUDENT ACCOUNT
|--------------------------------------------------------------------------
*/

if (isset($_POST['delete_student'])) {

    $studentId = (int) ($_POST['student_id'] ?? 0);
    $csrfToken = $_POST['csrf_token'] ?? null;

    if (!qflow_verify_csrf($csrfToken)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif ($studentId <= 0) {
        $error = 'Invalid student account.';
    } else {
        try {
            $pdo->beginTransaction();

            // Confirm that the selected account is a student.
            $stmt = $pdo->prepare("
                SELECT user_id
                FROM users
                WHERE user_id = ?
                  AND role_id = 1
                FOR UPDATE
            ");

            $stmt->execute([$studentId]);

            if (!$stmt->fetchColumn()) {
                throw new RuntimeException('Student account not found.');
            }

            // Remove the student's reference from queue status history.
            $stmt = $pdo->prepare("
                DELETE FROM queue_status
                WHERE changed_by = ?
            ");
            $stmt->execute([$studentId]);

            // Preserve queue tickets but remove their creator reference.
            $stmt = $pdo->prepare("
                UPDATE queues
                SET created_by = NULL
                WHERE created_by = ?
            ");
            $stmt->execute([$studentId]);

            // Delete email verification tokens.
            $stmt = $pdo->prepare("
                DELETE FROM email_verification_tokens
                WHERE user_id = ?
            ");
            $stmt->execute([$studentId]);

            // Delete the student account.
            $stmt = $pdo->prepare("
                DELETE FROM users
                WHERE user_id = ?
                  AND role_id = 1
            ");
            $stmt->execute([$studentId]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException(
                    'The student account could not be deleted.'
                );
            }

            $pdo->commit();

            header('Location: user-management.php?success=deleted');
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'QueueFlow student deletion error: ' . $e->getMessage()
            );

            $error = $e instanceof PDOException
                ? 'Could not delete the student account. Database error: ' . $e->getMessage()
                : $e->getMessage();
        }
    }
}

/*
|--------------------------------------------------------------------------
| LOAD EDIT ACCOUNT
|--------------------------------------------------------------------------
*/

$editStudent = null;

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("
        SELECT user_id, account_id, first_name, last_name, email
        FROM users
        WHERE user_id = ? AND role_id = 1
    ");
    $stmt->execute([(int) $_GET['edit']]);
    $editStudent = $stmt->fetch(PDO::FETCH_ASSOC);
}

/*
|--------------------------------------------------------------------------
| SEARCH AND LIST STUDENTS
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');
$searchLike = '%' . $search . '%';

if ($search !== '') {
    $stmt = $pdo->prepare("
        SELECT user_id, account_id, first_name, last_name, email,
               account_status, email_verified_at, created_at
        FROM users
        WHERE role_id = 1
          AND (
              account_id LIKE ?
              OR first_name LIKE ?
              OR last_name LIKE ?
              OR email LIKE ?
          )
        ORDER BY last_name, first_name
    ");
    $stmt->execute([$searchLike, $searchLike, $searchLike, $searchLike]);
} else {
    $stmt = $pdo->query("
        SELECT user_id, account_id, first_name, last_name, email,
               account_status, email_verified_at, created_at
        FROM users
        WHERE role_id = 1
        ORDER BY last_name, first_name
    ");
}

$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalStudents = (int) $pdo->query("
    SELECT COUNT(*) FROM users WHERE role_id = 1
")->fetchColumn();

$activeStudents = (int) $pdo->query("
    SELECT COUNT(*) FROM users
    WHERE role_id = 1 AND account_status = 'active'
")->fetchColumn();

$pendingStudents = (int) $pdo->query("
    SELECT COUNT(*) FROM users
    WHERE role_id = 1 AND account_status = 'pending'
")->fetchColumn();

$inactiveStudents = (int) $pdo->query("
    SELECT COUNT(*) FROM users
    WHERE role_id = 1 AND account_status IN ('inactive', 'locked')
")->fetchColumn();

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<style>
.accounts-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:18px}
.accounts-stat{background:#fff;border:1px solid #e1e6ed;border-radius:12px;padding:16px 18px}
.accounts-stat span{display:block;color:#68758a;font-size:13px;margin-bottom:7px}
.accounts-stat strong{font-size:27px;color:#19263b}
.accounts-search{display:flex;gap:10px;align-items:center;margin-bottom:16px;flex-wrap:wrap}
.accounts-search input{flex:1;min-width:200px;height:42px;border:1px solid #d6dce5;border-radius:8px;padding:0 12px;font:inherit}
.accounts-table-wrap{overflow-x:auto}
.accounts-table{width:100%;border-collapse:collapse;min-width:900px}
.accounts-table th,.accounts-table td{padding:12px 13px;text-align:left;border-bottom:1px solid #edf0f4;font-size:13px;vertical-align:middle}
.accounts-table th{background:#f8f9fb;color:#667085;font-weight:700;white-space:nowrap}
.accounts-status{display:inline-flex;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700;text-transform:capitalize}
.accounts-status.active{background:#e8f7ed;color:#167344}
.accounts-status.pending{background:#fff4d6;color:#9a6700}
.accounts-status.inactive{background:#f1f3f5;color:#596273}
.accounts-status.locked{background:#fff0f0;color:#b4232d}
.accounts-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.accounts-actions form{margin:0}
.accounts-link{color:#c81920;font-weight:700;text-decoration:none}
.accounts-actions button{border:1px solid #d9dee7;border-radius:7px;background:#fff;padding:7px 10px;cursor:pointer;font:inherit;font-size:12px}
.accounts-actions button:hover{border-color:#c81920;color:#c81920}
.accounts-actions .danger{color:#c81920}
.accounts-actions .delete-button{background:#fff0f0;border-color:#f3c5c8;color:#c81920}
.accounts-actions .delete-button:hover{background:#c81920;border-color:#c81920;color:#fff}
.email-verified{color:#167344;font-weight:700}
.email-unverified{color:#a15c00;font-weight:700}
@media(max-width:800px){.accounts-stats{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:500px){.accounts-stats{grid-template-columns:1fr}}
</style>

<main class="main-content staff-page">

    <div class="staff-page-header">
        <div>
            <h1>Account Management</h1>
            <p>Create student accounts, update details, and manage account access.</p>
        </div>
    </div>

    <?php if ($message !== ''): ?>
        <div class="staff-alert success" role="status">
            <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="staff-alert error" role="alert">
            <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <div class="accounts-stats">
        <div class="accounts-stat">
            <span>Total students</span>
            <strong><?php echo $totalStudents; ?></strong>
        </div>

        <div class="accounts-stat">
            <span>Active</span>
            <strong><?php echo $activeStudents; ?></strong>
        </div>

        <div class="accounts-stat">
            <span>Pending verification</span>
            <strong><?php echo $pendingStudents; ?></strong>
        </div>

        <div class="accounts-stat">
            <span>Inactive / locked</span>
            <strong><?php echo $inactiveStudents; ?></strong>
        </div>
    </div>

    <section class="staff-card" style="margin-bottom:18px;">

        <div class="staff-card-heading">
            <div>
                <h2>
                    <?php echo $editStudent ? 'Edit Student Account' : 'Add Student Account'; ?>
                </h2>

                <p>
                    <?php echo $editStudent
                        ? 'Update the student details. Leave the password blank to keep the current password.'
                        : 'A temporary password will be generated and sent to the student email.'; ?>
                </p>
            </div>

            <?php if ($editStudent): ?>
                <a class="staff-button secondary" href="user-management.php">
                    Cancel edit
                </a>
            <?php endif; ?>
        </div>

        <form
            method="POST"
            action="user-management.php<?php echo $editStudent ? '?edit=' . (int) $editStudent['user_id'] : ''; ?>"
            class="staff-form"
        >

            <?php if ($editStudent): ?>
                <input
                    type="hidden"
                    name="student_id"
                    value="<?php echo (int) $editStudent['user_id']; ?>"
                >
            <?php endif; ?>

            <div class="staff-form-grid">

                <div class="staff-field">
                    <label for="account_id">Student ID / Account ID *</label>
                    <input
                        id="account_id"
                        name="account_id"
                        type="text"
                        required
                        value="<?php echo htmlspecialchars($editStudent['account_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    >
                </div>

                <div class="staff-field">
                    <label for="first_name">First name *</label>
                    <input
                        id="first_name"
                        name="first_name"
                        type="text"
                        required
                        value="<?php echo htmlspecialchars($editStudent['first_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    >
                </div>

                <div class="staff-field">
                    <label for="last_name">Last name *</label>
                    <input
                        id="last_name"
                        name="last_name"
                        type="text"
                        required
                        value="<?php echo htmlspecialchars($editStudent['last_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    >
                </div>

                <div class="staff-field">
                    <label for="email">Email address *</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        required
                        value="<?php echo htmlspecialchars($editStudent['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    >
                </div>

                <?php if ($editStudent): ?>
                    <div class="staff-field staff-field-full">
                        <label for="password">New password (optional)</label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            minlength="8"
                            autocomplete="new-password"
                            placeholder="Leave blank to keep current password"
                        >
                    </div>
                <?php endif; ?>

            </div>

            <div class="staff-form-actions">
                <button
                    class="staff-button primary"
                    type="submit"
                    name="<?php echo $editStudent ? 'update_student' : 'add_student'; ?>"
                >
                    <?php echo $editStudent ? 'Save Changes' : 'Create Student Account'; ?>
                </button>
            </div>

        </form>

    </section>

    <section class="staff-card">

        <div class="staff-card-heading">
            <div>
                <h2>Student Accounts</h2>
                <p>Search and manage student accounts.</p>
            </div>
        </div>

        <form class="accounts-search" method="GET" action="user-management.php">

            <input
                type="text"
                name="search"
                value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>"
                placeholder="Search by student ID, name, or email..."
            >

            <button type="submit" class="staff-button primary">
                Search
            </button>

            <?php if ($search !== ''): ?>
                <a class="staff-button secondary" href="user-management.php">
                    Clear
                </a>
            <?php endif; ?>

        </form>

        <div class="accounts-table-wrap">

            <table class="accounts-table">

                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Email verification</th>
                        <th>Account status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if ($students): ?>

                        <?php foreach ($students as $student): ?>

                            <tr>

                                <td>
                                    <?php echo htmlspecialchars($student['account_id'], ENT_QUOTES, 'UTF-8'); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars(
                                        $student['first_name'] . ' ' . $student['last_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($student['email'] ?? '—', ENT_QUOTES, 'UTF-8'); ?>
                                </td>

                                <td>
                                    <?php if ($student['email_verified_at']): ?>
                                        <span class="email-verified">Verified</span>
                                    <?php else: ?>
                                        <span class="email-unverified">Not verified</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <span class="accounts-status <?php echo htmlspecialchars($student['account_status'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($student['account_status'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($student['created_at'] ?? '—', ENT_QUOTES, 'UTF-8'); ?>
                                </td>

                                <td>

                                    <div class="accounts-actions">

                                        <a
                                            class="accounts-link"
                                            href="user-management.php?edit=<?php echo (int) $student['user_id']; ?>"
                                        >
                                            Edit
                                        </a>

                                        <?php if ($student['account_status'] === 'active'): ?>

                                            <form method="POST" action="user-management.php">

                                                <input
                                                    type="hidden"
                                                    name="student_id"
                                                    value="<?php echo (int) $student['user_id']; ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="account_status"
                                                    value="inactive"
                                                >

                                                <button
                                                    class="danger"
                                                    type="submit"
                                                    name="update_status"
                                                    onclick="return confirm('Deactivate this student account?');"
                                                >
                                                    Deactivate
                                                </button>

                                            </form>

                                        <?php elseif ($student['account_status'] !== 'pending'): ?>

                                            <form method="POST" action="user-management.php">

                                                <input
                                                    type="hidden"
                                                    name="student_id"
                                                    value="<?php echo (int) $student['user_id']; ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="account_status"
                                                    value="active"
                                                >

                                                <button type="submit" name="update_status">
                                                    Activate
                                                </button>

                                            </form>

                                        <?php endif; ?>

                                        <?php if ($student['account_status'] !== 'locked'): ?>

                                            <form method="POST" action="user-management.php">

                                                <input
                                                    type="hidden"
                                                    name="student_id"
                                                    value="<?php echo (int) $student['user_id']; ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="account_status"
                                                    value="locked"
                                                >

                                                <button
                                                    class="danger"
                                                    type="submit"
                                                    name="update_status"
                                                    onclick="return confirm('Lock this student account?');"
                                                >
                                                    Lock
                                                </button>

                                            </form>

                                        <?php endif; ?>

                                        <!-- PERMANENT DELETE BUTTON -->
                                        <form
                                            method="POST"
                                            action="user-management.php"
                                            onsubmit="return confirm('PERMANENTLY DELETE this student account? This action cannot be undone.');"
                                        >

                                            <input
                                                type="hidden"
                                                name="student_id"
                                                value="<?php echo (int) $student['user_id']; ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?php echo htmlspecialchars(qflow_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="delete_student"
                                                class="delete-button"
                                            >
                                                Delete
                                            </button>

                                        </form>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="7">No student accounts found.</td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</main>

<?php require_once '../../includes/footer.php'; ?>