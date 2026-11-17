
<?php

require_once '../../includes/functions.php';

qflow_require_role(['Administrator']);

$pdo = require '../../config/database.php';

$pageTitle = 'Priority Assistance Requests';
$success = '';
$error = '';

/*
|--------------------------------------------------------------------------
| PROCESS PRIORITY REQUEST
|--------------------------------------------------------------------------
| Pending priority tickets stay out of the live queue until approved.
|
| APPROVED:
| - priority_status becomes approved
| - Ticket enters the live queue
| - Cashier priority tickets are assigned to Counter 4
|
| REJECTED:
| - priority_status becomes rejected
| - Existing queue status remains unchanged
| - Ticket remains a regular queue ticket
| - No new ticket is generated
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        qflow_verify_csrf($_POST['csrf_token'] ?? '');

        $action = (string) ($_POST['action'] ?? '');
        $queueId = filter_input(INPUT_POST, 'queue_id', FILTER_VALIDATE_INT);
        $adminId = (int) ($_SESSION['user_id'] ?? 0);

        if (
            !$queueId ||
            !in_array($action, ['approve_priority', 'reject_priority'], true)
        ) {
            throw new RuntimeException('Invalid priority request action.');
        }

        $pdo->beginTransaction();

        // Lock the selected ticket while processing the request.
        $ticketStmt = $pdo->prepare("
            SELECT
                q.queue_id,
                q.service_id,
                q.queue_remarks_id,
                q.priority_requested,
                q.priority_status,
                q.counter_id,
                s.service_name
            FROM queues q
            INNER JOIN services s
                ON s.service_id = q.service_id
            WHERE q.queue_id = ?
            FOR UPDATE
        ");

        $ticketStmt->execute([$queueId]);
        $ticket = $ticketStmt->fetch(PDO::FETCH_ASSOC);

        if (
            !$ticket ||
            (int) $ticket['priority_requested'] !== 1 ||
            $ticket['priority_status'] !== 'pending'
        ) {
            throw new RuntimeException(
                'This request is no longer pending verification. Refresh the page and try again.'
            );
        }

        // Keep the existing queue status unless approval requires
        // changing it to Waiting.
        $currentStatusId = (int) $ticket['queue_remarks_id'];
        $newStatusId = $currentStatusId;

        if ($action === 'approve_priority') {

            // Get the Waiting status.
            $waitingStmt = $pdo->prepare("
                SELECT queue_remarks_id
                FROM queue_remarks
                WHERE queue_name = 'Waiting'
                LIMIT 1
            ");

            $waitingStmt->execute();
            $waitingId = $waitingStmt->fetchColumn();

            if ($waitingId === false) {
                throw new RuntimeException(
                    'The Waiting status is missing from the database.'
                );
            }

            $newStatusId = (int) $waitingId;
            $counterId = null;

            // Cashier priority tickets use Counter 4.
            if (
                stripos((string) $ticket['service_name'], 'cashier') !== false
            ) {
                $counterStmt = $pdo->prepare("
                    SELECT counter_id
                    FROM service_counters
                    WHERE service_id = ?
                      AND counter_name = 'Counter 4'
                      AND is_active = 1
                    LIMIT 1
                ");

                $counterStmt->execute([
                    $ticket['service_id']
                ]);

                $foundCounter = $counterStmt->fetchColumn();

                if ($foundCounter === false) {
                    throw new RuntimeException(
                        'Counter 4 was not found or is inactive for the Cashier service. Please configure Counter 4 first.'
                    );
                }

                $counterId = (int) $foundCounter;
            }

            // Approve the existing ticket.
            $update = $pdo->prepare("
                UPDATE queues
                SET
                    priority_status = 'approved',
                    counter_id = ?,
                    queue_remarks_id = ?
                WHERE queue_id = ?
            ");

            $update->execute([
                $counterId,
                $newStatusId,
                $queueId
            ]);

            $success = 'Priority assistance approved. The ticket is now eligible for the live queue.';

        } elseif ($action === 'reject_priority') {

            /*
             * Do not change queue_remarks_id here.
             * The ticket keeps its existing queue status.
             * It simply loses priority eligibility and remains
             * in the regular queue.
             */

            $update = $pdo->prepare("
                UPDATE queues
                SET
                    priority_status = 'rejected',
                    counter_id = NULL
                WHERE queue_id = ?
            ");

            $update->execute([$queueId]);

            $newStatusId = $currentStatusId;

            $success = 'Priority assistance request rejected. The ticket remains in the regular queue.';
        }

        // Record queue status history only if the actual
        // queue status changed.
        if ($newStatusId !== $currentStatusId) {

            $history = $pdo->prepare("
                INSERT INTO queue_status
                (
                    queue_id,
                    previous_status_id,
                    new_status_id,
                    changed_by,
                    changed_at
                )
                VALUES (?, ?, ?, ?, NOW())
            ");

            $history->execute([
                $queueId,
                $currentStatusId,
                $newStatusId,
                $adminId
            ]);
        }

        $pdo->commit();

        $_SESSION['priority_request_success'] = $success;

        header('Location: priority-requests.php');
        exit;

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            'Priority request handling error: ' .
            get_class($e) . ': ' .
            $e->getMessage()
        );

        $error = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Could not update the priority request. Check that the priority columns have been added to the queues table.';
    }
}

// Retrieve success message after redirect.
if (!empty($_SESSION['priority_request_success'])) {
    $success = (string) $_SESSION['priority_request_success'];
    unset($_SESSION['priority_request_success']);
}

/*
|--------------------------------------------------------------------------
| GET PENDING PRIORITY REQUESTS
|--------------------------------------------------------------------------
|
| IMPORTANT:
| queues.account_id references users.account_id.
| Do not join it to users.user_id.
|
*/

$stmt = $pdo->query("
    SELECT
        q.queue_id,
        q.account_id,
        q.queue_number,
        q.queue_date,
        q.created_at,
        q.priority_category,
        q.priority_status,
        q.counter_id,

        s.service_name,

        u.first_name,
        u.last_name,
        u.email,

        sc.counter_name,

        r.queue_name AS queue_status

    FROM queues q

    INNER JOIN services s
        ON s.service_id = q.service_id

    INNER JOIN users u
        ON u.account_id = q.account_id

    INNER JOIN queue_remarks r
        ON r.queue_remarks_id = q.queue_remarks_id

    LEFT JOIN service_counters sc
        ON sc.counter_id = q.counter_id

    WHERE COALESCE(q.priority_requested, 0) = 1
      AND q.priority_status = 'pending'
      AND r.queue_name = 'Waiting'

    ORDER BY
        q.queue_date DESC,
        q.queue_id DESC
");

$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

require '../../includes/header.php';
require '../../includes/sidebar.php';
?>

<main class="main-content">

    <header class="page-header">
        <div>
            <p class="eyebrow">ADMINISTRATOR</p>
            <h1>Priority Assistance Requests</h1>
            <p class="page-description">
                Review requests before they enter the live queue.
            </p>
        </div>
    </header>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success" role="status">
            <?= qflow_escape($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error" role="alert">
            <?= qflow_escape($error) ?>
        </div>
    <?php endif; ?>

    <section class="section-block">

        <div class="section-heading">
            <div>
                <h2>Submitted Requests</h2>
                <p>
                    Pending requests stay out of the live queue until approved.
                    Rejected requests remain in the regular queue.
                </p>
            </div>
        </div>

        <?php if (!$requests): ?>

            <div class="empty-state">
                <h3>No priority requests yet</h3>
                <p>
                    Student requests for priority assistance will appear here.
                </p>
            </div>

        <?php else: ?>

            <div class="table-responsive">

                <table class="data-table">

                    <thead>
                        <tr>
                            <th>Ticket</th>
                            <th>Student</th>
                            <th>Service</th>
                            <th>Reason</th>
                            <th>Date</th>
                            <th>Queue Status</th>
                            <th>Verification</th>
                            <th>Counter</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php foreach ($requests as $request):

                        $service = strtolower(
                            (string) $request['service_name']
                        );

                        $prefix = str_contains($service, 'cashier')
                            ? 'C'
                            : (
                                str_contains($service, 'receivables')
                                    ? 'R'
                                    : 'Q'
                            );

                        $ticketNumber = $prefix . '-' .
                            str_pad(
                                (string) $request['queue_number'],
                                3,
                                '0',
                                STR_PAD_LEFT
                            );

                        $priorityStatus = (string) $request['priority_status'];

                        $studentName = trim(
                            ($request['first_name'] ?? '') . ' ' .
                            ($request['last_name'] ?? '')
                        );

                        $requestDate = $request['created_at']
                            ?: $request['queue_date'];

                    ?>

                        <tr>

                            <td>
                                <strong>
                                    <?= qflow_escape($ticketNumber) ?>
                                </strong>
                            </td>

                            <td>
                                <?= qflow_escape($studentName) ?>
                                <br>
                                <small>
                                    <?= qflow_escape($request['email'] ?? '') ?>
                                </small>
                            </td>

                            <td>
                                <?= qflow_escape($request['service_name']) ?>
                            </td>

                            <td>
                                <?= qflow_escape(
                                    $request['priority_category']
                                    ?: 'Not specified'
                                ) ?>
                            </td>

                            <td>
                                <?= qflow_escape(
                                    date(
                                        'M j, Y g:i A',
                                        strtotime($requestDate)
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= qflow_escape($request['queue_status']) ?>
                            </td>

                            <td>
                                <strong>
                                    <?= qflow_escape(
                                        ucfirst($priorityStatus)
                                    ) ?>
                                </strong>
                            </td>

                            <td>
                                <?= qflow_escape(
                                    $request['counter_name']
                                    ?: 'Not assigned'
                                ) ?>
                            </td>

                            <td>

                                <?php if ($priorityStatus === 'pending'): ?>

                                    <div
                                        class="priority-actions"
                                        style="display:flex;gap:8px;flex-wrap:wrap"
                                    >

                                        <!-- APPROVE -->
                                        <form
                                            method="post"
                                            onsubmit="return confirm('Approve priority assistance for <?= qflow_escape($ticketNumber) ?>?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= qflow_escape(
                                                    qflow_csrf_token()
                                                ) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="queue_id"
                                                value="<?= (int) $request['queue_id'] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="approve_priority"
                                            >

                                            <button
                                                type="submit"
                                                class="button button-primary"
                                            >
                                                Approve
                                            </button>

                                        </form>

                                        <!-- REJECT -->
                                        <form
                                            method="post"
                                            onsubmit="return confirm('Reject priority assistance for <?= qflow_escape($ticketNumber) ?>? The ticket will remain in the regular queue.');"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= qflow_escape(
                                                    qflow_csrf_token()
                                                ) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="queue_id"
                                                value="<?= (int) $request['queue_id'] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="reject_priority"
                                            >

                                            <button
                                                type="submit"
                                                class="button button-secondary"
                                            >
                                                Reject
                                            </button>

                                        </form>

                                    </div>

                                <?php else: ?>

                                    <span>—</span>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>

</main>

<?php require '../../includes/footer.php'; ?>
