
<?php
require_once '../../includes/functions.php';

qflow_start_session();
qflow_require_login();

$pdo = require '../../config/database.php';

if (($_SESSION['role_name'] ?? '') !== 'Administrator') {
    http_response_code(403);
    exit('Access denied.');
}

$error = '';
$success = '';

/*
|--------------------------------------------------------------------------
| Save counter assignment
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!qflow_verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please refresh the page.';
    } else {
        $counterId = filter_var(
            $_POST['counter_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $staffId = ($_POST['staff_user_id'] ?? '') !== ''
            ? (int) $_POST['staff_user_id']
            : null;

        try {
            if (!$counterId) {
                throw new RuntimeException('Invalid counter selected.');
            }

            $pdo->beginTransaction();

            // Confirm that the selected counter is one of the six fixed counters.
            $counterStatement = $pdo->prepare(
                "SELECT sc.counter_id, sc.service_id
                 FROM service_counters sc
                 INNER JOIN services s
                    ON s.service_id = sc.service_id
                 WHERE sc.counter_id = :counter_id
                   AND (
                       (LOWER(s.service_name) = 'cashier'
                        AND sc.counter_name IN (
                            'Counter 1', 'Counter 2',
                            'Counter 3', 'Counter 4'
                        ))
                       OR
                       (LOWER(s.service_name) IN (
                            'receivables', 'recievables'
                        )
                        AND sc.counter_name IN (
                            'Receivable 1', 'Receivable 2'
                        ))
                   )
                 FOR UPDATE"
            );

            $counterStatement->execute([
                'counter_id' => $counterId
            ]);

            $counter = $counterStatement->fetch(PDO::FETCH_ASSOC);

            if (!$counter) {
                throw new RuntimeException('Counter not found.');
            }

            // If a staff member is selected, verify their service assignment.
            if ($staffId !== null) {
                $staffStatement = $pdo->prepare(
                    "SELECT u.user_id
                     FROM users u
                     INNER JOIN staff_service_assignments a
                        ON a.user_id = u.user_id
                     WHERE u.user_id = :staff_id
                       AND u.role_id = 2
                       AND u.account_status = 'active'
                       AND a.service_id = :service_id
                     LIMIT 1"
                );

                $staffStatement->execute([
                    'staff_id' => $staffId,
                    'service_id' => $counter['service_id']
                ]);

                if (!$staffStatement->fetchColumn()) {
                    throw new RuntimeException(
                        'Select an active staff member assigned to this service.'
                    );
                }

                // A staff member can only be assigned to one counter.
                $existingAssignment = $pdo->prepare(
                    "SELECT counter_id
                     FROM counter_readiness
                     WHERE staff_user_id = :staff_id
                       AND counter_id != :counter_id
                     LIMIT 1
                     FOR UPDATE"
                );

                $existingAssignment->execute([
                    'staff_id' => $staffId,
                    'counter_id' => $counterId
                ]);

                if ($existingAssignment->fetchColumn()) {
                    throw new RuntimeException(
                        'This staff member is already assigned to another counter.'
                    );
                }
            }

            // Make sure readiness exists for the selected counter.
            $readinessStatement = $pdo->prepare(
                "INSERT IGNORE INTO counter_readiness
                    (counter_id, staff_user_id, availability_status)
                 VALUES (:counter_id, NULL, 'offline')"
            );

            $readinessStatement->execute([
                'counter_id' => $counterId
            ]);

            // Changing or removing an assignment resets availability.
            $updateStatement = $pdo->prepare(
                "UPDATE counter_readiness
                 SET staff_user_id = :staff_id,
                     availability_status = 'offline'
                 WHERE counter_id = :counter_id"
            );

            $updateStatement->execute([
                'staff_id' => $staffId,
                'counter_id' => $counterId
            ]);

            $pdo->commit();

            header('Location: counter-assignments.php?saved=1');
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Unable to save the counter assignment.';
        }
    }
}

if (isset($_GET['saved'])) {
    $success = 'Counter assignment saved successfully.';
}

/*
|--------------------------------------------------------------------------
| Load eligible staff
|--------------------------------------------------------------------------
*/
$staffStatement = $pdo->query(
    "SELECT DISTINCT
        u.user_id,
        u.first_name,
        u.last_name,
        a.service_id
     FROM users u
     INNER JOIN staff_service_assignments a
        ON a.user_id = u.user_id
     INNER JOIN services s
        ON s.service_id = a.service_id
     WHERE u.role_id = 2
       AND u.account_status = 'active'
       AND s.is_active = 1
     ORDER BY u.last_name, u.first_name"
);

$staffMembers = $staffStatement->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Load the six fixed counters and their assignments
|--------------------------------------------------------------------------
*/
$counterStatement = $pdo->query(
    "SELECT
        sc.counter_id,
        sc.counter_name,
        sc.service_id,
        s.service_name,
        cr.staff_user_id,
        cr.availability_status,
        u.first_name,
        u.last_name
     FROM service_counters sc
     INNER JOIN services s
        ON s.service_id = sc.service_id
     LEFT JOIN counter_readiness cr
        ON cr.counter_id = sc.counter_id
     LEFT JOIN users u
        ON u.user_id = cr.staff_user_id
     WHERE
        (LOWER(s.service_name) = 'cashier'
         AND sc.counter_name IN (
            'Counter 1', 'Counter 2',
            'Counter 3', 'Counter 4'
         ))
        OR
        (LOWER(s.service_name) IN ('receivables', 'recievables')
         AND sc.counter_name IN (
            'Receivable 1', 'Receivable 2'
         ))
     ORDER BY
        CASE
            WHEN LOWER(s.service_name) = 'cashier' THEN 1
            ELSE 2
        END,
        sc.counter_name"
);

$counters = $counterStatement->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Counter Assignments';

require '../../includes/header.php';
require '../../includes/sidebar.php';
?>

<main class="main-content counter-assignments-page">

    <header class="counter-page-header">
        <div>
            <h1>Counter Assignments</h1>
            <p>Assign one eligible staff member to each finance counter.</p>
        </div>
    </header>

    <?php if ($error !== ''): ?>
        <div class="counter-alert error" role="alert">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
        <div class="counter-alert success" role="status">
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <p class="counter-assignment-note">
        Changing an assignment sets that counter to Offline.
        The assigned staff member must mark themselves Available
        on the dashboard before Smart Queue can use the counter.
    </p>

    <section class="counter-grid">

        <?php foreach ($counters as $counter): ?>

            <?php
            $counterStatus = $counter['availability_status'] ?? 'offline';

            $statusLabel = match ($counterStatus) {
                'ready' => 'Available',
                'busy' => 'Serving',
                'unavailable' => 'Unavailable',
                default => 'Offline'
            };

            $statusClass = match ($counterStatus) {
                'ready' => 'available',
                'busy' => 'busy',
                'unavailable' => 'unavailable',
                default => 'offline'
            };
            ?>

            <article class="counter-card">

                <h3>
                    <?php echo htmlspecialchars($counter['counter_name']); ?>
                </h3>

                <p class="counter-service">
                    <?php echo htmlspecialchars($counter['service_name']); ?>
                </p>

                <form
                    method="POST"
                    action="counter-assignments.php"
                    class="counter-assignment-form"
                >
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo htmlspecialchars(qflow_csrf_token()); ?>"
                    >

                    <input
                        type="hidden"
                        name="counter_id"
                        value="<?php echo (int) $counter['counter_id']; ?>"
                    >

                    <select
                        name="staff_user_id"
                        aria-label="Assign staff to <?php echo htmlspecialchars($counter['counter_name']); ?>"
                    >
                        <option value="">Unassigned</option>

                        <?php foreach ($staffMembers as $staff): ?>
                            <?php if ((int) $staff['service_id'] === (int) $counter['service_id']): ?>

                                <option
                                    value="<?php echo (int) $staff['user_id']; ?>"
                                    <?php
                                    echo (int) ($counter['staff_user_id'] ?? 0)
                                        === (int) $staff['user_id']
                                        ? 'selected'
                                        : '';
                                    ?>
                                >
                                    <?php
                                    echo htmlspecialchars(
                                        $staff['first_name'] . ' ' . $staff['last_name']
                                    );
                                    ?>
                                </option>

                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit">
                        Save Assignment
                    </button>
                </form>

                <span class="counter-status <?php echo $statusClass; ?>">
                    Status: <?php echo htmlspecialchars($statusLabel); ?>
                </span>

                <p class="counter-assignment-note">
                    <?php if (!empty($counter['staff_user_id'])): ?>
                        Assigned to
                        <?php
                        echo htmlspecialchars(
                            $counter['first_name'] . ' ' . $counter['last_name']
                        );
                        ?>
                    <?php else: ?>
                        No staff assigned
                    <?php endif; ?>
                </p>

            </article>

        <?php endforeach; ?>

    </section>

</main>

<?php require_once '../../includes/footer.php'; ?>
