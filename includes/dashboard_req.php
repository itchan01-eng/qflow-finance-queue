
<?php

date_default_timezone_set('Asia/Manila');

/*
|--------------------------------------------------------------------------
| Load the current user
|--------------------------------------------------------------------------
*/

$userStatement = $pdo->prepare(
    'SELECT
        u.user_id,
        u.account_id,
        u.first_name,
        u.last_name,
        u.email,
        u.account_status,
        r.role_name
     FROM users u
     INNER JOIN roles r
        ON r.role_id = u.role_id
     WHERE u.user_id = :user_id
     LIMIT 1'
);

$userStatement->execute([
    'user_id' => $userId
]);

$user = $userStatement->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['account_status'] !== 'active') {
    $_SESSION = [];
    session_destroy();

    header('Location: login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Current user information
|--------------------------------------------------------------------------
*/

$accountId = $user['account_id'];
$firstName = $user['first_name'];
$lastName = $user['last_name'];
$roleName = $user['role_name'];

/*
|--------------------------------------------------------------------------
| Dashboard statistics
|--------------------------------------------------------------------------
*/

$stats = [
    'waiting' => 0,
    'serving' => 0,
    'completed' => 0,
    'services' => 0,
];

$activeTicket = null;
$services = [];

/*
|--------------------------------------------------------------------------
| Student dashboard
|--------------------------------------------------------------------------
*/

if ($roleName === 'Student') {

    // Get active finance services.
    $serviceStatement = $pdo->query(
        'SELECT
            service_id,
            service_name,
            description
         FROM services
         WHERE is_active = 1
         ORDER BY service_id ASC'
    );

    $services = $serviceStatement->fetchAll(PDO::FETCH_ASSOC);

    $stats['services'] = count($services);

    /*
    |--------------------------------------------------------------------------
    | Find student's active ticket using account_id
    |--------------------------------------------------------------------------
    */

    $ticketStatement = $pdo->prepare(
        "SELECT
            q.queue_id,
            q.queue_number,
            q.queue_date,
            q.created_at,
            s.service_name,
            qr.queue_name
         FROM queues q
         INNER JOIN services s
            ON s.service_id = q.service_id
         INNER JOIN queue_remarks qr
            ON qr.queue_remarks_id = q.queue_remarks_id
         WHERE q.account_id = :account_id
           AND q.queue_date = CURDATE()
           AND qr.queue_name IN ('Waiting', 'Serving')
           AND (
                COALESCE(q.priority_requested, 0) = 0
                OR q.priority_status IN ('approved', 'rejected')
           )
         ORDER BY q.created_at DESC
         LIMIT 1"
    );

    $ticketStatement->execute([
        'account_id' => $accountId
    ]);

    $activeTicket = $ticketStatement->fetch(PDO::FETCH_ASSOC) ?: null;

    // Update student queue statistics.
    if ($activeTicket) {
        $stats['waiting'] = (
            $activeTicket['queue_name'] === 'Waiting'
        ) ? 1 : 0;

        $stats['serving'] = (
            $activeTicket['queue_name'] === 'Serving'
        ) ? 1 : 0;
    }

/*
|--------------------------------------------------------------------------
| Staff and administrator dashboard
|--------------------------------------------------------------------------
*/

} else {

    // Get today's overall queue totals.
    // Follow the same ticket visibility rules as the Live Queue.
    $statStatement = $pdo->query(
        "SELECT
            COALESCE(SUM(
                CASE
                    WHEN qr.queue_name = 'Waiting' THEN 1
                    ELSE 0
                END
            ), 0) AS waiting,

            COALESCE(SUM(
                CASE
                    WHEN qr.queue_name = 'Serving' THEN 1
                    ELSE 0
                END
            ), 0) AS serving,

            COALESCE(SUM(
                CASE
                    WHEN qr.queue_name = 'Completed' THEN 1
                    ELSE 0
                END
            ), 0) AS completed

         FROM queues q

         INNER JOIN queue_remarks qr
            ON qr.queue_remarks_id = q.queue_remarks_id

         WHERE q.queue_date = CURDATE()

           AND (
                COALESCE(q.priority_requested, 0) = 0
                OR q.priority_status IN ('approved', 'rejected')
           )"
    );

    $queueStats = $statStatement->fetch(PDO::FETCH_ASSOC) ?: [];

    $stats['waiting'] = (int) ($queueStats['waiting'] ?? 0);
    $stats['serving'] = (int) ($queueStats['serving'] ?? 0);
    $stats['completed'] = (int) ($queueStats['completed'] ?? 0);

    // Get active finance services.
    $serviceStatement = $pdo->query(
        'SELECT
            service_id,
            service_name,
            description
         FROM services
         WHERE is_active = 1
         ORDER BY service_id ASC'
    );

    $services = $serviceStatement->fetchAll(PDO::FETCH_ASSOC);

    $stats['services'] = count($services);
}

/*
|--------------------------------------------------------------------------
| Staff service assignments
|--------------------------------------------------------------------------
*/

$staffAssignments = [];

$normalizedRole = strtolower(trim((string) $roleName));

if ($normalizedRole === 'staff') {

    $assignmentStatement = $pdo->prepare(
        "SELECT
            s.service_name,
            sc.counter_name
         FROM staff_service_assignments a
         INNER JOIN services s
            ON s.service_id = a.service_id
         LEFT JOIN service_counters sc
            ON sc.service_id = s.service_id
            AND sc.is_active = 1
         WHERE a.user_id = :user_id
           AND s.is_active = 1
         ORDER BY
            s.service_name ASC,
            sc.counter_name ASC"
    );

    $assignmentStatement->execute([
        'user_id' => $user['user_id']
    ]);

    $staffAssignments = $assignmentStatement->fetchAll(PDO::FETCH_ASSOC);
}
