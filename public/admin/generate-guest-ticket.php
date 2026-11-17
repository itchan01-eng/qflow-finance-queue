<?php
require_once '../../includes/functions.php';

qflow_start_session();
qflow_require_login();

$pdo = require '../../config/database.php';

if (($_SESSION['role_name'] ?? '') !== 'Administrator') {
    http_response_code(403);
    exit('Access denied.');
}

$message = '';
$error = '';
$ticket = null;

// Cancel a guest ticket that is still waiting.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_ticket'])) {
    if (!qflow_verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } else {
        $queueId = filter_var($_POST['queue_id'] ?? null, FILTER_VALIDATE_INT);

        if (!$queueId) {
            $error = 'Invalid ticket.';
        } else {
            try {
                $pdo->beginTransaction();

                $cancelStmt = $pdo->prepare(
                    "SELECT q.queue_id, qr.queue_name
                     FROM queues q
                     INNER JOIN queue_remarks qr
                        ON qr.queue_remarks_id = q.queue_remarks_id
                     WHERE q.queue_id = ?
                       AND q.ticket_type = 'guest'
                       AND q.queue_date = CURDATE()
                     FOR UPDATE"
                );
                $cancelStmt->execute([$queueId]);
                $cancelTicket = $cancelStmt->fetch(PDO::FETCH_ASSOC);

                if (!$cancelTicket) {
                    throw new RuntimeException('Guest ticket not found.');
                }

                if ($cancelTicket['queue_name'] !== 'Waiting') {
                    throw new RuntimeException(
                        'Only waiting tickets can be cancelled.'
                    );
                }

                $cancelStatusStmt = $pdo->prepare(
                    "SELECT queue_remarks_id
                     FROM queue_remarks
                     WHERE queue_name = 'Cancelled'
                     LIMIT 1"
                );
                $cancelStatusStmt->execute();
                $cancelStatusId = $cancelStatusStmt->fetchColumn();

                if (!$cancelStatusId) {
                    throw new RuntimeException(
                        'The Cancelled queue status is missing from the database.'
                    );
                }

                $updateStmt = $pdo->prepare(
                    "UPDATE queues
                     SET queue_remarks_id = ?
                     WHERE queue_id = ?
                       AND ticket_type = 'guest'"
                );
                $updateStmt->execute([$cancelStatusId, $queueId]);

                $pdo->commit();

                $message = 'Guest ticket cancelled successfully.';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'Unable to cancel the guest ticket. Please try again.';
            }
        }
    }
}

// Get active services.
$stmt = $pdo->query(
    "SELECT service_id, service_name
     FROM services
     WHERE is_active = 1
     ORDER BY service_name"
);
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create a guest ticket.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_ticket'])) {
    if (!qflow_verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } else {
        $guestName = trim($_POST['guest_name'] ?? '');
        $serviceId = filter_var(
            $_POST['service_id'] ?? null,
            FILTER_VALIDATE_INT
        );
        $priorityRequested = isset($_POST['priority_requested']) && $_POST['priority_requested'] === '1';
        $allowedPriorityCategories = [
            'Senior Citizen',
            'PWD',
            'Pregnant Individual',
            'Other Eligible Group',
        ];
        $priorityCategoryInput = trim($_POST['priority_category'] ?? '');
        $priorityCategory = null;
        $priorityStatus = 'not_requested';
        $priorityVerifiedBy = null;
        $priorityVerifiedAt = null;

        if ($priorityRequested) {
            if (!in_array($priorityCategoryInput, $allowedPriorityCategories, true)) {
                $error = 'Please select a valid priority category.';
            } else {
                // An Administrator creating this ticket confirms the selected category.
                $priorityCategory = $priorityCategoryInput;
                $priorityStatus = 'approved';
                $priorityVerifiedBy = (int) $_SESSION['user_id'];
                $priorityVerifiedAt = date('Y-m-d H:i:s');
            }
        }

        if ($error !== '') {
            // Keep the validation message and do not create a ticket.
        } elseif ($guestName === '') {
            $error = 'Please enter the guest name.';
        } elseif (!$serviceId) {
            $error = 'Please select a service.';
        } else {
            try {
                $pdo->beginTransaction();

                // Confirm that the selected service is active.
                $serviceStmt = $pdo->prepare(
                    "SELECT service_id, service_name
                     FROM services
                     WHERE service_id = ?
                       AND is_active = 1
                     LIMIT 1
                     FOR UPDATE"
                );
                $serviceStmt->execute([$serviceId]);
                $service = $serviceStmt->fetch(PDO::FETCH_ASSOC);

                if (!$service) {
                    throw new RuntimeException(
                        'The selected service is not available.'
                    );
                }

                // Get the Waiting status ID.
                $statusStmt = $pdo->prepare(
                    "SELECT queue_remarks_id
                     FROM queue_remarks
                     WHERE queue_name = 'Waiting'
                     LIMIT 1"
                );
                $statusStmt->execute();
                $waitingStatusId = $statusStmt->fetchColumn();

                if (!$waitingStatusId) {
                    throw new RuntimeException(
                        'The Waiting queue status is missing from the database.'
                    );
                }

                // Generate the next ticket number for this service today.
                $numberStmt = $pdo->prepare(
                    "SELECT COALESCE(MAX(queue_number), 0) + 1
                     FROM queues
                     WHERE service_id = ?
                       AND queue_date = CURDATE()
                     FOR UPDATE"
                );
                $numberStmt->execute([$serviceId]);
                $queueNumber = (int) $numberStmt->fetchColumn();

                // Insert the guest ticket, including priority information.
$insertStmt = $pdo->prepare(
    "INSERT INTO queues
    (
        queue_number,
        account_id,
        service_id,
        counter_id,
        queue_remarks_id,
        queue_date,
        ticket_type,
        guest_name,
        created_by,
        served_by,
        created_at,
        priority_requested,
        priority_category,
        priority_status,
        priority_verified_by,
        priority_verified_at
    )
    VALUES (
        ?, NULL, ?, NULL, ?, CURDATE(), 'guest', ?, ?, NULL, NOW(),
        ?, ?, ?, ?, ?
    )"
);

$insertStmt->execute([
    $queueNumber,
    $serviceId,
    $waitingStatusId,
    $guestName,
    (int) $_SESSION['user_id'],
    $priorityRequested ? 1 : 0,
    $priorityCategory,
    $priorityStatus,
    $priorityVerifiedBy,
    $priorityVerifiedAt
]);

                $queueId = (int) $pdo->lastInsertId();

                $pdo->commit();

                $ticket = [
                    'queue_id' => $queueId,
                    'queue_number' => $queueNumber,
                    'guest_name' => "[GUEST] ".$guestName,
                    'service_name' => $service['service_name'],
                    'queue_date' => date('Y-m-d'),
                ];

                $message = 'Guest ticket created successfully.';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'Unable to create the guest ticket. Please try again.';
            }
        }
    }
}

// Get recent guest tickets, including priority information.
$recentTicketsStmt = $pdo->prepare(
    "SELECT
        q.queue_id,
        q.queue_number,
        q.guest_name,
        q.queue_date,
        q.priority_requested,
        q.priority_category,
        q.priority_status,
        s.service_name,
        qr.queue_name
    FROM queues q
    INNER JOIN services s
        ON q.service_id = s.service_id
    INNER JOIN queue_remarks qr
        ON q.queue_remarks_id = qr.queue_remarks_id
    WHERE q.ticket_type = 'guest'
    ORDER BY q.queue_id DESC
    LIMIT 10"
);

$recentTicketsStmt->execute();
$recentTickets = $recentTicketsStmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Guest Ticket";
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<main class="main-content guest-ticket-page">

    <header class="page-header">
        <div>
            <h1>Guest Tickets</h1>
            <p class="page-description">
                Create queue tickets for guests who do not have a student account.
            </p>
        </div>
    </header>

    <?php if ($message !== '') { ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php } ?>

    <?php if ($error !== '') { ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php } ?>

    <div class="guest-ticket-layout">

        <!-- Generate Guest Ticket -->
        <section class="section-block guest-ticket-form-card">

            <div class="section-heading">
                <div>
                    <h2>Generate Guest Ticket</h2>
                    <p>Choose the service and enter the guest's name.</p>
                </div>
            </div>

            <form method="POST" action="generate-guest-ticket.php">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo htmlspecialchars(qflow_csrf_token()); ?>"
                >

                <div class="form-group">
                    <label for="guest_name">Guest Name *</label>

                    <input
                        type="text"
                        id="guest_name"
                        name="guest_name"
                        maxlength="100"
                        required
                        value="<?php
                            echo htmlspecialchars(
                                isset($_POST['create_ticket'])
                                    ? ($_POST['guest_name'] ?? '')
                                    : ''
                            );
                        ?>"
                        placeholder="Enter guest's full name"
                    >
                </div>

                <div class="form-group">
                    <label for="service_id">Finance Service *</label>

                    <select id="service_id" name="service_id" required>
                        <option value="">Select a service</option>

                        <?php foreach ($services as $service) { ?>
                            <option
                                value="<?php echo (int) $service['service_id']; ?>"
                                <?php
                                echo (
                                    isset($_POST['create_ticket']) &&
                                    (string) ($_POST['service_id'] ?? '') ===
                                    (string) $service['service_id']
                                ) ? 'selected' : '';
                                ?>
                            >
                                <?php echo htmlspecialchars($service['service_name']); ?>
                            </option>
                        <?php } ?>

                    </select>
                </div>

                <div class="form-group">
    <label for="priority_requested">Priority Assistance</label>

    <div class="priority-checkbox-wrapper">
        <input
            type="checkbox"
            id="priority_requested"
            name="priority_requested"
            value="1"
        >

        <label for="priority_requested">
            Request Priority Assistance
        </label>
    </div>
</div>

<div class="form-group" id="priorityCategoryContainer" style="display: none;">
    <label for="priority_category">Priority Category</label>

    <select name="priority_category" id="priority_category">
        <option value="">Select priority category</option>
        <?php
        $priorityOptions = [
            'Senior Citizen',
            'PWD',
            'Pregnant Individual',
            'Other Eligible Group',
        ];

        foreach ($priorityOptions as $option) {
            $selected = (
                ($_POST['priority_category'] ?? '') === $option
            ) ? 'selected' : '';

            echo '<option value="' .
                htmlspecialchars($option, ENT_QUOTES, 'UTF-8') .
                '" ' . $selected . '>' .
                htmlspecialchars($option, ENT_QUOTES, 'UTF-8') .
                '</option>';
        }
        ?>
    </select>
</div>

                <button
                    type="submit"
                    name="create_ticket"
                    class="button button-primary"
                >
                    Generate Ticket
                </button>

            </form>

            <?php if ($ticket) { ?>
                <div class="guest-ticket-result">

                    <p class="guest-ticket-result-label">
                        Guest ticket created
                    </p>

                    <div class="guest-ticket-number">
                        <?php echo (int) $ticket['queue_number']; ?>
                    </div>

                    <h3>
                        <?php echo htmlspecialchars($ticket['service_name']); ?>
                    </h3>

                    <p>
                        <?php echo htmlspecialchars($ticket['guest_name']); ?>
                    </p>

                    <p class="guest-ticket-small">
                        Ticket ID: <?php echo (int) $ticket['queue_id']; ?>
                    </p>

                    <button
                        type="button"
                        class="button button-secondary"
                        onclick="window.print()"
                    >
                        Print Ticket
                    </button>

                </div>
            <?php } ?>

        </section>

        <!-- Today's Guest Tickets -->
        <section class="section-block guest-ticket-list-card">

            <div class="section-heading">
                <div>
                    <h2>Today's Guest Tickets</h2>
                    <p>Latest guest tickets created today.</p>
                </div>

                <span class="guest-ticket-count">
                    <?php echo count($recentTickets); ?>
                </span>
            </div>

            <?php if ($recentTickets) { ?>

                <div class="guest-ticket-table-wrap">

                    <table class="guest-ticket-table">
                        <thead>
                            <tr>
                                <th>Ticket</th>
                                <th>Guest</th>
                                <th>Service</th>
                                <th>Status / Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($recentTickets as $row) { ?>
                                <tr>

                                    <td>
                                        #<?php echo (int) $row['queue_number']; ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo htmlspecialchars(
                                            $row['guest_name'] ?? 'Guest'
                                        );
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo htmlspecialchars($row['service_name']);
                                        ?>
                                        <?php if ((int) ($row['priority_requested'] ?? 0) === 1) { ?>
                                            <br><small>Priority: <?php echo htmlspecialchars($row['priority_category'] ?? ''); ?> (<?php echo htmlspecialchars($row['priority_status'] ?? ''); ?>)</small>
                                        <?php } ?>
                                    </td>

                                    <td>
                                        <span class="guest-ticket-status">
                                            <?php
                                            echo htmlspecialchars($row['queue_name']);
                                            ?>
                                        </span>

                                        <?php if ($row['queue_name'] === 'Waiting') { ?>
                                            <form
                                                method="POST"
                                                action="generate-guest-ticket.php"
                                                onsubmit="return confirm('Are you sure you want to cancel this guest ticket?');"
                                                style="display: inline-block; margin-left: 6px;"
                                            >
                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?php echo htmlspecialchars(qflow_csrf_token()); ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="queue_id"
                                                    value="<?php echo (int) $row['queue_id']; ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    name="cancel_ticket"
                                                    class="button button-danger"
                                                >
                                                    Cancel
                                                </button>
                                            </form>
                                        <?php } ?>
                                    </td>

                                </tr>
                            <?php } ?>
                        </tbody>

                    </table>

                </div>

            <?php } else { ?>

                <p class="guest-ticket-empty">
                    No guest tickets have been created today.
                </p>

            <?php } ?>

        </section>

    </div>

</main>


<script>
document.addEventListener('DOMContentLoaded', function () {
    const priorityCheckbox = document.getElementById('priority_requested');
    const categoryContainer = document.getElementById('priorityCategoryContainer');
    const categorySelect = document.getElementById('priority_category');

    function togglePriorityCategory() {
        categoryContainer.style.display = priorityCheckbox.checked ? 'block' : 'none';
        categorySelect.required = priorityCheckbox.checked;

        if (!priorityCheckbox.checked) {
            categorySelect.value = '';
        }
    }

    priorityCheckbox.addEventListener('change', togglePriorityCategory);
    togglePriorityCategory();
});
</script>

<?php require_once '../../includes/footer.php'; ?>