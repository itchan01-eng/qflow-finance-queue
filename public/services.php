<?php

require_once dirname(__DIR__) . '/includes/functions.php';

qflow_start_session();
qflow_require_role(['Student']);

$pdo = require dirname(__DIR__) . '/config/database.php';

$userId = (int) $_SESSION['user_id'];

// Verify that the account is active and retrieve the account ID.
$userStatement = $pdo->prepare(
    "SELECT account_id, account_status
     FROM users
     WHERE user_id = :user_id
     LIMIT 1"
);

$userStatement->execute(['user_id' => $userId]);

$user = $userStatement->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['account_status'] !== 'active') {
    $_SESSION = [];
    session_destroy();

    header('Location: login.php');
    exit;
}

// IMPORTANT: queues.account_id stores users.account_id,
// NOT users.user_id.
$accountId = (int) $user['account_id'];

// Load active finance services.
$serviceStatement = $pdo->query(
    "SELECT service_id, service_name, description
     FROM services
     WHERE is_active = 1
     ORDER BY service_id ASC"
);

$services = $serviceStatement->fetchAll(PDO::FETCH_ASSOC);

// Check whether the student already has an active ticket today.
// Use account_id instead of user_id.
$ticketStatement = $pdo->prepare(
    "SELECT q.queue_id, q.queue_number, q.account_id,
            q.queue_date, s.service_name, qr.queue_name
     FROM queues q
     INNER JOIN services s ON s.service_id = q.service_id
     INNER JOIN queue_remarks qr
        ON qr.queue_remarks_id = q.queue_remarks_id
     WHERE q.account_id = :account_id
       AND q.queue_date = CURDATE()
       AND qr.queue_name IN ('Waiting', 'Serving')
     ORDER BY q.created_at DESC
     LIMIT 1"
);

$ticketStatement->execute([
    'account_id' => $accountId
]);

$activeTicket = $ticketStatement->fetch(PDO::FETCH_ASSOC) ?: null;

/*
|--------------------------------------------------------------------------
| LIVE QUEUE STATUS
|--------------------------------------------------------------------------
| Queue status is based on today's tickets and their current remarks.
*/

// Count waiting tickets.
$waitingCountStatement = $pdo->query(
    "SELECT COUNT(*)
     FROM queues q
     INNER JOIN queue_remarks qr
        ON qr.queue_remarks_id = q.queue_remarks_id
     WHERE q.queue_date = CURDATE()
       AND qr.queue_name = 'Waiting'"
);

$waitingCount = (int) $waitingCountStatement->fetchColumn();

// Count tickets currently being served.
$servingCountStatement = $pdo->query(
    "SELECT COUNT(*)
     FROM queues q
     INNER JOIN queue_remarks qr
        ON qr.queue_remarks_id = q.queue_remarks_id
     WHERE q.queue_date = CURDATE()
       AND qr.queue_name = 'Serving'"
);

$servingCount = (int) $servingCountStatement->fetchColumn();

// Get the ticket currently being served.
$currentServingStatement = $pdo->query(
    "SELECT q.queue_number, s.service_name
     FROM queues q
     INNER JOIN services s ON s.service_id = q.service_id
     INNER JOIN queue_remarks qr
        ON qr.queue_remarks_id = q.queue_remarks_id
     WHERE q.queue_date = CURDATE()
       AND qr.queue_name = 'Serving'
     ORDER BY q.created_at ASC
     LIMIT 1"
);

$currentServing = $currentServingStatement->fetch(PDO::FETCH_ASSOC) ?: null;

// Get the next ticket waiting in the queue.
$nextWaitingStatement = $pdo->query(
    "SELECT q.queue_number, s.service_name
     FROM queues q
     INNER JOIN services s ON s.service_id = q.service_id
     INNER JOIN queue_remarks qr
        ON qr.queue_remarks_id = q.queue_remarks_id
     WHERE q.queue_date = CURDATE()
       AND qr.queue_name = 'Waiting'
     ORDER BY q.created_at ASC
     LIMIT 1"
);

$nextWaiting = $nextWaitingStatement->fetch(PDO::FETCH_ASSOC) ?: null;

$pageTitle = 'Finance Services';

require dirname(__DIR__) . '/includes/header.php';
require dirname(__DIR__) . '/includes/sidebar.php';
?>

<style>
/* ==========================================
   FINANCE SERVICES
   Original layout + service cards + live queue
========================================== */

/* Main content */
.main-content {
    padding-top: 20px;
    padding-bottom: 24px;
}

/* Page heading */
.page-header {
    margin-bottom: 20px;
}

.page-header h1 {
    margin-top: 0;
    margin-bottom: 5px;
}

.page-description {
    margin-top: 0;
    margin-bottom: 0;
    line-height: 1.6;
}

/* Welcome section */
.welcome-panel {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding: 20px 22px;
    margin-bottom: 26px;
}

.welcome-panel > div:first-child {
    min-width: 0;
}

.welcome-panel h2 {
    margin-top: 5px;
    margin-bottom: 7px;
    line-height: 1.4;
}

.welcome-panel p {
    max-width: 620px;
    margin-top: 0;
    margin-bottom: 0;
    line-height: 1.6;
}

.welcome-icon {
    flex-shrink: 0;
    display: grid;
    place-items: center;
}

.welcome-icon img {
    display: block;
    width: 48px;
    height: 48px;
    object-fit: contain;
}

/* Section headings */
.section-block {
    margin-bottom: 26px;
}

.section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 14px;
}

.section-heading h2 {
    margin-top: 0;
    margin-bottom: 4px;
}

.section-heading p {
    margin-top: 0;
    margin-bottom: 0;
}

/* ==========================================
   SERVICE CARDS
========================================== */

.service-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    align-items: stretch;
    gap: 18px;
}

.service-card {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    min-width: 0;
    padding: 23px;
    overflow: hidden;

    background: #ffffff;
    border: 1px solid #e7e9ee;
    border-radius: 14px;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.035);

    transition:
        transform 180ms ease,
        box-shadow 180ms ease,
        border-color 180ms ease;
}

.service-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: #e5e7eb;
    transition: background 180ms ease;
}

.service-card:hover {
    transform: translateY(-4px);
    border-color: #d5d8df;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.075);
}

.service-card:hover::before {
    background: var(--primary-color, var(--primary, #dc2626));
}

.service-card-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    margin-bottom: 19px;

    border: 1px solid #eceef2;
    border-radius: 12px;
    background: #f7f8fa;

    color: #374151;
    font-size: 1.15rem;
    font-weight: 700;

    transition:
        background 180ms ease,
        border-color 180ms ease,
        transform 180ms ease;
}

.service-card:hover .service-card-icon {
    background: #f1f3f6;
    border-color: #dfe3e8;
    transform: translateY(-2px);
}

.service-card h3 {
    margin: 0 0 9px;
    color: #1f2937;
    font-size: 1.02rem;
    font-weight: 700;
    line-height: 1.5;
    overflow-wrap: anywhere;
}

.service-card p {
    flex-grow: 1;
    margin: 0 0 23px;
    color: #6b7280;
    font-size: 0.86rem;
    line-height: 1.75;
    overflow-wrap: anywhere;
}

.service-card .button {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    min-height: 43px;
    box-sizing: border-box;
    margin-top: auto;
    text-align: center;
    border-radius: 8px;

    transition:
        filter 160ms ease,
        transform 160ms ease;
}

.service-card .button:not(:disabled):hover {
    filter: brightness(0.96);
}

.service-card .button:not(:disabled):active {
    transform: scale(0.985);
}

.service-card .button:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

/* ==========================================
   LIVE QUEUE CARD
========================================== */

.queue-status-card {
    position: relative;
    display: flex;
    flex-direction: column;
    min-width: 0;
    padding: 21px;
    overflow: hidden;

    background: #ffffff;
    border: 1px solid #e7e9ee;
    border-radius: 14px;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.035);
}

.queue-status-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--primary-color, var(--primary, #dc2626));
}

.queue-status-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 18px;
}

.queue-status-heading {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
}

.queue-status-icon {
    display: grid;
    flex-shrink: 0;
    width: 39px;
    height: 39px;
    place-items: center;

    border: 1px solid #eceef2;
    border-radius: 10px;
    background: #f7f8fa;
    color: #374151;
}

.queue-status-icon svg {
    width: 20px;
    height: 20px;
}

.queue-status-heading h3 {
    margin: 0 0 3px;
    color: #1f2937;
    font-size: 0.98rem;
    line-height: 1.4;
}

.queue-status-heading p {
    margin: 0;
    color: #6b7280;
    font-size: 0.75rem;
}

.queue-live-indicator {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    flex-shrink: 0;
    padding: 5px 8px;
    border: 1px solid #d1fae5;
    border-radius: 20px;
    background: #ecfdf5;
    color: #047857;
    font-size: 0.68rem;
    font-weight: 700;
}

.queue-live-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #10b981;
}

.queue-current-ticket {
    padding: 16px;
    margin-bottom: 13px;
    border: 1px solid #e5e7eb;
    border-radius: 11px;
    background: #f9fafb;
}

.queue-small-label {
    display: block;
    margin-bottom: 8px;
    color: #6b7280;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.055em;
}

.queue-current-number {
    display: block;
    margin-bottom: 6px;
    color: #111827;
    font-size: clamp(1.8rem, 2.5vw, 2.25rem);
    font-weight: 800;
    line-height: 1.15;
    letter-spacing: -0.04em;
    overflow-wrap: anywhere;
}

.queue-current-service {
    display: block;
    color: #6b7280;
    font-size: 0.79rem;
    line-height: 1.5;
    overflow-wrap: anywhere;
}

.queue-stats {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 13px;
}

.queue-stat {
    min-width: 0;
    padding: 12px;
    border: 1px solid #e7e9ee;
    border-radius: 10px;
    background: #ffffff;
}

.queue-stat-label {
    display: block;
    margin-bottom: 6px;
    color: #6b7280;
    font-size: 0.73rem;
    line-height: 1.4;
}

.queue-stat-value {
    display: block;
    color: #1f2937;
    font-size: 1.45rem;
    font-weight: 750;
    line-height: 1.2;
}

.queue-next-ticket {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 13px 14px;
    margin-top: auto;
    border: 1px solid #e7e9ee;
    border-radius: 10px;
    background: #ffffff;
}

.queue-next-ticket > div {
    min-width: 0;
}

.queue-next-ticket .queue-small-label {
    margin-bottom: 4px;
}

.queue-next-number {
    display: block;
    color: #1f2937;
    font-size: 1.05rem;
    font-weight: 750;
    line-height: 1.4;
    overflow-wrap: anywhere;
}

.queue-next-service {
    display: block;
    margin-top: 3px;
    color: #6b7280;
    font-size: 0.74rem;
    line-height: 1.45;
    overflow-wrap: anywhere;
}

.queue-next-arrow {
    display: grid;
    flex-shrink: 0;
    width: 31px;
    height: 31px;
    place-items: center;
    border: 1px solid #e5e7eb;
    border-radius: 9px;
    background: #f9fafb;
    color: #4b5563;
    font-size: 1.1rem;
}

.queue-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    flex-grow: 1;
    min-height: 105px;
    padding: 18px 12px;
    margin-bottom: 13px;
    border: 1px dashed #d1d5db;
    border-radius: 11px;
    background: #fafafa;
    text-align: center;
}

.queue-empty strong {
    margin-bottom: 5px;
    color: #374151;
    font-size: 0.88rem;
}

.queue-empty p {
    max-width: 230px;
    margin: 0;
    color: #6b7280;
    font-size: 0.77rem;
    line-height: 1.55;
}

.queue-status-footer {
    margin-top: 13px;
    color: #9ca3af;
    font-size: 0.7rem;
    line-height: 1.5;
}

/* ==========================================
   ACTIVE TICKET
========================================== */

.active-ticket-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    padding: 17px 19px;
}

.active-ticket-card > div {
    min-width: 0;
}

.active-ticket-card h3 {
    margin-top: 5px;
    margin-bottom: 6px;
    overflow-wrap: anywhere;
}

.active-ticket-card p {
    margin-top: 0;
    margin-bottom: 0;
}

.active-ticket-card .button {
    flex-shrink: 0;
    text-align: center;
}

/* ==========================================
   REMINDERS
========================================== */

.quick-link-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.quick-link-card {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    min-width: 0;
    padding: 15px;
}

.quick-link-icon {
    flex-shrink: 0;
}

.quick-link-card > span:last-child {
    min-width: 0;
}

.quick-link-card strong {
    display: block;
    margin-bottom: 5px;
    line-height: 1.5;
}

.quick-link-card small {
    display: block;
    line-height: 1.6;
}

.empty-state {
    padding: 28px 18px;
}

.empty-state h3 {
    margin-top: 0;
    margin-bottom: 7px;
}

.empty-state p {
    max-width: 460px;
    margin: 0 auto;
    line-height: 1.6;
}

.page-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-top: 28px;
    padding-top: 14px;
}

/* Tablet */
@media (max-width: 1050px) {
    .service-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 15px;
    }

    .service-card {
        padding: 20px;
    }

    .queue-status-card {
        padding: 19px;
    }
}

/* Mobile */
@media (max-width: 640px) {
    .main-content {
        padding-top: 15px;
        padding-bottom: 18px;
    }

    .page-header {
        margin-bottom: 16px;
    }

    .welcome-panel {
        gap: 12px;
        padding: 16px;
        margin-bottom: 22px;
    }

    .welcome-panel h2 {
        font-size: 1.1rem;
    }

    .welcome-panel p {
        font-size: 0.85rem;
    }

    .welcome-icon img {
        width: 36px;
        height: 36px;
    }

    .section-block {
        margin-bottom: 22px;
    }

    .section-heading {
        margin-bottom: 12px;
    }

    .service-grid {
        grid-template-columns: minmax(0, 1fr);
        gap: 13px;
    }

    .service-card {
        padding: 19px;
        border-radius: 12px;
    }

    .service-card-icon {
        width: 43px;
        height: 43px;
        margin-bottom: 14px;
    }

    .service-card p {
        margin-bottom: 18px;
    }

    .service-card:hover {
        transform: none;
    }

    .queue-status-card {
        padding: 18px;
    }

    .queue-current-number {
        font-size: 2rem;
    }

    .active-ticket-card {
        align-items: stretch;
        flex-direction: column;
        gap: 14px;
        padding: 16px;
    }

    .active-ticket-card .button {
        width: 100%;
        box-sizing: border-box;
    }

    .quick-link-grid {
        grid-template-columns: minmax(0, 1fr);
        gap: 10px;
    }

    .quick-link-card {
        padding: 13px;
    }

    .page-footer {
        align-items: flex-start;
        flex-wrap: wrap;
        font-size: 0.78rem;
    }
}

/* Reduced motion accessibility */
@media (prefers-reduced-motion: reduce) {
    .service-card,
    .service-card::before,
    .service-card-icon,
    .service-card .button {
        transition: none;
    }

    .service-card:hover,
    .service-card:hover .service-card-icon {
        transform: none;
    }
}
</style>

<main class="main-content">

    <header class="page-header">
        <div>
            <p class="eyebrow">STUDENT PORTAL</p>

            <h1>Finance Services</h1>

            <p class="page-description">
                Select the finance office service related to your transaction.
            </p>
        </div>
    </header>

    <section class="welcome-panel">
        <div>
            <span class="welcome-label">RMMCMI FINANCE OFFICE</span>

            <h2>Which service do you need?</h2>

            <p>
                Choose the appropriate service before requesting a queue number.
                Please make sure you select the correct transaction type.
            </p>
        </div>

        <div class="welcome-icon" aria-hidden="true">
            <img src="assets/img/icon.png" alt="">
        </div>
    </section>

    <?php if ($activeTicket): ?>

        <section class="section-block">
            <div class="section-heading">
                <div>
                    <h2>You already have an active ticket</h2>

                    <p>
                        Finish or cancel your current queue before
                        requesting another ticket.
                    </p>
                </div>
            </div>

            <div class="active-ticket-card">
                <div>
                    <span class="muted-label">CURRENT SERVICE</span>

                    <h3>
                        <?= qflow_escape($activeTicket['service_name']) ?>
                    </h3>

                    <p>
                        Status:
                        <?= qflow_escape($activeTicket['queue_name']) ?>
                    </p>
                </div>

                <a href="my-queue.php" class="button button-primary">
                    View My Queue
                </a>
            </div>
        </section>

    <?php endif; ?>

    <section class="section-block">

        <div class="section-heading">
            <div>
                <h2>Available Services</h2>

                <p>
                    <?= count($services) ?> active
                    <?= count($services) === 1 ? 'service' : 'services' ?>
                </p>
            </div>
        </div>

        <?php if (!$services): ?>

            <div class="empty-state">
                <div class="empty-state-icon">!</div>

                <h3>No services available</h3>

                <p>
                    There are currently no active finance services.
                    Please contact the finance office.
                </p>
            </div>

        <?php else: ?>

            <div class="service-grid">

                <?php foreach ($services as $service): ?>

                    <?php
                    $serviceName = (string) $service['service_name'];

                    $description = trim(
                        (string) ($service['description'] ?? '')
                    );

                    if ($description === '') {
                        $description =
                            'Get assistance with your ' .
                            $serviceName .
                            ' transaction at the RMMCMI Finance Office.';
                    }
                    ?>

                    <article class="service-card">

                        <div class="service-card-icon" aria-hidden="true">
                            <span>
                                <?= qflow_escape(
                                    strtoupper(substr($serviceName, 0, 1))
                                ) ?>
                            </span>
                        </div>

                        <h3>
                            <?= qflow_escape($serviceName) ?>
                        </h3>

                        <p>
                            <?= qflow_escape($description) ?>
                        </p>

                        <?php if ($activeTicket): ?>

                            <button
                                class="button button-primary"
                                type="button"
                                disabled
                                title="Finish your current queue first."
                            >
                                Active Ticket Exists
                            </button>

                        <?php else: ?>

                            <a
                                href="queue.php?service_id=<?= (int) $service['service_id'] ?>"
                                class="button button-primary"
                            >
                                Get Queue Number
                            </a>

                        <?php endif; ?>

                    </article>

                <?php endforeach; ?>

                <!-- Live queue card fills the remaining grid space. -->
                <aside class="queue-status-card">

                    <div class="queue-status-header">
                        <div class="queue-status-heading">

                            <div class="queue-status-icon" aria-hidden="true">
                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <path d="M4 5h16v14H4z"></path>
                                    <path d="M8 9h8"></path>
                                    <path d="M8 13h5"></path>
                                    <path d="M8 16h3"></path>
                                </svg>
                            </div>

                            <div>
                                <h3>Live Queue</h3>
                                <p>Finance Office</p>
                            </div>

                        </div>

                        <span class="queue-live-indicator">
                            <span class="queue-live-dot"></span>
                            TODAY
                        </span>
                    </div>

                    <?php if ($currentServing): ?>

                        <div class="queue-current-ticket">
                            <span class="queue-small-label">
                                NOW SERVING
                            </span>

                            <strong class="queue-current-number">
                                <?= qflow_escape($currentServing['queue_number']) ?>
                            </strong>

                            <span class="queue-current-service">
                                <?= qflow_escape($currentServing['service_name']) ?>
                            </span>
                        </div>

                    <?php else: ?>

                        <div class="queue-empty">
                            <strong>No ticket is being served</strong>

                            <p>
                                The finance office has no ticket
                                currently marked as serving.
                            </p>
                        </div>

                    <?php endif; ?>

                    <div class="queue-stats">

                        <div class="queue-stat">
                            <span class="queue-stat-label">
                                Waiting
                            </span>

                            <strong class="queue-stat-value">
                                <?= $waitingCount ?>
                            </strong>
                        </div>

                        <div class="queue-stat">
                            <span class="queue-stat-label">
                                Being served
                            </span>

                            <strong class="queue-stat-value">
                                <?= $servingCount ?>
                            </strong>
                        </div>

                    </div>

                    <div class="queue-next-ticket">

                        <div>
                            <span class="queue-small-label">
                                UP NEXT
                            </span>

                            <?php if ($nextWaiting): ?>

                                <strong class="queue-next-number">
                                    <?= qflow_escape($nextWaiting['queue_number']) ?>
                                </strong>

                                <span class="queue-next-service">
                                    <?= qflow_escape($nextWaiting['service_name']) ?>
                                </span>

                            <?php else: ?>

                                <strong class="queue-next-number">
                                    No waiting ticket
                                </strong>

                                <span class="queue-next-service">
                                    The waiting queue is empty.
                                </span>

                            <?php endif; ?>
                        </div>

                        <span class="queue-next-arrow" aria-hidden="true">
                            &rarr;
                        </span>

                    </div>

                    <div class="queue-status-footer">
                        Queue information is based on today's ticket records.
                    </div>

                </aside>

            </div>

        <?php endif; ?>

    </section>

    <section class="section-block">

        <div class="section-heading">
            <div>
                <h2>Before Getting a Ticket</h2>

                <p>Keep these reminders in mind.</p>
            </div>
        </div>

        <div class="quick-link-grid">

            <div class="quick-link-card">
                <span class="quick-link-icon">1</span>

                <span>
                    <strong>Choose the correct service</strong>

                    <small>
                        Select Cashier or Receivables based on your transaction.
                    </small>
                </span>
            </div>

            <div class="quick-link-card">
                <span class="quick-link-icon">2</span>

                <span>
                    <strong>Keep your ticket number</strong>

                    <small>
                        Use My Queue to check your ticket and its current status.
                    </small>
                </span>
            </div>

        </div>

    </section>

    <footer class="page-footer">
        <span>QueueFlow — RMMCMI Finance Queue Management System</span>

        <span><?= date('Y') ?></span>
    </footer>

</main>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>