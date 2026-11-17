
<?php

require_once '../../includes/functions.php';

$pdo = require '../../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

try {

    /*
    |--------------------------------------------------------------------------
    | GET ACTIVE FINANCE SERVICES
    |--------------------------------------------------------------------------
    */

    $servicesStmt = $pdo->query("
        SELECT
            service_id,
            service_name
        FROM services
        WHERE is_active = 1
        ORDER BY service_id ASC
    ");

    $services = $servicesStmt->fetchAll(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | GET ALL ACTIVE COUNTERS AND THEIR READINESS
    |--------------------------------------------------------------------------
    |
    | ready       = Available
    | busy        = Serving / Busy
    | unavailable = Unavailable
    |
    */

    $countersStmt = $pdo->query("
        SELECT
            sc.counter_id,
            sc.service_id,
            sc.counter_name,
            cr.availability_status

        FROM service_counters sc

        LEFT JOIN counter_readiness cr
            ON cr.counter_id = sc.counter_id

        INNER JOIN services s
            ON s.service_id = sc.service_id

        WHERE sc.is_active = 1
          AND s.is_active = 1

        ORDER BY
            sc.service_id ASC,
            sc.counter_id ASC
    ");

    $counters = $countersStmt->fetchAll(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | GET TODAY'S LIVE TICKETS
    |--------------------------------------------------------------------------
    |
    | Regular tickets are visible.
    | Approved priority tickets are visible.
    | Rejected priority tickets are treated as regular tickets.
    | Pending priority requests remain hidden.
    |
    */

    $ticketsStmt = $pdo->prepare("
        SELECT
            q.queue_id,
            q.queue_number,
            q.service_id,
            q.counter_id,
            q.created_at,
            q.called_at,

            COALESCE(q.priority_requested, 0) AS priority_requested,
            q.priority_category,
            COALESCE(q.priority_status, 'not_requested') AS priority_status,

            s.service_name,
            qr.queue_name AS status,
            sc.counter_name

        FROM queues q

        INNER JOIN services s
            ON s.service_id = q.service_id

        INNER JOIN queue_remarks qr
            ON qr.queue_remarks_id = q.queue_remarks_id

        LEFT JOIN service_counters sc
            ON sc.counter_id = q.counter_id

        WHERE q.queue_date = CURDATE()

          AND qr.queue_name IN ('Waiting', 'Serving')

          AND (
                COALESCE(q.priority_requested, 0) = 0
                OR q.priority_status IN ('approved', 'rejected')
          )

        ORDER BY
            q.service_id ASC,

            CASE
                WHEN qr.queue_name = 'Serving' THEN 0
                ELSE 1
            END ASC,

            CASE
                WHEN qr.queue_name = 'Waiting'
                 AND q.priority_requested = 1
                 AND q.priority_status = 'approved'
                THEN 0
                ELSE 1
            END ASC,

            q.queue_number ASC,
            q.created_at ASC
    ");

    $ticketsStmt->execute();

    $tickets = $ticketsStmt->fetchAll(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | PREPARE SERVICE GROUPS
    |--------------------------------------------------------------------------
    */

    $result = [];

    foreach ($services as $service) {

        $serviceId = (int) $service['service_id'];
        $serviceName = $service['service_name'];

        $prefix = stripos($serviceName, 'cashier') !== false
            ? 'C'
            : (
                stripos($serviceName, 'receivables') !== false
                    ? 'R'
                    : strtoupper(substr($serviceName, 0, 1))
            );

        $group = [
            'service_id' => $serviceId,
            'service_name' => $serviceName,
            'counters' => [],
            'serving' => [],
            'waiting' => [],
            'waiting_count' => 0,
            'serving_count' => 0,
        ];

        /*
        |--------------------------------------------------------------------------
        | ADD COUNTERS TO SERVICE
        |--------------------------------------------------------------------------
        */

        foreach ($counters as $counter) {

            if ((int) $counter['service_id'] !== $serviceId) {
                continue;
            }

            $readiness = strtolower(
                trim((string) ($counter['availability_status'] ?? ''))
            );

            $statusMap = [
                'ready' => 'Available',
                'busy' => 'Busy',
                'unavailable' => 'Unavailable',
            ];

            $group['counters'][] = [
                'counter_id' => (int) $counter['counter_id'],

                'counter_name' => $counter['counter_name'],

                'availability_status' => $readiness,

                'status' => $statusMap[$readiness] ?? 'Not configured',

                'serving' => [],
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | ADD TICKETS TO SERVICE
        |--------------------------------------------------------------------------
        */

        foreach ($tickets as $ticket) {

            if ((int) $ticket['service_id'] !== $serviceId) {
                continue;
            }

            $ticketNumber = $prefix . '-' . str_pad(
                (string) $ticket['queue_number'],
                3,
                '0',
                STR_PAD_LEFT
            );

            $isPriority = (
                (int) $ticket['priority_requested'] === 1
                && $ticket['priority_status'] === 'approved'
            );

            $item = [
                'queue_id' => (int) $ticket['queue_id'],

                'ticket_number' => $ticketNumber,

                'counter_id' => $ticket['counter_id'] !== null
                    ? (int) $ticket['counter_id']
                    : null,

                'counter_name' => $ticket['counter_name']
                    ?: 'Counter not assigned',

                'created_at' => $ticket['created_at'],

                'called_at' => $ticket['called_at'],

                'status' => $ticket['status'],

                'is_priority' => $isPriority,

                'priority_category' => $isPriority
                    ? $ticket['priority_category']
                    : null,
            ];

            if ($ticket['status'] === 'Serving') {

                $group['serving'][] = $item;

                // Attach the serving ticket to its actual counter.
                foreach ($group['counters'] as &$counterItem) {

                    if (
                        $counterItem['counter_id'] === $item['counter_id']
                    ) {
                        $counterItem['serving'][] = $item;
                        break;
                    }
                }

                unset($counterItem);

            } else {

                $group['waiting'][] = $item;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | DETERMINE COUNTER DISPLAY STATUS
        |--------------------------------------------------------------------------
        */

        foreach ($group['counters'] as &$counterItem) {

            if (!empty($counterItem['serving'])) {

                $counterItem['status'] = 'Serving';

            } elseif (
                $counterItem['availability_status'] === 'ready'
            ) {

                $counterItem['status'] = 'Available';

            } elseif (
                $counterItem['availability_status'] === 'busy'
            ) {

                $counterItem['status'] = 'Busy';

            } elseif (
                $counterItem['availability_status'] === 'unavailable'
            ) {

                $counterItem['status'] = 'Unavailable';

            } else {

                $counterItem['status'] = 'Not configured';
            }
        }

        unset($counterItem);

        $group['waiting_count'] = count($group['waiting']);
        $group['serving_count'] = count($group['serving']);

        $result[] = $group;
    }

    /*
    |--------------------------------------------------------------------------
    | JSON RESPONSE
    |--------------------------------------------------------------------------
    */

    echo json_encode([
        'success' => true,
        'updated_at' => date('c'),
        'services' => $result,
    ], JSON_THROW_ON_ERROR);

} catch (Throwable $e) {

    error_log('Queue monitor error: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to retrieve live queue information.',
    ]);
}
