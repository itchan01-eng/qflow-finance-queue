
<?php

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/push-notifications.php';
require_once dirname(__DIR__, 2) . '/includes/send-email.php';
require_once dirname(__DIR__, 2) . '/includes/email-templates.php';

qflow_start_session();
qflow_require_role(['Staff'], '/staff/login.php');

$pdo = require dirname(__DIR__, 2) . '/config/database.php';

$staffId = (int) ($_SESSION['user_id'] ?? 0);

$error = '';
$success = '';

/*
|--------------------------------------------------------------------------
| HELPER FUNCTIONS
|--------------------------------------------------------------------------
*/

function staff_queue_escape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function staff_queue_prefix(string $serviceName): string
{
    if (stripos($serviceName, 'cashier') !== false) {
        return 'C';
    }

    if (stripos($serviceName, 'receivables') !== false) {
        return 'R';
    }

    return strtoupper(substr($serviceName, 0, 1));
}

function staff_queue_ticket(string $serviceName, int $number): string
{
    return staff_queue_prefix($serviceName)
        . '-'
        . str_pad((string) $number, 3, '0', STR_PAD_LEFT);
}

function staff_queue_status_class(string $status): string
{
    return match ($status) {
        'Waiting' => 'status-waiting',
        'Serving' => 'status-serving',
        'Completed' => 'status-completed',
        'Skipped', 'No Show', 'Cancelled' => 'status-cancelled',
        default => 'status-default',
    };
}

/*
|--------------------------------------------------------------------------
| CANCELLED TICKET EMAIL TEMPLATE
|--------------------------------------------------------------------------
*/

function staff_queue_cancelled_email_template(array $data): string
{
    $name = htmlspecialchars(
        (string) ($data['name'] ?? 'Student'),
        ENT_QUOTES,
        'UTF-8'
    );

    $ticket = htmlspecialchars(
        (string) ($data['ticket'] ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );

    $service = htmlspecialchars(
        (string) ($data['service'] ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );

    $year = date('Y');

    return '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>QueueFlow Ticket Cancelled</title>
    </head>

    <body style="
        margin:0;
        padding:0;
        background-color:#f7f7f9;
        font-family:Arial,Helvetica,sans-serif;
        color:#202124;
    ">

        <table role="presentation" width="100%" cellpadding="0"
            cellspacing="0" border="0"
            style="background-color:#f7f7f9;padding:35px 12px;">
            <tr>
                <td align="center">

                    <table role="presentation" width="100%"
                        cellpadding="0" cellspacing="0" border="0"
                        style="
                            max-width:560px;
                            background-color:#ffffff;
                            border:1px solid #e9e9ed;
                            border-radius:12px;
                            overflow:hidden;
                        ">

                        <tr>
                            <td style="
                                background-color:#c8102e;
                                padding:25px 30px;
                                text-align:center;
                            ">
                                <h1 style="
                                    margin:0;
                                    color:#ffffff;
                                    font-size:25px;
                                    font-weight:700;
                                ">QueueFlow</h1>

                                <p style="
                                    margin:7px 0 0;
                                    color:#ffffff;
                                    font-size:12px;
                                    letter-spacing:1px;
                                ">
                                    RMMC-MI FINANCE QUEUE MANAGEMENT SYSTEM
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:35px 30px 20px;">
                                <p style="
                                    margin:0 0 12px;
                                    font-size:14px;
                                    color:#737780;
                                ">
                                    Hello ' . $name . ',
                                </p>

                                <h2 style="
                                    margin:0 0 15px;
                                    font-size:23px;
                                    color:#202124;
                                ">
                                    Your queue ticket has been cancelled.
                                </h2>

                                <p style="
                                    margin:0;
                                    font-size:14px;
                                    line-height:1.8;
                                    color:#737780;
                                ">
                                    Your QueueFlow ticket has been marked as
                                    cancelled by the Finance Office.
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:10px 30px 25px;">
                                <table role="presentation" width="100%"
                                    cellpadding="0" cellspacing="0" border="0"
                                    style="
                                        background-color:#fff0f2;
                                        border:1px solid #f5d5da;
                                        border-radius:10px;
                                    ">
                                    <tr>
                                        <td style="
                                            padding:22px;
                                            text-align:center;
                                        ">
                                            <p style="
                                                margin:0 0 8px;
                                                color:#737780;
                                                font-size:11px;
                                                font-weight:700;
                                                letter-spacing:1px;
                                            ">CANCELLED TICKET</p>

                                            <h1 style="
                                                margin:0;
                                                color:#c8102e;
                                                font-size:34px;
                                            ">' . $ticket . '</h1>

                                            <p style="
                                                margin:9px 0 0;
                                                color:#202124;
                                                font-size:14px;
                                            ">' . $service . '</p>

                                            <p style="
                                                margin:13px 0 0;
                                                display:inline-block;
                                                padding:6px 13px;
                                                background-color:#ffffff;
                                                border:1px solid #e9e9ed;
                                                border-radius:20px;
                                                color:#c8102e;
                                                font-size:12px;
                                                font-weight:700;
                                            ">CANCELLED</p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:0 30px 30px;">
                                <p style="
                                    margin:0 0 12px;
                                    font-size:14px;
                                    line-height:1.7;
                                    color:#737780;
                                ">
                                    If you believe this cancellation was made
                                    in error or need further assistance,
                                    please contact the Finance Office.
                                </p>

                                <p style="
                                    margin:0;
                                    font-size:14px;
                                    line-height:1.7;
                                    color:#737780;
                                ">
                                    Thank you for your understanding.
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <td style="
                                padding:20px 30px;
                                background-color:#f7f7f9;
                                border-top:1px solid #e9e9ed;
                                text-align:center;
                            ">
                                <p style="
                                    margin:0 0 6px;
                                    color:#202124;
                                    font-size:12px;
                                    font-weight:700;
                                ">QueueFlow</p>

                                <p style="
                                    margin:0;
                                    color:#737780;
                                    font-size:11px;
                                    line-height:1.6;
                                ">
                                    RMMC-MI Finance Queue Management System
                                    <br>
                                    &copy; ' . $year . ' All rights reserved.
                                </p>
                            </td>
                        </tr>

                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>';
}

/*
|--------------------------------------------------------------------------
| CHECK STAFF ACCOUNT
|--------------------------------------------------------------------------
*/

$accountStatement = $pdo->prepare(
    "SELECT account_status
     FROM users
     WHERE user_id = :user_id
     LIMIT 1"
);

$accountStatement->execute([
    'user_id' => $staffId,
]);

if ($accountStatement->fetchColumn() !== 'active') {
    $_SESSION = [];
    session_destroy();

    header('Location: login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| LOAD ASSIGNED SERVICES
|--------------------------------------------------------------------------
*/

$assignedStatement = $pdo->prepare(
    "SELECT
        s.service_id,
        s.service_name,
        s.description
     FROM staff_service_assignments a
     INNER JOIN services s
        ON s.service_id = a.service_id
     WHERE a.user_id = :user_id
       AND s.is_active = 1
     ORDER BY s.service_name"
);

$assignedStatement->execute([
    'user_id' => $staffId,
]);

$assignedServices = $assignedStatement->fetchAll(PDO::FETCH_ASSOC);

$requestedServiceId = filter_input(
    INPUT_GET,
    'service_id',
    FILTER_VALIDATE_INT
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestedServiceId = filter_var(
        $_POST['service_id'] ?? null,
        FILTER_VALIDATE_INT
    );
}

$serviceId = $requestedServiceId ? (int) $requestedServiceId : 0;

if ($serviceId === 0 && count($assignedServices) === 1) {
    $serviceId = (int) $assignedServices[0]['service_id'];
}

$selectedService = null;

foreach ($assignedServices as $assignedService) {
    if ((int) $assignedService['service_id'] === $serviceId) {
        $selectedService = $assignedService;
        break;
    }
}

if ($serviceId > 0 && !$selectedService) {
    http_response_code(403);
    exit('You are not assigned to manage this finance service.');
}

/*
|--------------------------------------------------------------------------
| PROCESS QUEUE ACTIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $selectedService) {

    if (!qflow_verify_csrf($_POST['csrf_token'] ?? null)) {

        $error = 'Your session expired. Refresh the page and try again.';

    } else {

        $action = (string) ($_POST['action'] ?? '');

        $queueId = filter_var(
            $_POST['queue_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        try {

            $pdo->beginTransaction();

            $pushNotification = null;
            $emailNotification = null;

            // Verify staff service assignment.
            $assignmentCheck = $pdo->prepare(
                "SELECT a.assignment_id
                 FROM staff_service_assignments a
                 INNER JOIN services s
                    ON s.service_id = a.service_id
                 WHERE a.user_id = :user_id
                   AND a.service_id = :service_id
                   AND s.is_active = 1
                 FOR UPDATE"
            );

            $assignmentCheck->execute([
                'user_id' => $staffId,
                'service_id' => $serviceId,
            ]);

            if (!$assignmentCheck->fetchColumn()) {
                throw new RuntimeException(
                    'You are no longer assigned to this service.'
                );
            }

            // Load queue status IDs.
            $statusQuery = $pdo->query(
                'SELECT queue_name, queue_remarks_id FROM queue_remarks'
            );

            $statusIds = [];

            foreach ($statusQuery->fetchAll(PDO::FETCH_ASSOC) as $statusRow) {
                $statusIds[$statusRow['queue_name']] =
                    (int) $statusRow['queue_remarks_id'];
            }

            $requiredStatuses = [
                'Waiting',
                'Serving',
                'Completed',
                'Skipped',
                'No Show',
                'Cancelled',
            ];

            foreach ($requiredStatuses as $requiredStatus) {
                if (!isset($statusIds[$requiredStatus])) {
                    throw new RuntimeException(
                        'The queue status "' . $requiredStatus .
                        '" is missing from the database.'
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | CALL NEXT TICKET
            |--------------------------------------------------------------------------
            */

            if ($action === 'call_next') {

                // Get and lock this staff member's assigned counter.
                $counterStatement = $pdo->prepare(
                    "SELECT
                        sc.counter_id,
                        sc.counter_name,
                        cr.availability_status
                     FROM service_counters sc
                     INNER JOIN counter_readiness cr
                        ON cr.counter_id = sc.counter_id
                     WHERE sc.service_id = :service_id
                       AND sc.is_active = 1
                       AND cr.staff_user_id = :staff_id
                     LIMIT 1
                     FOR UPDATE"
                );

                $counterStatement->execute([
                    'service_id' => $serviceId,
                    'staff_id' => $staffId,
                ]);

                $staffCounter = $counterStatement->fetch(PDO::FETCH_ASSOC);

                if (!$staffCounter) {
                    throw new RuntimeException(
                        'You do not have an active counter assigned to this service.'
                    );
                }

                if ($staffCounter['availability_status'] === 'unavailable') {
                    throw new RuntimeException(
                        'Your counter is unavailable. Mark yourself Available before calling a ticket.'
                    );
                }

                $counterIdForCall = (int) $staffCounter['counter_id'];

                // Counter 4 is the designated priority counter.
                $isPriorityCounter = stripos(
                    (string) $staffCounter['counter_name'],
                    'counter 4'
                ) !== false;

                /*
                 * FIND THE NEXT WAITING TICKET FIRST.
                 *
                 * This is important: the current ticket must not be
                 * completed if there is no replacement ticket.
                 */

                $nextStatement = $pdo->prepare(
    "SELECT
        q.queue_id,
        q.queue_number
     FROM queues q
     WHERE q.service_id = :service_id
       AND q.queue_date = CURDATE()
       AND q.queue_remarks_id = :waiting_status
       AND (
            (
                q.priority_requested = 1
                AND q.priority_status = 'approved'
                AND :is_priority_counter = 1
                AND q.counter_id = :priority_counter_id
            )
            OR
            (
                (
                    COALESCE(q.priority_requested, 0) = 0
                    OR q.priority_status = 'rejected'
                )
                AND (
                    q.counter_id = :regular_counter_id
                    OR q.counter_id IS NULL
                )
            )
       )
     ORDER BY
        CASE
            WHEN q.priority_requested = 1
             AND q.priority_status = 'approved'
            THEN 0
            ELSE 1
        END DESC,
        q.queue_number DESC,
        q.created_at DESC
     LIMIT 1
     FOR UPDATE"
);

$nextStatement->execute([
    'service_id' => $serviceId,
    'waiting_status' => $statusIds['Waiting'],
    'is_priority_counter' => $isPriorityCounter ? 1 : 0,
    'priority_counter_id' => $counterIdForCall,
    'regular_counter_id' => $counterIdForCall,
]);

$nextQueue = $nextStatement->fetch(PDO::FETCH_ASSOC);

                if (!$nextQueue) {
                    throw new RuntimeException(
                        'No more waiting tickets for today. Complete your current ticket manually if you have finished serving it.'
                    );
                }

                /*
                 * A replacement ticket exists.
                 * It is now safe to automatically complete the current
                 * ticket if the counter is busy.
                 */

                if ($staffCounter['availability_status'] === 'busy') {

                    $currentTicketStatement = $pdo->prepare(
                        "SELECT
                            queue_id,
                            queue_number,
                            queue_remarks_id
                         FROM queues
                         WHERE service_id = :service_id
                           AND queue_date = CURDATE()
                           AND counter_id = :counter_id
                           AND served_by = :staff_id
                           AND queue_remarks_id = :serving_status
                         LIMIT 1
                         FOR UPDATE"
                    );

                    $currentTicketStatement->execute([
                        'service_id' => $serviceId,
                        'counter_id' => $counterIdForCall,
                        'staff_id' => $staffId,
                        'serving_status' => $statusIds['Serving'],
                    ]);

                    $currentTicket = $currentTicketStatement->fetch(PDO::FETCH_ASSOC);

                    if (!$currentTicket) {
                        throw new RuntimeException(
                            'Your counter is marked busy, but no active ticket was found. Please refresh the page or contact the administrator.'
                        );
                    }

                    // Automatically complete the previous ticket.
                    $completeCurrent = $pdo->prepare(
                        "UPDATE queues
                         SET
                            queue_remarks_id = :completed_status,
                            completed_at = NOW()
                         WHERE queue_id = :queue_id
                           AND queue_remarks_id = :serving_status"
                    );

                    $completeCurrent->execute([
                        'completed_status' => $statusIds['Completed'],
                        'queue_id' => $currentTicket['queue_id'],
                        'serving_status' => $statusIds['Serving'],
                    ]);

                    if ($completeCurrent->rowCount() !== 1) {
                        throw new RuntimeException(
                            'Unable to automatically complete your current ticket.'
                        );
                    }

                    // Save automatic completion history.
                    $autoCompleteHistory = $pdo->prepare(
                        "INSERT INTO queue_status
                            (
                                queue_id,
                                previous_status_id,
                                new_status_id,
                                changed_by
                            )
                         VALUES
                            (
                                :queue_id,
                                :previous_status,
                                :new_status,
                                :changed_by
                            )"
                    );

                    $autoCompleteHistory->execute([
                        'queue_id' => $currentTicket['queue_id'],
                        'previous_status' => $statusIds['Serving'],
                        'new_status' => $statusIds['Completed'],
                        'changed_by' => $staffId,
                    ]);

                    // Log automatic completion.
                    $completedTicketNumber = staff_queue_ticket(
                        $selectedService['service_name'],
                        (int) $currentTicket['queue_number']
                    );

                    qflow_log_transaction(
                        $pdo,
                        'QUEUE_COMPLETED',
                        'queues',
                        (int) $currentTicket['queue_id'],
                        'Automatically completed ticket ' .
                        $completedTicketNumber .
                        ' before calling the next ticket at counter ID ' .
                        $counterIdForCall
                    );
                }

                $queueId = (int) $nextQueue['queue_id'];

                $newStatusId = $statusIds['Serving'];
                $oldStatusId = $statusIds['Waiting'];
                $newStatusName = 'Serving';

            } else {

                /*
                |--------------------------------------------------------------------------
                | COMPLETE, NO SHOW, OR CANCEL TICKET
                |--------------------------------------------------------------------------
                */

                if (
                    !$queueId ||
                    !in_array(
                        $action,
                        ['complete', 'no_show', 'cancel'],
                        true
                    )
                ) {
                    throw new RuntimeException('Invalid queue action.');
                }

                // Only today's tickets can be manually updated.
                $queueLock = $pdo->prepare(
                    "SELECT
                        queue_id,
                        queue_number,
                        queue_remarks_id,
                        counter_id,
                        served_by,
                        queue_date
                     FROM queues
                     WHERE queue_id = :queue_id
                       AND service_id = :service_id
                       AND queue_date = CURDATE()
                     FOR UPDATE"
                );

                $queueLock->execute([
                    'queue_id' => $queueId,
                    'service_id' => $serviceId,
                ]);

                $lockedQueue = $queueLock->fetch(PDO::FETCH_ASSOC);

                if (!$lockedQueue) {
                    throw new RuntimeException(
                        'The selected ticket was not found, is not from today, or does not belong to this service.'
                    );
                }

                $oldStatusId = (int) $lockedQueue['queue_remarks_id'];

                $counterIdForCall = $lockedQueue['counter_id'] !== null
                    ? (int) $lockedQueue['counter_id']
                    : null;

                // Only the staff member who called a ticket can complete
                // or mark it as No Show.
                if (in_array($action, ['complete', 'no_show'], true)) {

                    if (
                        (int) ($lockedQueue['served_by'] ?? 0) !== $staffId
                    ) {
                        throw new RuntimeException(
                            'You can only update a ticket that you called from your own counter.'
                        );
                    }
                }

                // Staff can only cancel an uncalled ticket or a ticket
                // assigned to their own counter.
                if (
                    $action === 'cancel' &&
                    $counterIdForCall !== null
                ) {
                    $counterOwner = $pdo->prepare(
                        "SELECT counter_id
                         FROM counter_readiness
                         WHERE counter_id = :counter_id
                           AND staff_user_id = :staff_id
                         LIMIT 1"
                    );

                    $counterOwner->execute([
                        'counter_id' => $counterIdForCall,
                        'staff_id' => $staffId,
                    ]);

                    if (!$counterOwner->fetchColumn()) {
                        throw new RuntimeException(
                            'You cannot cancel a ticket assigned to another staff member.'
                        );
                    }
                }

                $actionMap = [
                    'complete' => ['Serving', 'Completed'],
                    'no_show' => ['Serving', 'No Show'],
                    'cancel' => ['Waiting', 'Cancelled'],
                ];

                [$requiredStatus, $newStatusName] = $actionMap[$action];

                if ($oldStatusId !== $statusIds[$requiredStatus]) {
                    throw new RuntimeException(
                        'This action is not allowed for the ticket’s current status.'
                    );
                }

                $newStatusId = $statusIds[$newStatusName];
            }

            /*
            |--------------------------------------------------------------------------
            | UPDATE QUEUE TICKET
            |--------------------------------------------------------------------------
            */

            if ($newStatusName === 'Serving') {

                $updateQueue = $pdo->prepare(
                    "UPDATE queues
                     SET
                        queue_remarks_id = :new_status,
                        counter_id = :counter_id,
                        served_by = :staff_id,
                        called_at = NOW()
                     WHERE queue_id = :queue_id"
                );

                $updateQueue->execute([
                    'new_status' => $newStatusId,
                    'counter_id' => $counterIdForCall,
                    'staff_id' => $staffId,
                    'queue_id' => $queueId,
                ]);

            } elseif ($newStatusName === 'Completed') {

                $updateQueue = $pdo->prepare(
                    "UPDATE queues
                     SET
                        queue_remarks_id = :new_status,
                        completed_at = NOW()
                     WHERE queue_id = :queue_id"
                );

                $updateQueue->execute([
                    'new_status' => $newStatusId,
                    'queue_id' => $queueId,
                ]);

            } else {

                $updateQueue = $pdo->prepare(
                    "UPDATE queues
                     SET queue_remarks_id = :new_status
                     WHERE queue_id = :queue_id"
                );

                $updateQueue->execute([
                    'new_status' => $newStatusId,
                    'queue_id' => $queueId,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | UPDATE COUNTER READINESS
            |--------------------------------------------------------------------------
            */

            if (
                $newStatusName === 'Serving' &&
                !empty($counterIdForCall)
            ) {

                $counterBusy = $pdo->prepare(
                    "UPDATE counter_readiness
                     SET availability_status = 'busy'
                     WHERE counter_id = :counter_id
                       AND staff_user_id = :staff_id"
                );

                $counterBusy->execute([
                    'counter_id' => $counterIdForCall,
                    'staff_id' => $staffId,
                ]);

            } elseif (
                in_array(
                    $newStatusName,
                    ['Completed', 'Skipped', 'No Show'],
                    true
                ) &&
                !empty($counterIdForCall)
            ) {

                $counterReady = $pdo->prepare(
                    "UPDATE counter_readiness
                     SET availability_status = 'ready'
                     WHERE counter_id = :counter_id
                       AND staff_user_id = :staff_id"
                );

                $counterReady->execute([
                    'counter_id' => $counterIdForCall,
                    'staff_id' => $staffId,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | QUEUE STATUS HISTORY
            |--------------------------------------------------------------------------
            */

            $historyStatement = $pdo->prepare(
                "INSERT INTO queue_status
                    (
                        queue_id,
                        previous_status_id,
                        new_status_id,
                        changed_by
                    )
                 VALUES
                    (
                        :queue_id,
                        :previous_status,
                        :new_status,
                        :changed_by
                    )"
            );

            $historyStatement->execute([
                'queue_id' => $queueId,
                'previous_status' => $oldStatusId,
                'new_status' => $newStatusId,
                'changed_by' => $staffId,
            ]);

            /*
            |--------------------------------------------------------------------------
            | TRANSACTION LOG
            |--------------------------------------------------------------------------
            */

            $logAction = match ($action) {
                'call_next' => 'QUEUE_CALLED',
                'complete' => 'QUEUE_COMPLETED',
                'no_show' => 'QUEUE_NO_SHOW',
                'cancel' => 'QUEUE_CANCELLED',
                default => 'QUEUE_UPDATED',
            };

            $loggedQueueNumber = isset($nextQueue['queue_number'])
                ? (int) $nextQueue['queue_number']
                : (int) ($lockedQueue['queue_number'] ?? 0);

            $ticketNumber = staff_queue_ticket(
                $selectedService['service_name'],
                $loggedQueueNumber
            );

            $logDescription = match ($action) {
                'call_next' =>
                    'Called ticket ' . $ticketNumber .
                    ' for ' . $selectedService['service_name'] .
                    ' at counter ID ' . $counterIdForCall,

                'complete' =>
                    'Marked ticket ' . $ticketNumber .
                    ' as Completed for ' . $selectedService['service_name'],

                'no_show' =>
                    'Marked ticket ' . $ticketNumber .
                    ' as No Show for ' . $selectedService['service_name'],

                'cancel' =>
                    'Cancelled ticket ' . $ticketNumber .
                    ' for ' . $selectedService['service_name'],

                default =>
                    'Updated ticket ' . $ticketNumber .
                    ' to ' . $newStatusName,
            };

            qflow_log_transaction(
                $pdo,
                $logAction,
                'queues',
                $queueId,
                $logDescription
            );

            /*
            |--------------------------------------------------------------------------
            | GET NOTIFICATION RECIPIENT
            |--------------------------------------------------------------------------
            */

            $notificationTarget = $pdo->prepare(
                "SELECT
                    u.account_id,
                    u.email,
                    u.first_name,
                    u.last_name,
                    q.queue_number,
                    q.ticket_type,
                    s.service_name,
                    sc.counter_name
                 FROM queues q
                 INNER JOIN services s
                    ON s.service_id = q.service_id
                 LEFT JOIN users u
                    ON u.account_id = q.account_id
                 LEFT JOIN service_counters sc
                    ON sc.counter_id = q.counter_id
                 WHERE q.queue_id = :queue_id
                 LIMIT 1"
            );

            $notificationTarget->execute([
                'queue_id' => $queueId,
            ]);

            $recipient = $notificationTarget->fetch(PDO::FETCH_ASSOC);

            /*
            |--------------------------------------------------------------------------
            | IN-APP AND PUSH NOTIFICATIONS
            |--------------------------------------------------------------------------
            */

            if (
                $recipient &&
                $recipient['ticket_type'] === 'student' &&
                !empty($recipient['account_id'])
            ) {

                $ticketNumber = staff_queue_ticket(
                    $recipient['service_name'],
                    (int) $recipient['queue_number']
                );

                $notificationDetails = match ($newStatusName) {

                    'Serving' => [
                        'queue_called',
                        'Your turn is now!',
                        'Ticket ' . $ticketNumber .
                        ' is now being served. Please proceed to ' .
                        (
                            $recipient['counter_name']
                            ?: $recipient['service_name'] . ' counter'
                        ) . '.',
                    ],

                    'Completed' => [
                        'queue_completed',
                        'Transaction completed',
                        'Your ticket ' . $ticketNumber .
                        ' has been marked as completed.',
                    ],

                    'Skipped' => [
                        'queue_skipped',
                        'Your ticket was skipped',
                        'Ticket ' . $ticketNumber .
                        ' was skipped. Please contact the finance staff for assistance.',
                    ],

                    'No Show' => [
                        'queue_no_show',
                        'Ticket marked No Show',
                        'Ticket ' . $ticketNumber .
                        ' was marked as No Show. Please contact the finance staff for assistance.',
                    ],

                    'Cancelled' => [
                        'queue_cancelled',
                        'Ticket cancelled',
                        'Ticket ' . $ticketNumber .
                        ' has been marked as cancelled.',
                    ],

                    default => null,
                };

                if ($notificationDetails !== null) {

                    [$type, $title, $message] = $notificationDetails;

                    $notificationInsert = $pdo->prepare(
                        "INSERT INTO notifications
                            (
                                account_id,
                                queue_id,
                                notification_type,
                                title,
                                message
                            )
                         VALUES
                            (
                                :account_id,
                                :queue_id,
                                :type,
                                :title,
                                :message
                            )"
                    );

                    $notificationInsert->execute([
                        'account_id' => $recipient['account_id'],
                        'queue_id' => $queueId,
                        'type' => $type,
                        'title' => $title,
                        'message' => $message,
                    ]);

                    $pushNotification = [
                        'account_id' => (string) $recipient['account_id'],
                        'title' => $title,
                        'message' => $message,
                        'queue_id' => (int) $queueId,
                    ];

                    // Prepare email notification.
                    if (
                        !empty($recipient['email']) &&
                        in_array(
                            $newStatusName,
                            ['Serving', 'Completed', 'Cancelled'],
                            true
                        )
                    ) {

                        $emailNotification = [
                            'email' => $recipient['email'],
                            'name' => trim(
                                ($recipient['first_name'] ?? '') . ' ' .
                                ($recipient['last_name'] ?? '')
                            ),
                            'ticket' => $ticketNumber,
                            'service' => $recipient['service_name'],
                            'counter' => $recipient['counter_name'] ?? '',
                            'status' => $newStatusName,
                        ];
                    }
                }
            }

            /*
            |--------------------------------------------------------------------------
            | COMMIT DATABASE CHANGES
            |--------------------------------------------------------------------------
            */

            $pdo->commit();

            /*
            |--------------------------------------------------------------------------
            | SEND EMAIL AFTER COMMIT
            |--------------------------------------------------------------------------
            */

            if ($emailNotification !== null) {

                try {

                    if ($emailNotification['status'] === 'Cancelled') {

                        $emailHtml = staff_queue_cancelled_email_template([
                            'name' => $emailNotification['name'],
                            'ticket' => $emailNotification['ticket'],
                            'service' => $emailNotification['service'],
                        ]);

                        $emailSubject =
                            'QueueFlow: Your Queue Ticket Has Been Cancelled';

                    } else {

                        $emailType = match ($emailNotification['status']) {
                            'Serving' => 'serving',
                            'Completed' => 'completed',
                            default => null,
                        };

                        if ($emailType === null) {
                            throw new RuntimeException(
                                'Unsupported email notification status.'
                            );
                        }

                        $emailHtml = queueFlowEmailTemplate(
                            $emailType,
                            [
                                'name' => $emailNotification['name'],
                                'ticket' => $emailNotification['ticket'],
                                'service' => $emailNotification['service'],
                                'counter' => $emailNotification['counter'],
                            ]
                        );

                        $emailSubject = $emailType === 'serving'
                            ? 'QueueFlow: It is your turn!'
                            : 'QueueFlow: Transaction completed';
                    }

                    $emailSent = sendEmail(
                        $emailNotification['email'],
                        $emailSubject,
                        $emailHtml
                    );

                    if (!$emailSent) {
                        error_log(
                            'QueueFlow: Failed to send ' .
                            $emailNotification['status'] .
                            ' email for queue ticket ' .
                            $emailNotification['ticket']
                        );
                    }

                } catch (Throwable $emailError) {

                    error_log(
                        'QueueFlow email error: ' .
                        $emailError->getMessage()
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | SEND PUSH NOTIFICATION
            |--------------------------------------------------------------------------
            */

            if ($pushNotification !== null) {

                try {

                    $pushResult = qflow_send_push_notification(
                        $pdo,
                        $pushNotification['account_id'],
                        $pushNotification['title'],
                        $pushNotification['message'],
                        $pushNotification['queue_id']
                    );

                    error_log(
                        'QueueFlow push result: ' .
                        json_encode($pushResult)
                    );

                } catch (Throwable $pushError) {

                    error_log(
                        'QueueFlow push error: ' .
                        $pushError->getMessage()
                    );
                }
            }

            header(
                'Location: queue-handling.php?service_id=' .
                $serviceId .
                '&updated=1'
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
                    'Staff queue action error: ' .
                    $exception->getMessage()
                );

                $error = 'Unable to update the queue. Please try again.';
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| SUCCESS MESSAGE
|--------------------------------------------------------------------------
*/

if (isset($_GET['updated'])) {
    $success = 'Queue status updated successfully.';
}

/*
|--------------------------------------------------------------------------
| LOAD TODAY'S QUEUE
|--------------------------------------------------------------------------
*/

$queueRows = [];
$currentlyServing = null;

$stats = [
    'waiting' => 0,
    'serving' => 0,
    'completed' => 0,
];

if ($selectedService) {

    $queueList = $pdo->prepare(
    "SELECT
        q.queue_id,
        q.queue_number,
        q.queue_date,
        q.counter_id,
        sc.counter_name,
        q.created_at,
        q.called_at,
        q.completed_at,
        q.ticket_type,
        q.guest_name,
        q.priority_requested,
        q.priority_status,
        u.first_name,
        u.last_name,
        qr.queue_name
     FROM queues q
     INNER JOIN queue_remarks qr
        ON qr.queue_remarks_id = q.queue_remarks_id
     LEFT JOIN service_counters sc
        ON sc.counter_id = q.counter_id
       AND sc.service_id = q.service_id
       AND sc.is_active = 1
     LEFT JOIN counter_readiness cr
        ON cr.counter_id = sc.counter_id
       AND cr.staff_user_id = :staff_id
     LEFT JOIN users u
        ON u.account_id = q.account_id
     WHERE q.service_id = :service_id
       AND q.queue_date = CURDATE()

       -- Hide tickets with pending priority requests.
       AND (
            COALESCE(q.priority_requested, 0) = 0
            OR q.priority_status IN ('approved', 'rejected')
       )

       -- Hide tickets cancelled by students.
       -- Keep tickets cancelled by staff.
       AND (
            qr.queue_name <> 'Cancelled'
            OR EXISTS (
                SELECT 1
                FROM queue_status qs
                INNER JOIN users cancel_user
                    ON cancel_user.user_id = qs.changed_by
                WHERE qs.queue_id = q.queue_id
                  AND qs.new_status_id = q.queue_remarks_id
                  AND cancel_user.role_id IN (2, 3)
            )
       )

       AND (
            q.counter_id IS NULL
            OR cr.staff_user_id = :staff_id_check
       )

     ORDER BY
        CASE
            WHEN q.priority_requested = 1
             AND q.priority_status = 'approved'
            THEN 0
            ELSE 1
        END DESC,
        q.queue_number DESC,
        q.created_at DESC"
);

$queueList->execute([
    'staff_id' => $staffId,
    'service_id' => $serviceId,
    'staff_id_check' => $staffId,
]);

$queueRows = $queueList->fetchAll(PDO::FETCH_ASSOC);

    foreach ($queueRows as $row) {

        if ($row['queue_name'] === 'Waiting') {

            $stats['waiting']++;

        } elseif ($row['queue_name'] === 'Serving') {

            $stats['serving']++;

            if ($currentlyServing === null) {
                $currentlyServing = $row;
            }

        } elseif ($row['queue_name'] === 'Completed') {

            $stats['completed']++;
        }
    }
}

/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

$pageTitle = 'Staff Queue Management';

require dirname(__DIR__, 2) . '/includes/header.php';
require dirname(__DIR__, 2) . '/includes/sidebar.php';

?>

<main class="main-content">

    <header class="page-header">
        <div>
            <p class="eyebrow">STAFF PORTAL</p>
            <h1>Queue Management</h1>
            <p class="page-description">
                Manage waiting and active tickets for your assigned finance service.
            </p>
        </div>
    </header>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error" role="alert">
            <?= staff_queue_escape($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success" role="status">
            <?= staff_queue_escape($success) ?>
        </div>
    <?php endif; ?>

    <?php if (!$assignedServices): ?>

        <div class="empty-state">
            <h3>No assigned services</h3>
            <p>
                Your administrator must assign you to Cashier or Receivables
                before you can manage queues.
            </p>
        </div>

    <?php else: ?>

        <?php if (!$selectedService): ?>

            <section class="section-block">

                <div class="section-heading">
                    <div>
                        <h2>Select a Service</h2>
                        <p>
                            Only services assigned to your staff account are shown.
                        </p>
                    </div>
                </div>

                <div class="service-grid">

                    <?php foreach ($assignedServices as $assignedService): ?>

                        <article class="service-card">

                            <div class="service-card-icon">
                                <?= staff_queue_escape(
                                    strtoupper(
                                        substr(
                                            $assignedService['service_name'],
                                            0,
                                            1
                                        )
                                    )
                                ) ?>
                            </div>

                            <h3>
                                <?= staff_queue_escape(
                                    $assignedService['service_name']
                                ) ?>
                            </h3>

                            <p>
                                <?= staff_queue_escape(
                                    $assignedService['description']
                                    ?: 'Manage queue tickets.'
                                ) ?>
                            </p>

                            <a
                                class="button button-primary"
                                href="queue-handling.php?service_id=<?= (int) $assignedService['service_id'] ?>"
                            >
                                Open Queue
                            </a>

                        </article>

                    <?php endforeach; ?>

                </div>

            </section>

        <?php else: ?>

            <section class="section-block">

                <div class="section-heading">

                    <div>
                        <h2>
                            <?= staff_queue_escape(
                                $selectedService['service_name']
                            ) ?>
                        </h2>

                        <p>
                            <?= date('F d, Y') ?> · Today's queue tickets only
                        </p>
                    </div>

                    <?php if (count($assignedServices) > 1): ?>
                        <a href="queue-handling.php" class="text-link">
                            Change Service
                        </a>
                    <?php endif; ?>

                </div>

                <!-- QUEUE STATISTICS -->

                <div class="stats-grid">

                    <article class="stat-card">
                        <span class="stat-label">Waiting</span>
                        <strong><?= $stats['waiting'] ?></strong>
                        <small>Waiting tickets</small>
                    </article>

                    <article class="stat-card">
                        <span class="stat-label">Serving</span>
                        <strong><?= $stats['serving'] ?></strong>
                        <small>Tickets being served</small>
                    </article>

                    <article class="stat-card">
                        <span class="stat-label">Completed</span>
                        <strong><?= $stats['completed'] ?></strong>
                        <small>Completed today</small>
                    </article>

                    <article class="stat-card">
                        <span class="stat-label">Total Tickets</span>
                        <strong><?= count($queueRows) ?></strong>
                        <small>Today's visible tickets</small>
                    </article>

                </div>

                <!-- CALL NEXT -->

                <div class="queue-action-panel">

                    <div>
                        <h3>Call Next Ticket</h3>

                        <p>
                            QueueFlow will automatically complete your current
                            ticket when another waiting ticket is available,
                            then call the next eligible ticket. If no more
                            tickets are waiting, complete your last ticket manually.
                        </p>
                    </div>

                    <form
                        method="POST"
                        action="queue-handling.php?service_id=<?= $serviceId ?>"
                    >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= staff_queue_escape(qflow_csrf_token()) ?>"
                        >

                        <input
                            type="hidden"
                            name="service_id"
                            value="<?= $serviceId ?>"
                        >

                        <input
                            type="hidden"
                            name="action"
                            value="call_next"
                        >

                        <button
                            type="submit"
                            class="button button-primary"
                        >
                            Call Next
                        </button>

                    </form>

                </div>

                <!-- QUEUE TABLE -->

                <div class="section-heading">
                    <div>
                        <h2>Queue Tickets</h2>

                        <p>
                            Only today's tickets are displayed. Cancelled
                            tickets and pending priority requests are excluded.
                            Rejected priority requests appear as regular tickets.
                        </p>
                    </div>
                </div>

                <div class="table-container">

                    <table class="data-table">

                        <thead>
                            <tr>
                                <th>Ticket</th>
                                <th>Student / Guest</th>
                                <th>Counter</th>
                                <th>Queue Date</th>
                                <th>Created</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php if (!$queueRows): ?>

                            <tr>
                                <td colspan="7">
                                    No eligible tickets are available for your counter today.
                                </td>
                            </tr>

                        <?php else: ?>

                            <?php foreach ($queueRows as $row): ?>

                                <?php
                                $ticketNumber = staff_queue_ticket(
                                    $selectedService['service_name'],
                                    (int) $row['queue_number']
                                );

                                $isApprovedPriority =
                                    (int) $row['priority_requested'] === 1
                                    && $row['priority_status'] === 'approved';

                                $isRejectedPriority =
                                    (int) $row['priority_requested'] === 1
                                    && $row['priority_status'] === 'rejected';
                                ?>

                                <tr>

                                    <td>

                                        <strong>
                                            <?= staff_queue_escape($ticketNumber) ?>
                                        </strong>

                                        <?php if ($isApprovedPriority): ?>

                                            <br>
                                            <small>Priority</small>

                                        <?php elseif ($isRejectedPriority): ?>

                                            <br>
                                            <small>Regular Queue</small>

                                        <?php endif; ?>

                                    </td>

                                    <td>

                                        <?php if ($row['ticket_type'] === 'guest'): ?>

                                            <?= staff_queue_escape(
                                                $row['guest_name'] ?: 'Guest'
                                            ) ?>

                                        <?php else: ?>

                                            <?= staff_queue_escape(
                                                trim(
                                                    ($row['first_name'] ?? '') .
                                                    ' ' .
                                                    ($row['last_name'] ?? '')
                                                ) ?: 'Student'
                                            ) ?>

                                        <?php endif; ?>

                                    </td>

                                    <td>
                                        <?= staff_queue_escape(
                                            $row['counter_name'] ?: 'Not assigned'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= staff_queue_escape(
                                            date(
                                                'M d, Y',
                                                strtotime($row['queue_date'])
                                            )
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= staff_queue_escape(
                                            date(
                                                'h:i A',
                                                strtotime($row['created_at'])
                                            )
                                        ) ?>
                                    </td>

                                    <td>
                                        <span class="status-pill <?= staff_queue_escape(
                                            staff_queue_status_class(
                                                $row['queue_name']
                                            )
                                        ) ?>">
                                            <?= staff_queue_escape(
                                                $row['queue_name']
                                            ) ?>
                                        </span>
                                    </td>

                                    <td>

                                        <?php

                                        $actions = match ($row['queue_name']) {

                                            'Serving' => [
                                                'complete' => 'Complete',
                                                'no_show' => 'No Show',
                                            ],

                                            'Waiting' => [
                                                'cancel' => 'Cancel',
                                            ],

                                            default => [],
                                        };

                                        ?>

                                        <?php if ($actions): ?>

                                            <div class="queue-row-actions">

                                                <?php foreach (
                                                    $actions as $action => $label
                                                ): ?>

                                                    <form
                                                        method="POST"
                                                        action="queue-handling.php?service_id=<?= $serviceId ?>"
                                                    >

                                                        <input
                                                            type="hidden"
                                                            name="csrf_token"
                                                            value="<?= staff_queue_escape(
                                                                qflow_csrf_token()
                                                            ) ?>"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="service_id"
                                                            value="<?= $serviceId ?>"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="queue_id"
                                                            value="<?= (int) $row['queue_id'] ?>"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="action"
                                                            value="<?= staff_queue_escape($action) ?>"
                                                        >

                                                        <button
                                                            type="submit"
                                                            class="button button-small"
                                                            onclick="return confirm('Confirm: <?= staff_queue_escape($label) ?> this ticket?')"
                                                        >
                                                            <?= staff_queue_escape($label) ?>
                                                        </button>

                                                    </form>

                                                <?php endforeach; ?>

                                            </div>

                                        <?php else: ?>

                                            <span class="muted-label">
                                                No actions
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </section>

        <?php endif; ?>

    <?php endif; ?>

    <footer class="page-footer">
        <span>
            QueueFlow — RMMCMI Finance Queue Management System
        </span>

        <span><?= date('Y') ?></span>
    </footer>

</main>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
