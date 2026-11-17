
<?php
require_once '../../includes/functions.php';
require_once '../../includes/send-email.php';

qflow_start_session();
qflow_require_role(['Administrator'], '/login.php');

$pdo = require '../../config/database.php';

$pageTitle = 'Staff Management';
$message = '';
$error = '';


// =====================================================
// APPLICATION URL
// =====================================================

$envPath = dirname(__DIR__, 2) . '/.env';

$env = is_file($envPath)
    ? parse_ini_file($envPath, false, INI_SCANNER_RAW)
    : [];

$appUrl = rtrim(trim($env['QUEUEFLOW_APP_URL'] ?? ''), '/');

if ($appUrl === '') {
    $error = 'Application URL is missing from the .env file.';
}


// =====================================================
// ACTIVE SERVICES
// =====================================================

$stmt = $pdo->query("
    SELECT service_id, service_name
    FROM services
    WHERE is_active = 1
    ORDER BY service_name
");

$servicesList = $stmt->fetchAll(PDO::FETCH_ASSOC);


// =====================================================
// SUCCESS MESSAGES
// =====================================================

if (isset($_GET['success'])) {

    $successMessages = [
        'added' => 'Staff account created successfully. Verification email sent.',
        'updated' => 'Staff details updated successfully.',
        'services' => 'Service assignments updated successfully.',
        'status' => 'Staff account status updated successfully.',
        'deleted' => 'Staff account and associated records deleted successfully.'
    ];

    $message = $successMessages[$_GET['success']] ?? '';
}


// =====================================================
// ADD STAFF ACCOUNT
// =====================================================

if (isset($_POST['add_staff'])) {

    $accountId = trim($_POST['account_id'] ?? '');
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $serviceId = (int) ($_POST['service_id'] ?? 0);

    if (
        $accountId === '' ||
        $firstName === '' ||
        $lastName === '' ||
        $email === ''
    ) {
        $error = 'Please complete all required fields.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';

    } elseif ($serviceId <= 0) {
        $error = 'Select one service for this staff member.';

    } elseif ($appUrl === '') {
        $error = 'Application URL is not configured.';

    } else {

        try {

            $temporaryPassword =
                'Qf!' . bin2hex(random_bytes(6)) . 'aA';

            $verificationToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $verificationToken);

            $checkService = $pdo->prepare("
                SELECT service_id
                FROM services
                WHERE service_id = ?
                AND is_active = 1
            ");

            $checkService->execute([$serviceId]);

            if (!$checkService->fetchColumn()) {
                throw new RuntimeException('Selected service is not active.');
            }

            $pdo->beginTransaction();

            // Create staff account as pending.
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
                VALUES (2, ?, ?, ?, ?, ?, 'pending', NULL, 1)
            ");

            $stmt->execute([
                $accountId,
                $firstName,
                $lastName,
                $email,
                password_hash($temporaryPassword, PASSWORD_DEFAULT)
            ]);

            $staffId = (int) $pdo->lastInsertId();


            // Assign staff to selected service.
            $stmt = $pdo->prepare("
                INSERT INTO staff_service_assignments (
                    user_id,
                    service_id
                )
                VALUES (?, ?)
            ");

            $stmt->execute([$staffId, $serviceId]);


            // Generate verification token.
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
                $staffId,
                $tokenHash,
                $expiresAt
            ]);


            // Build verification link.
            // Matches the student account verification URL structure.
            $verifyLink = $appUrl .
                '/verify-email.php?token=' .
                urlencode($verificationToken);


            // =====================================================
            // STAFF VERIFICATION EMAIL
            // SAME DESIGN AS STUDENT ACCOUNT EMAIL
            // =====================================================

            $safeName = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
            $safeAccountId = htmlspecialchars($accountId, ENT_QUOTES, 'UTF-8');
            $safePassword = htmlspecialchars($temporaryPassword, ENT_QUOTES, 'UTF-8');
            $safeLink = htmlspecialchars($verifyLink, ENT_QUOTES, 'UTF-8');

            $subject = 'QueueFlow - Your Staff Account';

            $body = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Your QueueFlow Staff Account</title>
            </head>

            <body style="margin:0;padding:30px 15px;background:#f4f6f9;font-family:Arial,sans-serif;">

                <div style="max-width:550px;margin:auto;background:#ffffff;padding:32px;border-radius:12px;">

                    <h2 style="text-align:center;color:#c81920;">QUEUEFLOW</h2>

                    <h2 style="color:#19263b;">Welcome, ' . $safeName . '!</h2>

                    <p style="color:#475467;line-height:1.7;">
                        Your staff account for the RMMC-MI Finance Office
                        Queue Management System has been created by the Administrator.
                    </p>

                    <h3 style="color:#19263b;">Your Login Credentials</h3>

                    <div style="background:#f8f9fb;padding:18px;border-radius:8px;">

                        <p style="margin:5px 0;color:#475467;">
                            <strong>Staff ID:</strong> ' . $safeAccountId . '
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


            // Send email before committing.
            $sent = sendEmail($email, $subject, $body);

            if (!$sent) {
                throw new RuntimeException(
                    'The verification email could not be sent.'
                );
            }

            $pdo->commit();

            header('Location: staff-management.php?success=added');
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($e instanceof PDOException) {
                $error = 'Could not add staff. The account ID or email may already be in use.';
            } else {
                $error = $e->getMessage();
            }
        }
    }
}


// =====================================================
// UPDATE STAFF ACCOUNT
// =====================================================

if (isset($_POST['update_staff'])) {

    $staffId = (int) ($_POST['staff_id'] ?? 0);
    $accountId = trim($_POST['account_id'] ?? '');
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $serviceId = (int) ($_POST['service_id'] ?? 0);

    if (
        $accountId === '' ||
        $firstName === '' ||
        $lastName === '' ||
        $email === ''
    ) {
        $error = 'Please complete all required fields.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';

    } elseif ($serviceId <= 0) {
        $error = 'Select one service for this staff member.';

    } elseif ($password !== '' && strlen($password) < 8) {
        $error = 'New password must be at least 8 characters.';

    } else {

        try {

            $checkService = $pdo->prepare("
                SELECT service_id
                FROM services
                WHERE service_id = ?
                AND is_active = 1
            ");

            $checkService->execute([$serviceId]);

            if (!$checkService->fetchColumn()) {
                throw new RuntimeException('Selected service is not active.');
            }

            $pdo->beginTransaction();

            if ($password !== '') {

                $stmt = $pdo->prepare("
                    UPDATE users
                    SET
                        account_id = ?,
                        first_name = ?,
                        last_name = ?,
                        email = ?,
                        password = ?,
                        must_change_password = 1
                    WHERE user_id = ?
                    AND role_id = 2
                ");

                $stmt->execute([
                    $accountId,
                    $firstName,
                    $lastName,
                    $email,
                    password_hash($password, PASSWORD_DEFAULT),
                    $staffId
                ]);

            } else {

                $stmt = $pdo->prepare("
                    UPDATE users
                    SET
                        account_id = ?,
                        first_name = ?,
                        last_name = ?,
                        email = ?
                    WHERE user_id = ?
                    AND role_id = 2
                ");

                $stmt->execute([
                    $accountId,
                    $firstName,
                    $lastName,
                    $email,
                    $staffId
                ]);
            }

            // Update service assignment.
            $stmt = $pdo->prepare("
                DELETE FROM staff_service_assignments
                WHERE user_id = ?
            ");

            $stmt->execute([$staffId]);

            $stmt = $pdo->prepare("
                INSERT INTO staff_service_assignments (
                    user_id,
                    service_id
                )
                VALUES (?, ?)
            ");

            $stmt->execute([$staffId, $serviceId]);

            $pdo->commit();

            header('Location: staff-management.php?success=updated');
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = $e instanceof PDOException
                ? 'Could not update staff. The account ID or email may already be in use.'
                : $e->getMessage();
        }
    }
}


// =====================================================
// CHANGE SERVICE ASSIGNMENT
// =====================================================

if (isset($_POST['save_services'])) {

    $staffId = (int) ($_POST['staff_id'] ?? 0);
    $serviceId = (int) ($_POST['service_id'] ?? 0);

    try {

        $stmt = $pdo->prepare("
            SELECT user_id
            FROM users
            WHERE user_id = ?
            AND role_id = 2
        ");

        $stmt->execute([$staffId]);

        if (!$stmt->fetchColumn()) {
            throw new RuntimeException('Staff account not found.');
        }

        $stmt = $pdo->prepare("
            SELECT service_id
            FROM services
            WHERE service_id = ?
            AND is_active = 1
        ");

        $stmt->execute([$serviceId]);

        if (!$stmt->fetchColumn()) {
            throw new RuntimeException('Select one active service.');
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            DELETE FROM staff_service_assignments
            WHERE user_id = ?
        ");

        $stmt->execute([$staffId]);

        $stmt = $pdo->prepare("
            INSERT INTO staff_service_assignments (
                user_id,
                service_id
            )
            VALUES (?, ?)
        ");

        $stmt->execute([$staffId, $serviceId]);

        $pdo->commit();

        header('Location: staff-management.php?success=services');
        exit;

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $error = 'Could not update the service assignment. Please select one active service.';
    }
}


// =====================================================
// ACTIVATE OR DEACTIVATE STAFF
// =====================================================

if (isset($_POST['update_status'])) {

    $staffId = (int) ($_POST['staff_id'] ?? 0);
    $status = $_POST['account_status'] ?? '';

    if (!in_array($status, ['active', 'inactive'], true)) {

        $error = 'Invalid account status.';

    } else {

        try {

            if ($status === 'active') {

                $stmt = $pdo->prepare("
                    SELECT email_verified_at
                    FROM users
                    WHERE user_id = ?
                    AND role_id = 2
                ");

                $stmt->execute([$staffId]);

                $verifiedAt = $stmt->fetchColumn();

                if ($verifiedAt === false || empty($verifiedAt)) {
                    throw new RuntimeException(
                        'This staff member must verify their email before the account can be activated.'
                    );
                }
            }

            $stmt = $pdo->prepare("
                UPDATE users
                SET account_status = ?
                WHERE user_id = ?
                AND role_id = 2
            ");

            $stmt->execute([$status, $staffId]);

            header('Location: staff-management.php?success=status');
            exit;

        } catch (Throwable $e) {

            $error = $e->getMessage();
        }
    }
}


// =====================================================
// PERMANENTLY DELETE STAFF ACCOUNT
// =====================================================

if (isset($_POST['delete_staff'])) {

    $staffId = (int) ($_POST['staff_id'] ?? 0);

    if ($staffId <= 0) {

        $error = 'Invalid staff account.';

    } else {

        try {

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT user_id
                FROM users
                WHERE user_id = ?
                AND role_id = 2
                FOR UPDATE
            ");

            $stmt->execute([$staffId]);

            if (!$stmt->fetchColumn()) {
                throw new RuntimeException('Staff account not found.');
            }

            $stmt = $pdo->prepare("
                DELETE FROM email_verification_tokens
                WHERE user_id = ?
            ");

            $stmt->execute([$staffId]);

            $stmt = $pdo->prepare("
                DELETE FROM staff_service_assignments
                WHERE user_id = ?
            ");

            $stmt->execute([$staffId]);

            $stmt = $pdo->prepare("
                DELETE FROM counter_readiness
                WHERE staff_user_id = ?
            ");

            $stmt->execute([$staffId]);

            $stmt = $pdo->prepare("
                DELETE FROM users
                WHERE user_id = ?
                AND role_id = 2
            ");

            $stmt->execute([$staffId]);

            $pdo->commit();

            header('Location: staff-management.php?success=deleted');
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = 'Could not delete this staff account. Another database record may still reference this user. Check the database relationships.';
        }
    }
}


// =====================================================
// GET STAFF LIST
// =====================================================

$stmt = $pdo->query("
    SELECT
        user_id,
        account_id,
        first_name,
        last_name,
        email,
        account_status,
        email_verified_at
    FROM users
    WHERE role_id = 2
    ORDER BY last_name, first_name
");

$staffList = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($staffList as $key => $staff) {

    $stmt = $pdo->prepare("
        SELECT service_id
        FROM staff_service_assignments
        WHERE user_id = ?
    ");

    $stmt->execute([$staff['user_id']]);

    $staffList[$key]['service_id'] =
        (int) ($stmt->fetchColumn() ?: 0);
}


// =====================================================
// GET STAFF FOR EDITING
// =====================================================

$editStaff = null;

if (isset($_GET['edit'])) {

    $stmt = $pdo->prepare("
        SELECT
            user_id,
            account_id,
            first_name,
            last_name,
            email
        FROM users
        WHERE user_id = ?
        AND role_id = 2
    ");

    $stmt->execute([(int) $_GET['edit']]);

    $editStaff = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($editStaff) {

        $stmt = $pdo->prepare("
            SELECT service_id
            FROM staff_service_assignments
            WHERE user_id = ?
            LIMIT 1
        ");

        $stmt->execute([$editStaff['user_id']]);

        $editStaff['service_id'] =
            (int) ($stmt->fetchColumn() ?: 0);
    }
}


// =====================================================
// COUNTER AVAILABILITY
// =====================================================

$stmt = $pdo->query("
    SELECT
        sc.counter_id,
        sc.counter_name,
        sc.is_active,
        s.service_name,
        cr.availability_status,
        cr.staff_user_id,
        u.first_name,
        u.last_name
    FROM service_counters sc
    JOIN services s
        ON s.service_id = sc.service_id
    LEFT JOIN counter_readiness cr
        ON cr.counter_id = sc.counter_id
    LEFT JOIN users u
        ON u.user_id = cr.staff_user_id
    WHERE
        (
            LOWER(s.service_name) = 'cashier'
            AND sc.counter_name IN (
                'Counter 1',
                'Counter 2',
                'Counter 3',
                'Counter 4'
            )
        )
        OR
        (
            LOWER(s.service_name) IN (
                'receivables',
                'recievables'
            )
            AND sc.counter_name IN (
                'Receivable 1',
                'Receivable 2'
            )
        )
    ORDER BY
        CASE
            WHEN LOWER(s.service_name) = 'cashier' THEN 1
            ELSE 2
        END,
        sc.counter_name
");

$counters = $stmt->fetchAll(PDO::FETCH_ASSOC);

$activeCounters = 0;
$unavailableCounters = 0;

foreach ($counters as $counter) {

    $isReady =
        (int) $counter['is_active'] === 1
        && in_array(
            $counter['availability_status'] ?? 'offline',
            ['ready', 'busy'],
            true
        )
        && !empty($counter['staff_user_id']);

    if ($isReady) {
        $activeCounters++;
    } else {
        $unavailableCounters++;
    }
}


require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<main class="main-content staff-page">

    <div class="staff-page-header">
        <div>
            <h1>Staff Management</h1>
            <p>Manage finance staff, service assignments, and counter availability.</p>
        </div>
    </div>

    <?php if ($message !== '') { ?>
        <div class="staff-alert success">
            <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php } ?>

    <?php if ($error !== '') { ?>
        <div class="staff-alert error">
            <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php } ?>


    <div class="staff-top-grid">

        <!-- STAFF FORM -->
        <section class="staff-card">

            <div class="staff-card-heading">
                <div>
                    <h2>
                        <?php echo $editStaff
                            ? 'Edit Staff Account'
                            : 'Add Staff Account'; ?>
                    </h2>

                    <p>
                        <?php echo $editStaff
                            ? 'Update the staff member’s account details.'
                            : 'Create a staff account and assign one service.'; ?>
                    </p>
                </div>

                <?php if ($editStaff) { ?>
                    <a class="staff-button secondary" href="staff-management.php">
                        Cancel edit
                    </a>
                <?php } ?>
            </div>


            <form
                method="POST"
                action="staff-management.php<?php
                    echo $editStaff
                        ? '?edit=' . (int) $editStaff['user_id']
                        : '';
                ?>"
                class="staff-form"
            >

                <?php if ($editStaff) { ?>
                    <input
                        type="hidden"
                        name="staff_id"
                        value="<?php echo (int) $editStaff['user_id']; ?>"
                    >
                <?php } ?>


                <div class="staff-form-grid">

                    <div class="staff-field">
                        <label for="account_id">
                            Account ID <span>*</span>
                        </label>

                        <input
                            id="account_id"
                            name="account_id"
                            type="text"
                            value="<?php echo htmlspecialchars($editStaff['account_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            required
                        >
                    </div>


                    <div class="staff-field">
                        <label for="first_name">
                            First name <span>*</span>
                        </label>

                        <input
                            id="first_name"
                            name="first_name"
                            type="text"
                            value="<?php echo htmlspecialchars($editStaff['first_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            required
                        >
                    </div>


                    <div class="staff-field">
                        <label for="last_name">
                            Last name <span>*</span>
                        </label>

                        <input
                            id="last_name"
                            name="last_name"
                            type="text"
                            value="<?php echo htmlspecialchars($editStaff['last_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            required
                        >
                    </div>


                    <div class="staff-field">
                        <label for="email">
                            Email <span>*</span>
                        </label>

                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="<?php echo htmlspecialchars($editStaff['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            required
                        >
                    </div>


                    <div class="staff-field staff-field-full">

                        <?php if ($editStaff) { ?>

                            <label for="password">
                                New password
                                <small>(leave blank to keep current password)</small>
                            </label>

                            <input
                                id="password"
                                name="password"
                                type="password"
                                minlength="8"
                                autocomplete="new-password"
                                placeholder="Leave blank to keep current password"
                            >

                        <?php } else { ?>

                            <label>Temporary Password</label>

                            <input
                                type="text"
                                value="Automatically generated by QueueFlow"
                                disabled
                            >

                            <small>
                                A temporary password and verification link
                                will be sent to the staff member.
                            </small>

                        <?php } ?>

                    </div>

                </div>


                <div class="staff-field staff-services-field">

                    <label for="service_id">
                        Assigned service <span>*</span>
                    </label>

                    <select id="service_id" name="service_id" required>

                        <option value="">Select one service</option>

                        <?php foreach ($servicesList as $service) { ?>

                            <option
                                value="<?php echo (int) $service['service_id']; ?>"
                                <?php echo
                                    (int) ($editStaff['service_id'] ?? 0)
                                    === (int) $service['service_id']
                                    ? 'selected'
                                    : '';
                                ?>
                            >
                                <?php echo htmlspecialchars($service['service_name'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>

                        <?php } ?>

                    </select>

                    <?php if (!$servicesList) { ?>
                        <p class="staff-muted">
                            No active services found. Activate a service first.
                        </p>
                    <?php } ?>

                </div>


                <div class="staff-form-actions">

                    <button
                        type="submit"
                        name="<?php echo $editStaff ? 'update_staff' : 'add_staff'; ?>"
                        class="staff-button primary"
                    >
                        <?php echo $editStaff
                            ? 'Save Changes'
                            : 'Add Staff Account'; ?>
                    </button>

                </div>

            </form>

        </section>


        <!-- COUNTER AVAILABILITY -->
        <section class="staff-card counter-card">

            <div class="staff-card-heading">
                <div>
                    <h2>Counter Availability</h2>
                    <p>Current status of finance service counters.</p>
                </div>
            </div>

            <div class="counter-stats">

                <div class="counter-stat active-stat">
                    <span>Active counters</span>
                    <strong><?php echo $activeCounters; ?></strong>
                </div>

                <div class="counter-stat unavailable-stat">
                    <span>Unavailable</span>
                    <strong><?php echo $unavailableCounters; ?></strong>
                </div>

            </div>


            <div class="counter-list">

                <?php if ($counters) { ?>

                    <?php foreach ($counters as $counter) {

                        $counterReady =
                            (int) $counter['is_active'] === 1
                            && in_array(
                                $counter['availability_status'] ?? 'offline',
                                ['ready', 'busy'],
                                true
                            )
                            && !empty($counter['staff_user_id']);

                        $availability =
                            $counter['availability_status'] ?? 'offline';

                        if ((int) $counter['is_active'] !== 1) {
                            $statusLabel = 'Disabled';
                        } elseif ($counterReady) {
                            $statusLabel =
                                $availability === 'busy'
                                ? 'Serving'
                                : 'Ready';
                        } else {
                            $statusLabel =
                                $availability === 'unavailable'
                                ? 'Unavailable'
                                : 'Inactive';
                        }
                    ?>

                        <div class="counter-row">

                            <div>
                                <strong>
                                    <?php echo htmlspecialchars($counter['counter_name'], ENT_QUOTES, 'UTF-8'); ?>
                                </strong>

                                <small>
                                    <?php echo htmlspecialchars($counter['service_name'], ENT_QUOTES, 'UTF-8'); ?>

                                    <?php if (!empty($counter['staff_user_id'])) { ?>
                                        ·
                                        <?php echo htmlspecialchars(
                                            $counter['first_name'] . ' ' . $counter['last_name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>
                                    <?php } ?>
                                </small>
                            </div>

                            <span class="counter-status <?php
                                echo $counterReady
                                    ? 'is-active'
                                    : 'is-unavailable';
                            ?>">
                                <?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?>
                            </span>

                        </div>

                    <?php } ?>

                <?php } else { ?>

                    <p class="staff-muted">
                        No counters have been added yet.
                    </p>

                <?php } ?>

            </div>

            <p class="staff-note">
                A counter is active only when an assigned staff member
                marks it Ready or Serving. New counters remain inactive
                until staff availability is set.
            </p>

        </section>

    </div>


    <!-- STAFF ACCOUNTS -->
    <section class="staff-card staff-list-card">

        <div class="staff-card-heading">

            <div>
                <h2>Staff Accounts</h2>
                <p>
                    Each staff member can have only one service.
                    Change it here without opening the edit form.
                </p>
            </div>

            <span class="staff-count">
                <?php echo count($staffList); ?> staff
            </span>

        </div>


        <?php if ($staffList) { ?>

            <div class="staff-table-wrap">

                <table class="staff-table">

                    <thead>
                        <tr>
                            <th>Staff member</th>
                            <th>Account ID</th>
                            <th>Service assignment</th>
                            <th>Status</th>
                            <th>Account</th>
                        </tr>
                    </thead>


                    <tbody>

                        <?php foreach ($staffList as $staff) {

                            $isVerified = !empty($staff['email_verified_at']);

                            if (!$isVerified) {
                                $statusLabel = 'Pending Verification';
                                $statusClass = 'status-pending';
                            } elseif ($staff['account_status'] === 'active') {
                                $statusLabel = 'Active';
                                $statusClass = 'status-active';
                            } else {
                                $statusLabel = ucfirst($staff['account_status']);
                                $statusClass = 'status-inactive';
                            }
                        ?>

                            <tr>

                                <td>

                                    <div class="staff-person">

                                        <span class="staff-avatar">
                                            <?php echo htmlspecialchars(
                                                strtoupper(substr($staff['first_name'], 0, 1)),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>
                                        </span>

                                        <div>

                                            <strong>
                                                <?php echo htmlspecialchars(
                                                    $staff['first_name'] . ' ' . $staff['last_name'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>
                                            </strong>

                                            <small>
                                                <?php echo htmlspecialchars($staff['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                            </small>

                                            <?php if (!$isVerified) { ?>
                                                <small class="staff-muted">
                                                    Email not verified
                                                </small>
                                            <?php } ?>

                                        </div>

                                    </div>

                                </td>


                                <td>
                                    <?php echo htmlspecialchars($staff['account_id'], ENT_QUOTES, 'UTF-8'); ?>
                                </td>


                                <td>

                                    <form
                                        method="POST"
                                        action="staff-management.php"
                                        class="assignment-form"
                                    >

                                        <input
                                            type="hidden"
                                            name="staff_id"
                                            value="<?php echo (int) $staff['user_id']; ?>"
                                        >

                                        <select name="service_id" required>

                                            <option value="">
                                                Select service
                                            </option>

                                            <?php foreach ($servicesList as $service) { ?>

                                                <option
                                                    value="<?php echo (int) $service['service_id']; ?>"
                                                    <?php echo
                                                        (int) $staff['service_id']
                                                        === (int) $service['service_id']
                                                        ? 'selected'
                                                        : '';
                                                    ?>
                                                >
                                                    <?php echo htmlspecialchars($service['service_name'], ENT_QUOTES, 'UTF-8'); ?>
                                                </option>

                                            <?php } ?>

                                        </select>

                                        <button
                                            type="submit"
                                            name="save_services"
                                            class="staff-button small primary"
                                        >
                                            Save service
                                        </button>

                                    </form>

                                </td>


                                <td>

                                    <span class="staff-status <?php echo $statusClass; ?>">
                                        <?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>

                                </td>


                                <td>

                                    <div class="staff-row-actions">

                                        <a
                                            class="staff-button small secondary"
                                            href="staff-management.php?edit=<?php echo (int) $staff['user_id']; ?>"
                                        >
                                            Edit
                                        </a>


                                        <!-- DELETE STAFF -->
                                        <form
                                            method="POST"
                                            action="staff-management.php"
                                            onsubmit="return confirm('WARNING: Permanently delete this staff account? This action cannot be undone.');"
                                        >

                                            <input
                                                type="hidden"
                                                name="staff_id"
                                                value="<?php echo (int) $staff['user_id']; ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="delete_staff"
                                                class="staff-button small danger-outline"
                                            >
                                                Delete
                                            </button>

                                        </form>


                                        <!-- ACTIVATE / DEACTIVATE -->
                                        <?php if ($isVerified) { ?>

                                            <form
                                                method="POST"
                                                action="staff-management.php"
                                                onsubmit="return confirm('Change this staff account status?');"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="staff_id"
                                                    value="<?php echo (int) $staff['user_id']; ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="account_status"
                                                    value="<?php echo
                                                        $staff['account_status'] === 'active'
                                                        ? 'inactive'
                                                        : 'active';
                                                    ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    name="update_status"
                                                    class="staff-button small <?php
                                                        echo $staff['account_status'] === 'active'
                                                            ? 'danger-outline'
                                                            : 'primary';
                                                    ?>"
                                                >
                                                    <?php echo $staff['account_status'] === 'active'
                                                        ? 'Deactivate'
                                                        : 'Activate';
                                                    ?>
                                                </button>

                                            </form>

                                        <?php } else { ?>

                                            <span class="staff-muted">
                                                Awaiting verification
                                            </span>

                                        <?php } ?>

                                    </div>

                                </td>

                            </tr>

                        <?php } ?>

                    </tbody>

                </table>

            </div>

        <?php } else { ?>

            <div class="staff-empty">
                No staff accounts yet. Add your first staff account above.
            </div>

        <?php } ?>

    </section>

</main>

<?php require_once '../../includes/footer.php'; ?>
