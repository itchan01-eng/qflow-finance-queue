<?php

require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/send-email.php';
require_once dirname(__DIR__) . '/includes/email-templates.php';

qflow_start_session();
qflow_require_role(['Student']);

date_default_timezone_set('Asia/Manila');

$pdo = require dirname(__DIR__) . '/config/database.php';

$userId = (int) ($_SESSION['user_id'] ?? 0);
$accountId = (string) ($_SESSION['account_id'] ?? '');

if (!$userId) {
    header('Location: login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Retrieve the student's account ID
|--------------------------------------------------------------------------
*/

$userStatement = $pdo->prepare(
    'SELECT account_id, account_status
     FROM users
     WHERE user_id = :user_id
     LIMIT 1'
);

$userStatement->execute([
    'user_id' => $userId,
]);

$currentUser = $userStatement->fetch(PDO::FETCH_ASSOC);

if (!$currentUser || $currentUser['account_status'] !== 'active') {
    $_SESSION = [];
    session_destroy();

    header('Location: login.php');
    exit;
}

// Textual account ID from users.account_id (VARCHAR 50).
$accountId = (string) $currentUser['account_id'];

/*
|--------------------------------------------------------------------------
| Handle ticket cancellation
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'cancel_queue'
) {
    try {
        if (!qflow_verify_csrf($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException(
                'Your session has expired. Please refresh the page and try again.'
            );
        }

        $queueId = filter_input(
            INPUT_POST,
            'queue_id',
            FILTER_VALIDATE_INT
        );

        if (!$queueId || $queueId < 1) {
            throw new RuntimeException('Invalid queue ticket.');
        }

        $pdo->beginTransaction();

        /*
         * Retrieve and lock the student's waiting ticket.
         * queues.account_id now references users.account_id.
         */
        $stmt = $pdo->prepare("
            SELECT
                q.queue_id,
                q.queue_number,
                q.queue_remarks_id,
                q.service_id,
                s.service_name,
                r.queue_name AS current_status,
                u.email,
                u.first_name,
                u.last_name
            FROM queues q
            INNER JOIN queue_remarks r
                ON r.queue_remarks_id = q.queue_remarks_id
            INNER JOIN services s
                ON s.service_id = q.service_id
            INNER JOIN users u
                ON u.account_id = q.account_id
            WHERE q.queue_id = ?
              AND q.account_id = ?
              AND r.queue_name = 'Waiting'
            FOR UPDATE
        ");

        $stmt->execute([
            $queueId,
            $accountId,
        ]);

        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ticket) {
            throw new RuntimeException(
                'This ticket is no longer waiting and cannot be cancelled.'
            );
        }

        /*
         * Retrieve the Cancelled status ID.
         */
        $stmt = $pdo->prepare("
            SELECT queue_remarks_id
            FROM queue_remarks
            WHERE queue_name = 'Cancelled'
            LIMIT 1
        ");

        $stmt->execute();

        $cancelledId = $stmt->fetchColumn();

        if ($cancelledId === false) {
            throw new RuntimeException(
                'The Cancelled status is missing from the database.'
            );
        }

        /*
         * Update the queue ticket using the textual account ID.
         */
        $stmt = $pdo->prepare("
            UPDATE queues
            SET queue_remarks_id = ?
            WHERE queue_id = ?
              AND account_id = ?
        ");

        $stmt->execute([
            $cancelledId,
            $queueId,
            $accountId,
        ]);

        /*
         * Record the status change.
         * changed_by remains the numeric users.user_id.
         */
        $stmt = $pdo->prepare("
            INSERT INTO queue_status (
                queue_id,
                previous_status_id,
                new_status_id,
                changed_by,
                changed_at
            )
            VALUES (?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $queueId,
            $ticket['queue_remarks_id'],
            $cancelledId,
            $userId,
        ]);

        /*
         * Generate the formatted ticket number for the email.
         */
        $serviceName = strtolower($ticket['service_name']);

        $prefix = str_contains($serviceName, 'cashier')
            ? 'C'
            : (
                str_contains($serviceName, 'receivables')
                    ? 'R'
                    : 'Q'
            );

        $ticketNumber = $prefix . '-' . str_pad(
            (string) $ticket['queue_number'],
            3,
            '0',
            STR_PAD_LEFT
        );

        /*
         * Record the cancellation in transaction logs.
         * Store the textual account ID, not the internal numeric user key.
         */
        $stmt = $pdo->prepare("
            INSERT INTO transaction_logs (
                account_id,
                action_type,
                table_name,
                record_id,
                description
            )
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $accountId,
            'CANCEL_QUEUE',
            'queues',
            $queueId,
            'Student cancelled queue ticket ' . $ticketNumber,
        ]);

        /*
         * Create an in-app notification.
         * Notifications are addressed by the textual account ID.
         */
        $stmt = $pdo->prepare("
            INSERT INTO notifications (
                account_id,
                queue_id,
                notification_type,
                title,
                message
            )
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $accountId,
            $queueId,
            'queue_cancelled',
            'Ticket cancelled',
            'Your ticket ' . $ticketNumber . ' for '
                . $ticket['service_name']
                . ' has been cancelled successfully.',
        ]);

        /*
         * Commit the database changes first.
         */
        $pdo->commit();

        $_SESSION['queue_success'] =
            'Your ticket was cancelled successfully.';

        /*
         * Send the cancellation email after a successful commit.
         */
        if (!empty($ticket['email'])) {
            try {
                $studentName = trim(
                    ($ticket['first_name'] ?? '') . ' '
                    . ($ticket['last_name'] ?? '')
                );

                if ($studentName === '') {
                    $studentName = 'Student';
                }

                $emailMessage = queueFlowEmailTemplate('cancelled', [
                    'name' => $studentName,
                    'ticket' => $ticketNumber,
                    'service' => $ticket['service_name'],
                ]);

                $emailSent = sendEmail(
                    $ticket['email'],
                    'QueueFlow: Your Queue Ticket Has Been Cancelled',
                    $emailMessage
                );

                if (!$emailSent) {
                    error_log(
                        'QueueFlow cancellation email was not sent. Queue ID: '
                        . $queueId
                    );
                }

            } catch (Throwable $emailError) {
                error_log(
                    'QueueFlow cancellation email error: '
                    . $emailError->getMessage()
                );
            }
        }

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            'Queue cancellation error: '
            . get_class($e) . ': ' . $e->getMessage()
        );

        $_SESSION['queue_error'] = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'We could not cancel your ticket. Please try again.';
    }

    header('Location: my-queue.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Retrieve ticket history and priority-request details
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        q.queue_id,
        q.queue_number,
        q.queue_date,
        q.service_id,
        q.counter_id,
        q.created_at,
        COALESCE(q.priority_requested, 0) AS priority_requested,
        COALESCE(q.priority_status, 'not_requested') AS priority_status,
        q.priority_category,
        s.service_name,
        r.queue_name AS status,
        sc.counter_name
    FROM queues q
    INNER JOIN services s
        ON s.service_id = q.service_id
    INNER JOIN queue_remarks r
        ON r.queue_remarks_id = q.queue_remarks_id
    LEFT JOIN service_counters sc
        ON sc.counter_id = q.counter_id
    WHERE q.account_id = ?
    ORDER BY q.queue_date DESC, q.queue_id DESC
");

$stmt->execute([
    $accountId,
]);

$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Identify the student's active ticket
|--------------------------------------------------------------------------
*/

$activeTicket = null;

foreach ($tickets as $ticket) {
    if (
        $ticket['queue_date'] === date('Y-m-d')
        && in_array($ticket['status'], ['Waiting', 'Serving'], true)
    ) {
        $activeTicket = $ticket;
        break;
    }
}

/*
|--------------------------------------------------------------------------
| Format ticket number
|--------------------------------------------------------------------------
*/

function getTicketNumber(array $ticket): string
{
    $service = strtolower($ticket['service_name']);

    $prefix = str_contains($service, 'cashier')
        ? 'C'
        : (str_contains($service, 'receivables') ? 'R' : 'Q');

    return $prefix . '-' . str_pad(
        (string) $ticket['queue_number'],
        3,
        '0',
        STR_PAD_LEFT
    );
}

/*
|--------------------------------------------------------------------------
| Priority request status
|--------------------------------------------------------------------------
*/

$isPriorityPending = $activeTicket !== null
    && (int) $activeTicket['priority_requested'] === 1
    && strtolower((string) $activeTicket['priority_status']) !== 'approved';

/*
|--------------------------------------------------------------------------
| Queue statistics
|--------------------------------------------------------------------------
*/

$peopleAhead = 0;
$waitingCount = 0;
$servingCount = 0;
$estimatedMinutes = 0;
$averageMinutes = 5.0;

if ($activeTicket !== null && !$isPriorityPending) {

    /*
     * Approved priority tickets go before regular tickets.
     * Within each group, FIFO is used.
     */
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM queues q
        INNER JOIN queue_remarks r
            ON r.queue_remarks_id = q.queue_remarks_id
        WHERE q.service_id = ?
          AND q.queue_date = ?
          AND r.queue_name = 'Waiting'
          AND q.queue_id <> ?
          AND (
              COALESCE(q.priority_requested, 0) = 0
              OR COALESCE(q.priority_status, 'not_requested') = 'approved'
          )
          AND (
              (
                  COALESCE(q.priority_requested, 0) = 1
                  AND COALESCE(q.priority_status, 'not_requested') = 'approved'
                  AND NOT (
                      COALESCE(?, 0) = 1
                      AND ? = 'approved'
                  )
              )
              OR (
                  (
                      (
                          COALESCE(q.priority_requested, 0) = 1
                          AND COALESCE(q.priority_status, 'not_requested') = 'approved'
                      )
                      =
                      (
                          COALESCE(?, 0) = 1
                          AND ? = 'approved'
                      )
                  )
                  AND q.queue_number < ?
              )
          )
    ");

    $stmt->execute([
        $activeTicket['service_id'],
        $activeTicket['queue_date'],
        $activeTicket['queue_id'],
        $activeTicket['priority_requested'],
        $activeTicket['priority_status'],
        $activeTicket['priority_requested'],
        $activeTicket['priority_status'],
        $activeTicket['queue_number'],
    ]);

    $peopleAhead = (int) $stmt->fetchColumn();

    /*
     * Only eligible tickets count in the queue totals.
     * Pending priority requests are excluded.
     */
    $stmt = $pdo->prepare("
        SELECT
            SUM(
                CASE
                    WHEN r.queue_name = 'Waiting' THEN 1
                    ELSE 0
                END
            ) AS waiting_count,
            SUM(
                CASE
                    WHEN r.queue_name = 'Serving' THEN 1
                    ELSE 0
                END
            ) AS serving_count
        FROM queues q
        INNER JOIN queue_remarks r
            ON r.queue_remarks_id = q.queue_remarks_id
        WHERE q.service_id = ?
          AND q.queue_date = ?
          AND (
              COALESCE(q.priority_requested, 0) = 0
              OR COALESCE(q.priority_status, 'not_requested') = 'approved'
          )
    ");

    $stmt->execute([
        $activeTicket['service_id'],
        $activeTicket['queue_date'],
    ]);

    $counts = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $waitingCount = (int) ($counts['waiting_count'] ?? 0);
    $servingCount = (int) ($counts['serving_count'] ?? 0);

    /*
     * Calculate average service time from completed transactions.
     */
    $stmt = $pdo->prepare("
        SELECT AVG(
            TIMESTAMPDIFF(SECOND, q.called_at, q.completed_at)
        ) / 60
        FROM queues q
        INNER JOIN queue_remarks r
            ON r.queue_remarks_id = q.queue_remarks_id
        WHERE q.service_id = ?
          AND r.queue_name = 'Completed'
          AND q.called_at IS NOT NULL
          AND q.completed_at IS NOT NULL
          AND q.completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");

    $stmt->execute([
        $activeTicket['service_id'],
    ]);

    $average = $stmt->fetchColumn();

    $averageMinutes = (
        $average !== false && $average !== null
    )
        ? max(1, (float) $average)
        : 5.0;

    $estimatedMinutes = $activeTicket['status'] === 'Waiting'
        ? (int) ceil($peopleAhead * $averageMinutes)
        : 0;
}

/*
|--------------------------------------------------------------------------
| Page layout
|--------------------------------------------------------------------------
*/

$pageTitle = 'My Queue';

require dirname(__DIR__) . '/includes/header.php';
require dirname(__DIR__) . '/includes/sidebar.php';
?>

<style>
    .page-content.queue-page {
        width: 100%;
        max-width: none;
        min-width: 0;
        box-sizing: border-box;
    }

    .queue-page .queue-page-header,
    .queue-page .queue-ticket-card,
    .queue-page .queue-empty-card,
    .queue-page .queue-history-card,
    .queue-page .queue-page-note {
        width: 100%;
        max-width: none;
        box-sizing: border-box;
    }
</style>
<div class="page-content queue-page">

    <div class="page-header queue-page-header">
        <div>
            <span class="queue-eyebrow">STUDENT SERVICES</span>
            <h1>My Queue</h1>
            <p>Track your ticket, check your queue progress, and view previous transactions.</p>
        </div>
    </div>

    <?php if (!empty($_SESSION['queue_success'])): ?>
        <div class="alert alert-success">
            <?= qflow_escape($_SESSION['queue_success']) ?>
        </div>
        <?php unset($_SESSION['queue_success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['queue_error'])): ?>
        <div class="alert alert-error">
            <?= qflow_escape($_SESSION['queue_error']) ?>
        </div>
        <?php unset($_SESSION['queue_error']); ?>
    <?php endif; ?>

    <?php if ($activeTicket !== null): ?>

        <section class="queue-ticket-card">

            <div class="queue-ticket-top">
                <div>
                    <span class="queue-eyebrow">YOUR CURRENT TICKET</span>

                    <h2>
                        <?= qflow_escape($activeTicket['service_name']) ?>
                    </h2>

                    <p class="queue-ticket-date">
                        <?= qflow_escape(
                            date('F j, Y', strtotime($activeTicket['queue_date']))
                        ) ?>

                        <?php if (!empty($activeTicket['created_at'])): ?>
                            · Created
                            <?= qflow_escape(
                                date('g:i A', strtotime($activeTicket['created_at']))
                            ) ?>
                        <?php endif; ?>
                    </p>
                </div>

                <span class="queue-status-badge <?= strtolower($activeTicket['status']) ?>">
                    <?= qflow_escape($activeTicket['status']) ?>
                </span>
            </div>

            <?php if ($isPriorityPending): ?>

                <div class="alert alert-info" role="status">
                    <strong>Priority assistance pending verification</strong>

                    <p>
                        Your ticket is waiting for staff verification.
                        It has not been added to the active queue, has no
                        assigned counter, and is not included in
                        queue-position or waiting-time calculations.
                    </p>

                    <?php if (!empty($activeTicket['priority_category'])): ?>
                        <p>
                            Request category:
                            <?= qflow_escape($activeTicket['priority_category']) ?>
                        </p>
                    <?php endif; ?>
                </div>

            <?php elseif (
                (int) $activeTicket['priority_requested'] === 1
                && strtolower((string) $activeTicket['priority_status']) === 'approved'
            ): ?>

                <div class="alert alert-success" role="status">
                    <strong>Priority assistance approved</strong>

                    <?php if (!empty($activeTicket['priority_category'])): ?>
                        <span>
                            — <?= qflow_escape($activeTicket['priority_category']) ?>
                        </span>
                    <?php endif; ?>
                </div>

            <?php endif; ?>

            <div class="queue-ticket-main">

                <div class="queue-ticket-number-block">
                    <span class="queue-eyebrow">TICKET NUMBER</span>

                    <div class="queue-ticket-number">
                        <?= qflow_escape(getTicketNumber($activeTicket)) ?>
                    </div>

                    <p>Keep this number ready when your turn arrives.</p>
                </div>

                <div class="queue-counter-block">
                    <span class="queue-counter-icon" aria-hidden="true">⌖</span>

                    <div>
                        <span class="queue-eyebrow">ASSIGNED COUNTER</span>

                        <h3>
                            <?= qflow_escape(
                                $isPriorityPending
                                    ? 'Pending verification'
                                    : ($activeTicket['counter_name'] ?: 'Not assigned')
                            ) ?>
                        </h3>

                        <p>
                            <?php if ($isPriorityPending): ?>

                                A counter will be assigned after your request is approved.

                            <?php elseif ($activeTicket['counter_name']): ?>

                                Please proceed when your ticket is called.

                            <?php else: ?>

                                Wait for staff instructions regarding your counter.

                            <?php endif; ?>
                        </p>
                    </div>
                </div>

            </div>

            <div class="queue-progress-heading">

                <div>
                    <span class="queue-eyebrow">QUEUE PROGRESS</span>

                    <h3>
                        <?php if ($isPriorityPending): ?>
                            Awaiting verification
                        <?php elseif ($activeTicket['status'] === 'Serving'): ?>
                            It is your turn!
                        <?php else: ?>
                            You are in line
                        <?php endif; ?>
                    </h3>
                </div>

                <span class="queue-progress-number">
                    <?php if ($isPriorityPending): ?>
                        PENDING
                    <?php elseif ($activeTicket['status'] === 'Serving'): ?>
                        NOW SERVING
                    <?php else: ?>
                        <?= $peopleAhead === 0
                            ? 'UP NEXT'
                            : $peopleAhead . ' ahead' ?>
                    <?php endif; ?>
                </span>

            </div>

            <div class="queue-progress-track">
                <div
                    class="queue-progress-fill"
                    style="width: <?= $isPriorityPending
                        ? '0'
                        : (
                            $activeTicket['status'] === 'Serving'
                                ? '100'
                                : max(8, min(92, 100 / ($peopleAhead + 1)))
                        ) ?>%;"
                ></div>
            </div>

            <div class="queue-metrics">

                <div class="queue-metric">
                    <span class="queue-metric-icon">♙</span>

                    <div>
                        <span class="queue-eyebrow">PEOPLE AHEAD</span>

                        <strong>
                            <?= $isPriorityPending
                                ? '—'
                                : (
                                    $activeTicket['status'] === 'Serving'
                                        ? '0'
                                        : $peopleAhead
                                ) ?>
                        </strong>

                        <small>
                            <?= $isPriorityPending
                                ? 'Available after approval'
                                : (
                                    $activeTicket['status'] === 'Serving'
                                        ? 'Your turn now'
                                        : 'Before your ticket'
                                ) ?>
                        </small>
                    </div>
                </div>

                <div class="queue-metric">
                    <span class="queue-metric-icon">◷</span>

                    <div>
                        <span class="queue-eyebrow">ESTIMATED WAIT</span>

                        <strong>
                            <?= $isPriorityPending
                                ? 'Pending'
                                : (
                                    $activeTicket['status'] === 'Serving'
                                        ? 'Now'
                                        : (
                                            $estimatedMinutes === 0
                                                ? 'Next'
                                                : $estimatedMinutes . ' min'
                                        )
                                ) ?>
                        </strong>

                        <small>
                            <?= $isPriorityPending
                                ? 'Calculated after approval'
                                : 'Estimate, not a guarantee' ?>
                        </small>
                    </div>
                </div>

                <div class="queue-metric">
                    <span class="queue-metric-icon">≡</span>

                    <div>
                        <span class="queue-eyebrow">WAITING TODAY</span>

                        <strong>
                            <?= $isPriorityPending ? '—' : $waitingCount ?>
                        </strong>

                        <small>
                            <?= $isPriorityPending
                                ? 'Not counted until approval'
                                : 'Eligible tickets for this service' ?>
                        </small>
                    </div>
                </div>

            </div>

            <?php if ($activeTicket['status'] === 'Waiting'): ?>

                <div class="queue-ticket-actions">

                    <p>
                        <strong>Need to leave?</strong>
                        You can cancel your ticket while it is still waiting.
                    </p>

                    <form
                        method="POST"
                        action="my-queue.php"
                        onsubmit="return confirm('Cancel this queue ticket? This action cannot be undone.');"
                    >
                        <input
                            type="hidden"
                            name="action"
                            value="cancel_queue"
                        >

                        <input
                            type="hidden"
                            name="queue_id"
                            value="<?= (int) $activeTicket['queue_id'] ?>"
                        >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= qflow_escape(qflow_csrf_token()) ?>"
                        >

                        <button type="submit" class="button button-cancel">
                            <span aria-hidden="true">&times;</span>
                            Cancel Ticket
                        </button>
                    </form>

                </div>

            <?php endif; ?>

        </section>

    <?php else: ?>

        <section class="queue-empty-card">

            <div class="queue-empty-icon" aria-hidden="true">≡</div>

            <h2>No active ticket</h2>

            <p>
                You don't have a waiting or serving ticket today.
                Choose a finance service to get started.
            </p>

            <a href="services.php" class="button button-primary">
                Browse Finance Services
            </a>

        </section>

    <?php endif; ?>

    <section class="queue-history-card">

        <div class="queue-history-header">

            <div>
                <span class="queue-eyebrow">YOUR RECORDS</span>

                <h2>Queue History</h2>

                <p>Review your previous and current tickets.</p>
            </div>

            <span class="queue-history-count">
                <?= count($tickets) ?>
                ticket<?= count($tickets) === 1 ? '' : 's' ?>
            </span>

        </div>

        <?php if (empty($tickets)): ?>

            <div class="queue-history-empty">
                Your queue history will appear here after you get a ticket.
            </div>

        <?php else: ?>

            <div class="table-responsive">

                <table class="queue-history-table">

                    <thead>
                        <tr>
                            <th>Ticket</th>
                            <th>Service</th>
                            <th>Date</th>
                            <th>Counter</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($tickets as $ticket): ?>

                            <tr>

                                <td>
                                    <strong class="queue-history-ticket">
                                        <?= qflow_escape(getTicketNumber($ticket)) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= qflow_escape($ticket['service_name']) ?>

                                    <?php if ((int) $ticket['priority_requested'] === 1): ?>
                                        <br>
                                        <small>
                                            Priority:
                                            <?= qflow_escape(
                                                ucfirst((string) $ticket['priority_status'])
                                            ) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?= qflow_escape(
                                        date('M j, Y', strtotime($ticket['queue_date']))
                                    ) ?>
                                </td>

                                <td>
                                    <?= qflow_escape(
                                        (
                                            (int) $ticket['priority_requested'] === 1
                                            && strtolower((string) $ticket['priority_status']) !== 'approved'
                                        )
                                            ? 'Pending verification'
                                            : ($ticket['counter_name'] ?: 'Not assigned')
                                    ) ?>
                                </td>

                                <td>
                                    <span class="queue-status-badge <?= strtolower($ticket['status']) ?>">
                                        <?= qflow_escape($ticket['status']) ?>
                                    </span>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>

    <p class="queue-page-note">
        Queue estimates are based on recent completed transactions when available.
        Actual waiting times may change as staff process tickets.
    </p>

</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>