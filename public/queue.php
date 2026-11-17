
<?php

require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/send-email.php';
require_once dirname(__DIR__) . '/includes/email-templates.php';

qflow_start_session();
qflow_require_role(['Student']);
date_default_timezone_set('Asia/Manila');

$pdo = require dirname(__DIR__) . '/config/database.php';

$userId = (int) $_SESSION['user_id'];
$error = '';
$ticket = null;
$priorityRequested = '0';
$priorityCategory = null;

$queueStats = [
    'waiting' => 0,
    'serving' => null,
    'ahead' => 0,
    'average_minutes' => 5,
    'estimated_minutes' => null,
];

/*
|--------------------------------------------------------------------------
| Get the student's account ID and verify account status
|--------------------------------------------------------------------------
*/

$userStatement = $pdo->prepare(
    'SELECT account_id, account_status
     FROM users
     WHERE user_id = :user_id
     LIMIT 1'
);

$userStatement->execute(['user_id' => $userId]);

$currentUser = $userStatement->fetch(PDO::FETCH_ASSOC);

if (!$currentUser || $currentUser['account_status'] !== 'active') {
    $_SESSION = [];
    session_destroy();

    header('Location: login.php');
    exit;
}

// queues.account_id stores users.account_id (VARCHAR 50).
$accountId = (string) $currentUser['account_id'];

$serviceId = filter_input(INPUT_GET, 'service_id', FILTER_VALIDATE_INT);

if (!$serviceId && isset($_POST['service_id'])) {
    $serviceId = filter_var($_POST['service_id'], FILTER_VALIDATE_INT);
}

$serviceId = ($serviceId !== false && $serviceId !== null)
    ? (int) $serviceId
    : 0;

if ($serviceId < 1) {
    header('Location: services.php');
    exit;
}

$serviceStatement = $pdo->prepare(
    'SELECT service_id, service_name, description
     FROM services
     WHERE service_id = :service_id
       AND is_active = 1
     LIMIT 1'
);

$serviceStatement->execute(['service_id' => $serviceId]);

$service = $serviceStatement->fetch(PDO::FETCH_ASSOC);

if (!$service) {
    http_response_code(404);
    exit('The selected finance service is unavailable.');
}

function queue_page_prefix(string $serviceName): string
{
    if (stripos($serviceName, 'cashier') !== false) {
        return 'C';
    }

    if (stripos($serviceName, 'receivables') !== false) {
        return 'R';
    }

    return strtoupper(substr($serviceName, 0, 1));
}

function queue_page_ticket_number(
    string $serviceName,
    int $number
): string {
    return queue_page_prefix($serviceName)
        . '-'
        . str_pad((string) $number, 3, '0', STR_PAD_LEFT);
}

/*
|--------------------------------------------------------------------------
| Generate a ticket
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!qflow_verify_csrf($_POST['csrf_token'] ?? null)) {

        $error = 'Your session has expired. Refresh the page and try again.';

    } else {

        try {

            $pdo->beginTransaction();

            $lockStudent = $pdo->prepare(
                'SELECT user_id
                 FROM users
                 WHERE user_id = :user_id
                   AND account_status = "active"
                 FOR UPDATE'
            );

            $lockStudent->execute(['user_id' => $userId]);

            if (!$lockStudent->fetchColumn()) {
                throw new RuntimeException(
                    'Your account is not active. Please sign in again.'
                );
            }

            /*
             * Check active tickets using the student's account_id.
             */
            $activeTicketStatement = $pdo->prepare(
                "SELECT s.service_name
                 FROM queues q
                 INNER JOIN services s
                    ON s.service_id = q.service_id
                 INNER JOIN queue_remarks qr
                    ON qr.queue_remarks_id = q.queue_remarks_id
                 WHERE q.account_id = :account_id
                   AND q.queue_date = CURDATE()
                   AND qr.queue_name IN ('Waiting', 'Serving')
                 LIMIT 1
                 FOR UPDATE"
            );

            $activeTicketStatement->execute([
                'account_id' => $accountId,
            ]);

            $existingService = $activeTicketStatement->fetchColumn();

            if ($existingService) {
                throw new RuntimeException(
                    'You already have an active ticket for '
                    . $existingService
                    . '. Please finish your current queue first.'
                );
            }

            // Verify that the selected service is still active.
            $checkService = $pdo->prepare(
                'SELECT service_id, service_name
                 FROM services
                 WHERE service_id = :service_id
                   AND is_active = 1
                 LIMIT 1'
            );

            $checkService->execute([
                'service_id' => $serviceId,
            ]);

            $currentService = $checkService->fetch(PDO::FETCH_ASSOC);

            if (!$currentService) {
                throw new RuntimeException(
                    'This finance service is no longer available.'
                );
            }

            // Capture priority-lane request.
            $priorityRequested = (
                ($_POST['priority_requested'] ?? '0') === '1'
            ) ? '1' : '0';

            $priorityCategory = trim(
                (string) ($_POST['priority_category'] ?? '')
            );

            $allowedPriorityCategories = [
                'Senior citizen',
                'Person with disability (PWD)',
                'Pregnant',
            ];

            if ($priorityRequested === '1') {

                if (!in_array(
                    $priorityCategory,
                    $allowedPriorityCategories,
                    true
                )) {
                    throw new RuntimeException(
                        'Please choose the reason for requesting priority assistance.'
                    );
                }

            } else {

                $priorityCategory = null;
            }

            /*
             * Priority requests stay unassigned until staff approval.
             * Regular tickets can be assigned to a ready counter.
             */
            $counterId = null;

            if ($priorityRequested !== '1') {

                $counterStatement = $pdo->prepare(
                    "SELECT sc.counter_id
                     FROM service_counters sc
                     INNER JOIN counter_readiness cr
                        ON cr.counter_id = sc.counter_id
                     WHERE sc.service_id = :service_id
                       AND sc.is_active = 1
                       AND cr.availability_status = 'ready'
                       AND cr.staff_user_id IS NOT NULL
                       AND EXISTS (
                           SELECT 1
                           FROM staff_service_assignments a
                           WHERE a.user_id = cr.staff_user_id
                             AND a.service_id = sc.service_id
                       )
                     ORDER BY sc.counter_name ASC, sc.counter_id ASC
                     LIMIT 1
                     FOR UPDATE"
                );

                $counterStatement->execute([
                    'service_id' => $serviceId,
                ]);

                $availableCounterId = $counterStatement->fetchColumn();

                $counterId = $availableCounterId !== false
                    ? (int) $availableCounterId
                    : null;
            }

            // Get Waiting status ID.
            $statusStatement = $pdo->prepare(
                "SELECT queue_remarks_id
                 FROM queue_remarks
                 WHERE queue_name = 'Waiting'
                 LIMIT 1"
            );

            $statusStatement->execute();

            $waitingStatusId = $statusStatement->fetchColumn();

            if (!$waitingStatusId) {
                throw new RuntimeException(
                    'The Waiting status is missing from the database.'
                );
            }

            /*
             * Separate daily ticket sequence for each service.
             *
             * The counter row is locked first so that two requests for the
             * same service cannot generate the same number simultaneously.
             * We also compare the counter with the highest existing queue
             * number to recover safely if older queue records exist while
             * queue_counter is behind.
             */

            // Create today's counter row if it does not exist.
            $createCounter = $pdo->prepare(
                'INSERT INTO queue_counter
                    (service_id, queue_date, last_number)
                 VALUES (:service_id, CURDATE(), 0)
                 ON DUPLICATE KEY UPDATE
                    last_number = last_number'
            );

            $createCounter->execute([
                'service_id' => $serviceId,
            ]);

            // Lock this service's counter row for the current date.
            $lockCounter = $pdo->prepare(
                'SELECT last_number
                 FROM queue_counter
                 WHERE service_id = :service_id
                   AND queue_date = CURDATE()
                 FOR UPDATE'
            );

            $lockCounter->execute([
                'service_id' => $serviceId,
            ]);

            $lastNumber = $lockCounter->fetchColumn();

            if ($lastNumber === false) {
                throw new RuntimeException(
                    'Unable to retrieve the daily queue counter.'
                );
            }

            // Find the highest queue number already used for this service today.
            $maxQueueStatement = $pdo->prepare(
                'SELECT COALESCE(MAX(queue_number), 0)
                 FROM queues
                 WHERE service_id = :service_id
                   AND queue_date = CURDATE()'
            );

            $maxQueueStatement->execute([
                'service_id' => $serviceId,
            ]);

            $maxQueueNumber = (int) $maxQueueStatement->fetchColumn();

            // Continue after whichever value is higher.
            $nextNumber = max(
                (int) $lastNumber,
                $maxQueueNumber
            ) + 1;

            // Save the new number before inserting the queue ticket.
            $updateCounter = $pdo->prepare(
                'UPDATE queue_counter
                 SET last_number = :last_number
                 WHERE service_id = :service_id
                   AND queue_date = CURDATE()'
            );

            $updateCounter->execute([
                'last_number' => $nextNumber,
                'service_id' => $serviceId,
            ]);

            // Insert queue ticket.
            $insertQueue = $pdo->prepare(
                'INSERT INTO queues
                    (
                        queue_number,
                        account_id,
                        service_id,
                        counter_id,
                        queue_remarks_id,
                        queue_date,
                        ticket_type,
                        created_by,
                        priority_requested,
                        priority_category,
                        priority_status
                    )
                 VALUES
                    (
                        :queue_number,
                        :account_id,
                        :service_id,
                        :counter_id,
                        :status_id,
                        CURDATE(),
                        "student",
                        :created_by,
                        :priority_requested,
                        :priority_category,
                        :priority_status
                    )'
            );

            $insertQueue->execute([
                'queue_number' => $nextNumber,
                'account_id' => $accountId,
                'service_id' => $serviceId,
                'counter_id' => $counterId,
                'status_id' => $waitingStatusId,
                'created_by' => $userId,
                'priority_requested' => (int) $priorityRequested,
                'priority_category' => $priorityCategory,
                'priority_status' => $priorityRequested === '1'
                    ? 'pending'
                    : 'not_requested',
            ]);

            $queueId = (int) $pdo->lastInsertId();

            // Record initial queue status history.
            $historyStatement = $pdo->prepare(
                'INSERT INTO queue_status
                    (
                        queue_id,
                        previous_status_id,
                        new_status_id,
                        changed_by
                    )
                 VALUES (:queue_id, NULL, :status_id, :changed_by)'
            );

            $historyStatement->execute([
                'queue_id' => $queueId,
                'status_id' => $waitingStatusId,
                'changed_by' => $userId,
            ]);

            /*
            |--------------------------------------------------------------------------
            | TRANSACTION LOGGING
            |--------------------------------------------------------------------------
            */

            // Log successful queue generation.
            qflow_log_transaction(
                $pdo,
                'QUEUE_GENERATED',
                'queues',
                $queueId,
                'Generated ticket '
                    . queue_page_ticket_number(
                        $currentService['service_name'],
                        $nextNumber
                    )
                    . ' for '
                    . $currentService['service_name']
            );

            // Log priority assistance request, if applicable.
            if ($priorityRequested === '1') {

                qflow_log_transaction(
                    $pdo,
                    'PRIORITY_REQUESTED',
                    'queues',
                    $queueId,
                    'Requested priority assistance: '
                        . $priorityCategory
                        . ' for '
                        . $currentService['service_name']
                );
            }

            // Log immediate counter assignment, if applicable.
            if ($counterId !== null) {

                qflow_log_transaction(
                    $pdo,
                    'COUNTER_ASSIGNED',
                    'queues',
                    $queueId,
                    'Queue ticket assigned to counter ID '
                        . $counterId
                );
            }

            // Commit ticket and logs together.
            $pdo->commit();

            /*
            |--------------------------------------------------------------------------
            | Send Queue Confirmed email
            |--------------------------------------------------------------------------
            */

            try {

                $studentStatement = $pdo->prepare(
                    'SELECT email, first_name, last_name
                     FROM users
                     WHERE user_id = :user_id
                     LIMIT 1'
                );

                $studentStatement->execute([
                    'user_id' => $userId,
                ]);

                $student = $studentStatement->fetch(PDO::FETCH_ASSOC);

                if ($student && !empty($student['email'])) {

                    $ticketNumber = queue_page_ticket_number(
                        $currentService['service_name'],
                        $nextNumber
                    );

                    $emailHtml = queueFlowEmailTemplate(
                        'queued',
                        [
                            'name' => trim(
                                ($student['first_name'] ?? '') . ' ' .
                                ($student['last_name'] ?? '')
                            ),
                            'ticket' => $ticketNumber,
                            'service' => $currentService['service_name'],
                        ]
                    );

                    $emailSent = sendEmail(
                        $student['email'],
                        'QueueFlow: Your Queue Ticket is Confirmed!',
                        $emailHtml
                    );

                    if ($emailSent) {

                        error_log(
                            'QueueFlow: Queue confirmation email sent successfully to student account ID '
                            . $userId
                        );

                    } else {

                        error_log(
                            'QueueFlow: Failed to send queue confirmation email to student account ID '
                            . $userId
                        );
                    }

                } else {

                    error_log(
                        'QueueFlow: Student email address is missing for account ID '
                        . $userId
                    );
                }

            } catch (Throwable $emailError) {

                error_log(
                    'QueueFlow confirmation email error: '
                    . $emailError->getMessage()
                );
            }

            header(
                'Location: queue.php?service_id='
                . $serviceId
                . '&ticket='
                . $queueId
            );

            exit;

        } catch (Throwable $exception) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($exception instanceof RuntimeException) {

                $error = $exception->getMessage();

            } else {

                error_log(
                    'Queue generation error: ' . $exception->getMessage()
                );

                $error = 'We could not generate your ticket. '
                    . 'Please try again.';
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Load the student's ticket and live queue information
|--------------------------------------------------------------------------
*/

$ticketId = filter_input(INPUT_GET, 'ticket', FILTER_VALIDATE_INT);

if ($ticketId && $ticketId > 0) {

    // Pending priority requests remain unassigned.
    $pendingUnassign = $pdo->prepare(
        "UPDATE queues
         SET counter_id = NULL
         WHERE queue_id = :queue_id
           AND account_id = :account_id
           AND service_id = :service_id
           AND priority_requested = 1
           AND priority_status <> 'approved'"
    );

    $pendingUnassign->execute([
        'queue_id' => $ticketId,
        'account_id' => $accountId,
        'service_id' => $serviceId,
    ]);

    // Approved priority requests belong to Counter 4.
    $assignApprovedPriority = $pdo->prepare(
        "UPDATE queues q
         INNER JOIN service_counters sc
            ON sc.service_id = q.service_id
           AND sc.is_active = 1
           AND sc.counter_name = 'Counter 4'
         SET q.counter_id = sc.counter_id
         WHERE q.queue_id = :queue_id
           AND q.account_id = :account_id
           AND q.service_id = :service_id
           AND q.priority_requested = 1
           AND q.priority_status = 'approved'
         LIMIT 1"
    );

    $assignApprovedPriority->execute([
        'queue_id' => $ticketId,
        'account_id' => $accountId,
        'service_id' => $serviceId,
    ]);

    // Load the student's ticket using account_id.
    $ticketStatement = $pdo->prepare(
        'SELECT
            q.queue_id,
            q.queue_number,
            q.queue_date,
            q.created_at,
            q.called_at,
            q.completed_at,
            q.counter_id,
            q.priority_requested,
            q.priority_category,
            q.priority_status,
            s.service_name,
            qr.queue_name,
            sc.counter_name
         FROM queues q
         INNER JOIN services s
            ON s.service_id = q.service_id
         INNER JOIN queue_remarks qr
            ON qr.queue_remarks_id = q.queue_remarks_id
         LEFT JOIN service_counters sc
            ON sc.counter_id = q.counter_id
         WHERE q.queue_id = :queue_id
           AND q.account_id = :account_id
           AND q.service_id = :service_id
         LIMIT 1'
    );

    $ticketStatement->execute([
        'queue_id' => $ticketId,
        'account_id' => $accountId,
        'service_id' => $serviceId,
    ]);

    $ticket = $ticketStatement->fetch(PDO::FETCH_ASSOC) ?: null;
}

if ($ticket) {

    $isPendingPriority = !empty($ticket['priority_requested'])
        && $ticket['priority_status'] !== 'approved';

    if (
        !$isPendingPriority &&
        !empty($ticket['counter_id']) &&
        $ticket['queue_date'] === date('Y-m-d')
    ) {

        $waitingStatement = $pdo->prepare(
            "SELECT COUNT(*)
             FROM queues q
             INNER JOIN queue_remarks qr
                ON qr.queue_remarks_id = q.queue_remarks_id
             WHERE q.service_id = :service_id
               AND q.counter_id = :counter_id
               AND q.queue_date = CURDATE()
               AND qr.queue_name = 'Waiting'
               AND (
                    q.priority_requested = 0
                    OR q.priority_status = 'approved'
               )"
        );

        $waitingStatement->execute([
            'service_id' => $serviceId,
            'counter_id' => (int) $ticket['counter_id'],
        ]);

        $queueStats['waiting'] = (int) $waitingStatement->fetchColumn();

        $servingStatement = $pdo->prepare(
            "SELECT q.queue_number
             FROM queues q
             INNER JOIN queue_remarks qr
                ON qr.queue_remarks_id = q.queue_remarks_id
             WHERE q.service_id = :service_id
               AND q.counter_id = :counter_id
               AND q.queue_date = CURDATE()
               AND qr.queue_name = 'Serving'
               AND (
                    q.priority_requested = 0
                    OR q.priority_status = 'approved'
               )
             ORDER BY q.called_at ASC, q.queue_number ASC
             LIMIT 1"
        );

        $servingStatement->execute([
            'service_id' => $serviceId,
            'counter_id' => (int) $ticket['counter_id'],
        ]);

        $servingNumber = $servingStatement->fetchColumn();

        if ($servingNumber !== false) {
            $queueStats['serving'] = (int) $servingNumber;
        }

        $aheadStatement = $pdo->prepare(
            "SELECT COUNT(*)
             FROM queues q
             INNER JOIN queue_remarks qr
                ON qr.queue_remarks_id = q.queue_remarks_id
             WHERE q.service_id = :service_id
               AND q.counter_id = :counter_id
               AND q.queue_date = CURDATE()
               AND qr.queue_name IN ('Waiting', 'Serving')
               AND (
                    q.priority_requested = 0
                    OR q.priority_status = 'approved'
               )
               AND (
                    (
                        q.priority_requested = 1
                        AND q.priority_status = 'approved'
                        AND (
                            :is_priority = 0
                            OR q.queue_number < :ticket_number_priority
                        )
                    )
                    OR
                    (
                        (
                            q.priority_requested = 0
                            OR q.priority_status <> 'approved'
                        )
                        AND :is_priority_regular = 0
                        AND q.queue_number < :ticket_number_regular
                    )
                    OR
                    (
                        :is_priority_regular = 1
                        AND q.priority_requested = 1
                        AND q.priority_status = 'approved'
                    )
               )"
        );

        $isPriority = !empty($ticket['priority_requested'])
            && $ticket['priority_status'] === 'approved';

        $aheadStatement->execute([
            'service_id' => $serviceId,
            'counter_id' => (int) $ticket['counter_id'],
            'is_priority' => $isPriority ? 1 : 0,
            'ticket_number_priority' => (int) $ticket['queue_number'],
            'is_priority_regular' => $isPriority ? 1 : 0,
            'ticket_number_regular' => (int) $ticket['queue_number'],
        ]);

        $queueStats['ahead'] = (int) $aheadStatement->fetchColumn();

        $averageStatement = $pdo->prepare(
            "SELECT AVG(service_minutes)
             FROM (
                 SELECT
                    TIMESTAMPDIFF(
                        SECOND,
                        q.called_at,
                        q.completed_at
                    ) / 60 AS service_minutes
                 FROM queues q
                 INNER JOIN queue_remarks qr
                    ON qr.queue_remarks_id = q.queue_remarks_id
                 WHERE q.service_id = :service_id
                   AND qr.queue_name = 'Completed'
                   AND q.called_at IS NOT NULL
                   AND q.completed_at IS NOT NULL
                   AND q.completed_at >= q.called_at
                   AND q.queue_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                 ORDER BY q.completed_at DESC
                 LIMIT 30
             ) AS recent_completed"
        );

        $averageStatement->execute([
            'service_id' => $serviceId,
        ]);

        $averageMinutes = $averageStatement->fetchColumn();

        $queueStats['average_minutes'] = (
            $averageMinutes !== false &&
            $averageMinutes !== null &&
            (float) $averageMinutes > 0
        )
            ? max(1, (int) ceil((float) $averageMinutes))
            : 5;

        if ($ticket['queue_name'] === 'Waiting') {

            $queueStats['estimated_minutes'] =
                max(1, $queueStats['ahead']) *
                $queueStats['average_minutes'];

            if ($queueStats['ahead'] === 0) {
                $queueStats['estimated_minutes'] = 0;
            }

        } elseif ($ticket['queue_name'] === 'Serving') {

            $queueStats['estimated_minutes'] = 0;
        }
    }
}

$pageTitle = 'Get Queue Number';

require dirname(__DIR__) . '/includes/header.php';
require dirname(__DIR__) . '/includes/sidebar.php';
?>

<style>
.main-content {
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.main-content .page-header,
.main-content .section-block,
.main-content .page-footer {
    width: 100%;
    max-width: none;
    box-sizing: border-box;
}

.main-content .queue-detail-card {
    width: 100%;
    max-width: none;
    box-sizing: border-box;
}
</style>

<main class="main-content">

    <header class="page-header">
        <div>
            <p class="eyebrow">STUDENT PORTAL</p>

            <h1>Get Queue Number</h1>

            <p class="page-description">
                <?= qflow_escape($service['service_name']) ?>
                — request your ticket for today's queue.
            </p>
        </div>
    </header>

    <?php if ($error !== ''): ?>

        <div class="alert alert-error" role="alert">
            <?= qflow_escape($error) ?>
        </div>

    <?php endif; ?>

    <section class="section-block">

        <div class="queue-detail-card">

            <?php if ($ticket): ?>

                <div class="ticket-success-heading">
                    <span class="ticket-success-icon">✓</span>

                    <h2>Your queue ticket is ready</h2>

                    <p>Keep your ticket number for reference.</p>
                </div>

                <div class="generated-ticket">
                    <span class="muted-label">YOUR TICKET NUMBER</span>

                    <strong>
                        <?= qflow_escape(
                            queue_page_ticket_number(
                                $ticket['service_name'],
                                (int) $ticket['queue_number']
                            )
                        ) ?>
                    </strong>

                    <span>
                        <?= qflow_escape($ticket['service_name']) ?>
                    </span>
                </div>

                <div class="ticket-status-row">
                    <span>Assigned counter</span>

                    <strong>
                        <?= qflow_escape(
                            $ticket['counter_name'] ?: 'Not assigned'
                        ) ?>
                    </strong>
                </div>

                <?php if (!empty($ticket['priority_requested'])): ?>

                    <div class="ticket-status-row">
                        <span>Priority assistance</span>

                        <strong>
                            <?= qflow_escape(
                                $ticket['priority_status'] === 'approved'
                                    ? 'Approved'
                                    : 'Pending verification'
                            ) ?>
                        </strong>
                    </div>

                    <div class="ticket-status-row">
                        <span>Request reason</span>

                        <strong>
                            <?= qflow_escape(
                                $ticket['priority_category'] ?? 'Not specified'
                            ) ?>
                        </strong>
                    </div>

                <?php endif; ?>

                <div class="ticket-status-row">
                    <span>Status</span>

                    <span class="status-pill status-waiting">
                        <?= qflow_escape(
                            !empty($ticket['priority_requested']) &&
                            $ticket['priority_status'] !== 'approved'
                                ? 'Awaiting priority verification'
                                : $ticket['queue_name']
                        ) ?>
                    </span>
                </div>

                <?php if (
                    empty($ticket['priority_requested']) ||
                    $ticket['priority_status'] === 'approved'
                ): ?>

                    <div class="ticket-status-row">
                        <span>People ahead</span>

                        <strong>
                            <?= (int) $queueStats['ahead'] ?>
                        </strong>
                    </div>

                <?php endif; ?>

                <div class="ticket-status-row">
                    <span>Estimated waiting time</span>

                    <strong>

                        <?php if (
                            !empty($ticket['priority_requested']) &&
                            $ticket['priority_status'] !== 'approved'
                        ): ?>

                            Available after approval

                        <?php elseif ($queueStats['estimated_minutes'] === null): ?>

                            Not available

                        <?php elseif ($ticket['queue_name'] === 'Serving'): ?>

                            You're being served now

                        <?php elseif ($queueStats['estimated_minutes'] === 0): ?>

                            You're next

                        <?php else: ?>

                            About <?= (int) $queueStats['estimated_minutes'] ?>
                            minutes (updates every 10 seconds)

                        <?php endif; ?>

                    </strong>
                </div>

                <?php if (
                    empty($ticket['priority_requested']) ||
                    $ticket['priority_status'] === 'approved'
                ): ?>

                    <div class="ticket-status-row">
                        <span>Total students waiting</span>

                        <strong>
                            <?= (int) $queueStats['waiting'] ?>
                        </strong>
                    </div>

                <?php endif; ?>

                <div class="queue-note">
                    Waiting times are estimates and may change depending on
                    service speed and the number of students in the queue.
                </div>

                <div class="ticket-actions">

                    <a href="my-queue.php" class="button button-primary">
                        View My Queue
                    </a>

                    <a href="index.php" class="button button-secondary">
                        Back to Dashboard
                    </a>

                </div>

            <?php else: ?>

                <div class="service-card-icon">
                    <?= qflow_escape(
                        strtoupper(substr($service['service_name'], 0, 1))
                    ) ?>
                </div>

                <p class="eyebrow">SELECTED FINANCE SERVICE</p>

                <h2>
                    <?= qflow_escape($service['service_name']) ?>
                </h2>

                <p class="page-description">
                    <?= qflow_escape(
                        $service['description']
                        ?: 'Request a queue number for this finance service.'
                    ) ?>
                </p>

                <div class="queue-note">
                    <strong>Before you continue</strong>

                    <p>
                        Make sure this is the service you need. You can only
                        have one active ticket at a time.
                    </p>

                    <p>
                        Your ticket will be added to today's queue after
                        you confirm.
                    </p>
                </div>

                <form
                    id="getQueueForm"
                    method="POST"
                    action="queue.php?service_id=<?= (int) $serviceId ?>"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= qflow_escape(qflow_csrf_token()) ?>"
                    >

                    <input
                        type="hidden"
                        name="service_id"
                        value="<?= (int) $serviceId ?>"
                    >

                    <input
                        type="hidden"
                        name="priority_requested"
                        id="priorityRequested"
                        value="0"
                    >

                    <input
                        type="hidden"
                        name="priority_category"
                        id="priorityCategory"
                        value=""
                    >

                    <div class="ticket-actions">

                        <button
                            class="button button-primary"
                            type="button"
                            id="openPriorityModal"
                        >
                            Continue and Get Ticket
                        </button>

                        <a
                            href="services.php"
                            class="button button-secondary"
                        >
                            Choose Another Service
                        </a>

                    </div>

                </form>

                <div
                    id="priorityModal"
                    class="priority-modal-backdrop"
                    hidden
                >

                    <section
                        class="priority-modal"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="priorityModalTitle"
                    >

                        <button
                            type="button"
                            class="priority-modal-close"
                            id="closePriorityModal"
                            aria-label="Close"
                        >
                            &times;
                        </button>

                        <span class="eyebrow">OPTIONAL ASSISTANCE</span>

                        <h2 id="priorityModalTitle">
                            Do you need priority-lane assistance?
                        </h2>

                        <p>
                            If you are eligible for priority assistance,
                            let us know so staff can verify your request.
                            You can continue with a regular ticket if you
                            do not need it.
                        </p>

                        <div class="priority-choice-row">

                            <button
                                type="button"
                                class="button button-secondary"
                                id="regularTicketButton"
                            >
                                No, regular queue
                            </button>

                            <button
                                type="button"
                                class="button button-primary"
                                id="requestPriorityButton"
                            >
                                Yes, I need assistance
                            </button>

                        </div>

                        <div id="priorityCategoryPanel" hidden>

                            <label for="priorityCategorySelect">
                                Please select the reason
                            </label>

                            <select id="priorityCategorySelect">

                                <option value="">
                                    Choose a reason
                                </option>

                                <option value="Senior citizen">
                                    Senior citizen
                                </option>

                                <option value="Person with disability (PWD)">
                                    Person with disability (PWD)
                                </option>

                                <option value="Pregnant">
                                    Pregnant student
                                </option>

                            </select>

                            <p class="priority-modal-note">
                                Your request will be marked pending until
                                finance staff verifies it.
                            </p>

                            <div class="priority-choice-row">

                                <button
                                    type="button"
                                    class="button button-secondary"
                                    id="backPriorityChoice"
                                >
                                    Back
                                </button>

                                <button
                                    type="button"
                                    class="button button-primary"
                                    id="submitPriorityRequest"
                                >
                                    Submit request and get ticket
                                </button>

                            </div>

                            <p
                                id="priorityModalError"
                                class="priority-modal-error"
                                role="alert"
                                hidden
                            >
                                Please select a reason for your request.
                            </p>

                        </div>

                    </section>

                </div>

            <?php endif; ?>

        </div>

    </section>

    <footer class="page-footer">
        <span>
            QueueFlow — RMMC-MI Finance Queue Management System
        </span>

        <span><?= date('Y') ?></span>
    </footer>

</main>

<?php if ($ticket): ?>

<script>
    window.setTimeout(function () {
        window.location.reload();
    }, 10000);
</script>

<?php endif; ?>

<?php if (!$ticket): ?>

<style>
.priority-modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(15,23,42,.65);
}

.priority-modal-backdrop[hidden],
[hidden] {
    display: none !important;
}

.priority-modal {
    position: relative;
    width: min(100%, 520px);
    max-height: 90vh;
    overflow: auto;
    background: #fff;
    border-radius: 18px;
    padding: 28px;
    box-shadow: 0 24px 80px rgba(0,0,0,.25);
    color: #1f2937;
}

.priority-modal h2 {
    margin: 8px 0 12px;
    font-size: 1.45rem;
    line-height: 1.3;
}

.priority-modal p {
    line-height: 1.6;
    color: #5b6472;
}

.priority-modal-close {
    position: absolute;
    right: 16px;
    top: 12px;
    border: 0;
    background: transparent;
    font-size: 28px;
    cursor: pointer;
    color: #64748b;
}

.priority-choice-row {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 20px;
}

.priority-choice-row .button {
    flex: 1;
    justify-content: center;
    min-width: 170px;
}

#priorityCategoryPanel {
    margin-top: 20px;
}

#priorityCategoryPanel label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
}

#priorityCategoryPanel select {
    width: 100%;
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    background: #fff;
    font: inherit;
}

.priority-modal-note {
    font-size: .9rem;
    margin-bottom: 0;
}

.priority-modal-error {
    color: #b91c1c !important;
    font-size: .9rem;
}

@media(max-width:520px) {
    .priority-modal {
        padding: 24px 18px;
    }

    .priority-choice-row {
        flex-direction: column;
    }

    .priority-choice-row .button {
        width: 100%;
    }
}
</style>

<script>
(function () {

    const modal = document.getElementById('priorityModal');
    const openButton = document.getElementById('openPriorityModal');
    const closeButton = document.getElementById('closePriorityModal');

    const regularButton = document.getElementById('regularTicketButton');
    const requestButton = document.getElementById('requestPriorityButton');

    const categoryPanel = document.getElementById('priorityCategoryPanel');
    const categorySelect = document.getElementById('priorityCategorySelect');

    const requestedInput = document.getElementById('priorityRequested');
    const categoryInput = document.getElementById('priorityCategory');

    const submitRequestButton = document.getElementById('submitPriorityRequest');
    const backButton = document.getElementById('backPriorityChoice');

    const error = document.getElementById('priorityModalError');
    const form = document.getElementById('getQueueForm');

    if (!modal || !form) return;

    function showModal() {
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
    }

    function hideModal() {
        modal.hidden = true;
        document.body.style.overflow = '';
    }

    function submitTicket(requested, category) {

        requestedInput.value = requested ? '1' : '0';
        categoryInput.value = category || '';

        form.submit();
    }

    openButton.addEventListener('click', showModal);

    closeButton.addEventListener('click', hideModal);

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            hideModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !modal.hidden) {
            hideModal();
        }
    });

    regularButton.addEventListener('click', function () {
        submitTicket(false, '');
    });

    requestButton.addEventListener('click', function () {

        categoryPanel.hidden = false;
        error.hidden = true;

        categorySelect.focus();
    });

    backButton.addEventListener('click', function () {

        categoryPanel.hidden = true;
        error.hidden = true;
    });

    submitRequestButton.addEventListener('click', function () {

        if (!categorySelect.value) {

            error.hidden = false;
            categorySelect.focus();

            return;
        }

        submitTicket(true, categorySelect.value);
    });

})();
</script>

<?php endif; ?>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
