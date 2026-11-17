
<?php

require_once dirname(__DIR__) . '/includes/functions.php';

qflow_start_session();
qflow_require_login();

$pdo = require dirname(__DIR__) . '/config/database.php';

$userId = (int) $_SESSION['user_id'];

$userStatement = $pdo->prepare(
    'SELECT account_status
     FROM users
     WHERE user_id = :user_id
     LIMIT 1'
);

$userStatement->execute(['user_id' => $userId]);
$user = $userStatement->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['account_status'] !== 'active') {
    $_SESSION = [];
    session_destroy();

    header('Location: login.php');
    exit;
}

$pageTitle = 'Live Queue';

require dirname(__DIR__) . '/includes/header.php';
require dirname(__DIR__) . '/includes/sidebar.php';

?>

<style>
/* =========================================================
   QUEUEFLOW LIVE QUEUE
   Responsive layout
========================================================= */

.main-content {
    width: 100%;
    max-width: none;
    min-width: 0;
    margin: 0;
    padding: 24px;
    box-sizing: border-box;
}

.live-queue-page,
.live-queue-page * {
    box-sizing: border-box;
}

.live-queue-page {
    width: 100%;
    min-width: 0;
    padding-bottom: 30px;
    color: #263449;
}

/* PAGE INTRODUCTION */

.page-intro {
    margin-bottom: 22px;
}

.page-intro .eyebrow {
    margin: 0 0 7px;
    color: #c8102e;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 1.4px;
    text-transform: uppercase;
}

.page-intro h1 {
    margin: 0;
    color: #1c293b;
    font-size: clamp(25px, 3vw, 32px);
    font-weight: 800;
    line-height: 1.25;
}

.page-intro p {
    max-width: 650px;
    margin: 8px 0 0;
    color: #7b8491;
    font-size: 14px;
    line-height: 1.6;
}

/* LIVE STATUS */

.live-status-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 20px;
    padding: 13px 16px;
    background: #fff;
    border: 1px solid #e8ebef;
    border-radius: 10px;
}

.live-status {
    display: flex;
    align-items: center;
    gap: 9px;
    color: #187343;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .4px;
}

.live-dot {
    width: 8px;
    height: 8px;
    flex-shrink: 0;
    border-radius: 50%;
    background: #26965a;
    box-shadow: 0 0 0 4px #e5f5eb;
}

.last-updated {
    color: #8b93a0;
    font-size: 11px;
}

/* SUMMARY CARDS */

.summary-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 15px;
    margin-bottom: 24px;
}

.summary-card {
    display: flex;
    align-items: center;
    gap: 14px;
    min-width: 0;
    padding: 18px;
    background: #fff;
    border: 1px solid #e8ebef;
    border-radius: 11px;
}

.summary-icon {
    display: grid;
    place-items: center;
    flex-shrink: 0;
    width: 43px;
    height: 43px;
    border-radius: 10px;
    background: #f8f0f1;
    color: #c8102e;
    font-size: 15px;
    font-weight: 800;
}

.summary-text {
    min-width: 0;
}

.summary-text span {
    display: block;
    margin-bottom: 5px;
    color: #7b8491;
    font-size: 11px;
    font-weight: 600;
}

.summary-text strong {
    display: block;
    color: #1c293b;
    font-size: 25px;
    font-weight: 800;
    line-height: 1.2;
}

/* SERVICE SECTION */

.service-section {
    width: 100%;
    min-width: 0;
    margin-bottom: 20px;
    overflow: hidden;
    background: #fff;
    border: 1px solid #e8ebef;
    border-radius: 11px;
}

.service-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 18px 20px;
    border-bottom: 1px solid #edf0f3;
}

.service-heading h2 {
    min-width: 0;
    margin: 0;
    color: #1c293b;
    font-size: 17px;
    font-weight: 800;
    overflow-wrap: anywhere;
}

.service-count {
    flex-shrink: 0;
    padding: 6px 10px;
    border-radius: 20px;
    background: #fff0f2;
    color: #c8102e;
    font-size: 10px;
    font-weight: 800;
}

.service-content {
    padding: 20px;
}

.section-title {
    margin: 0 0 13px;
    color: #263449;
    font-size: 13px;
    font-weight: 800;
}

/* COUNTER CARDS */

.counter-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 25px;
}

.counter-card {
    min-width: 0;
    padding: 15px;
    border: 1px solid #e8ebef;
    border-radius: 9px;
    background: #fff;
}

.counter-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 15px;
}

.counter-name {
    color: #263449;
    font-size: 12px;
    font-weight: 800;
    overflow-wrap: anywhere;
}

.counter-status {
    padding: 5px 8px;
    border-radius: 20px;
    font-size: 9px;
    font-weight: 800;
}

.status-serving {
    background: #e3f5eb;
    color: #187343;
}

.status-available {
    background: #eaf2ff;
    color: #2458a2;
}

.status-busy {
    background: #fff2df;
    color: #a9630b;
}

.status-unavailable,
.status-not-configured {
    background: #edf0f4;
    color: #667085;
}

.serving-label {
    margin-bottom: 6px;
    color: #9299a5;
    font-size: 9px;
    font-weight: 800;
    letter-spacing: .6px;
}

.serving-ticket {
    min-height: 27px;
    color: #c8102e;
    font-size: 22px;
    font-weight: 900;
    overflow-wrap: anywhere;
}

.no-ticket {
    color: #a0a8b5;
    font-size: 12px;
    font-weight: 500;
}

/* WAITING QUEUE */

.waiting-queue-container {
    width: 100%;
    min-width: 0;
}

.waiting-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 14px;
}

.waiting-heading h3 {
    margin: 0;
    color: #263449;
    font-size: 14px;
    font-weight: 800;
}

.waiting-count {
    color: #858e9b;
    font-size: 11px;
    font-weight: 600;
}

/* SEARCH */

.queue-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
}

.queue-search {
    width: 100%;
    max-width: 300px;
}

.queue-search input {
    width: 100%;
    height: 39px;
    padding: 0 12px;
    border: 1px solid #e3e7ed;
    border-radius: 7px;
    outline: none;
    background: #fff;
    color: #263449;
    font-family: inherit;
    font-size: 12px;
    transition: border-color .2s, box-shadow .2s;
}

.queue-search input:focus {
    border-color: #c8102e;
    box-shadow: 0 0 0 3px rgba(200, 16, 46, .08);
}

.queue-search input::placeholder {
    color: #a0a8b5;
}

.queue-results-label {
    color: #89919d;
    font-size: 11px;
    white-space: nowrap;
}

/* TABLE */

.queue-table-wrapper {
    width: 100%;
    overflow-x: auto;
    border: 1px solid #e8ebef;
    border-radius: 9px;
    scrollbar-width: thin;
}

.queue-table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
}

.queue-table thead {
    background: #f8f9fb;
}

.queue-table th {
    padding: 12px 15px;
    color: #858e9b;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .5px;
    text-transform: uppercase;
    white-space: nowrap;
}

.queue-table td {
    padding: 12px 15px;
    border-top: 1px solid #edf0f3;
    color: #4b5563;
    font-size: 12px;
}

.queue-table tbody tr {
    transition: background .15s ease;
}

.queue-table tbody tr:hover {
    background: #fffafb;
}

.queue-position {
    color: #89919d;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

.ticket-number {
    color: #263449;
    font-size: 13px;
    font-weight: 800;
    overflow-wrap: anywhere;
}

.priority-tag {
    display: inline-flex;
    align-items: center;
    padding: 5px 8px;
    border-radius: 5px;
    background: #fff0f2;
    color: #c8102e;
    font-size: 9px;
    font-weight: 800;
    white-space: nowrap;
}

.regular-tag {
    color: #858e9b;
    font-size: 10px;
    font-weight: 600;
}

/* EMPTY STATES */

.empty-state {
    padding: 28px 15px;
    color: #9299a5;
    font-size: 12px;
    text-align: center;
}

.loading-state,
.error-state {
    padding: 30px 20px;
    border: 1px solid #e8ebef;
    border-radius: 9px;
    background: #fff;
    color: #7b8491;
    font-size: 12px;
    text-align: center;
}

.error-state {
    color: #c8102e;
}

/* PAGINATION */

.queue-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 14px;
}

.pagination-info {
    color: #89919d;
    font-size: 11px;
}

.pagination-controls {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 5px;
}

.page-button {
    min-width: 32px;
    height: 32px;
    padding: 0 9px;
    border: 1px solid #e3e7ed;
    border-radius: 6px;
    background: #fff;
    color: #596273;
    font-family: inherit;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
}

.page-button:hover:not(:disabled) {
    border-color: #c8102e;
    color: #c8102e;
}

.page-button.active {
    border-color: #c8102e;
    background: #c8102e;
    color: #fff;
}

.page-button:disabled {
    opacity: .4;
    cursor: not-allowed;
}

/* FOOTER */

.queue-footer {
    margin-top: 25px;
    color: #89919d;
    font-size: 10px;
    text-align: center;
}

/* TABLET */

@media (max-width: 1050px) {
    .main-content {
        padding: 20px;
    }

    .counter-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

/* MOBILE */

@media (max-width: 640px) {
    .main-content {
        padding: 16px 13px 20px;
    }

    .page-intro {
        margin-bottom: 16px;
    }

    .page-intro h1 {
        font-size: 25px;
    }

    .page-intro p {
        font-size: 12px;
    }

    .live-status-bar {
        align-items: flex-start;
        flex-direction: column;
        padding: 12px;
    }

    .summary-grid {
        gap: 8px;
        margin-bottom: 17px;
    }

    .summary-card {
        align-items: flex-start;
        flex-direction: column;
        gap: 8px;
        padding: 12px 9px;
    }

    .summary-icon {
        width: 32px;
        height: 32px;
        font-size: 12px;
    }

    .summary-text span {
        font-size: 9px;
        line-height: 1.4;
    }

    .summary-text strong {
        font-size: 20px;
    }

    .service-heading {
        padding: 14px;
    }

    .service-heading h2 {
        font-size: 15px;
    }

    .service-content {
        padding: 13px;
    }

    .counter-grid {
        gap: 8px;
        margin-bottom: 20px;
    }

    .counter-card {
        padding: 11px;
    }

    .counter-top {
        align-items: flex-start;
        flex-direction: column;
        gap: 7px;
        margin-bottom: 11px;
    }

    .serving-ticket {
        font-size: 19px;
    }

    .queue-toolbar {
        align-items: stretch;
        flex-direction: column;
    }

    .queue-search {
        max-width: none;
    }

    .queue-results-label {
        white-space: normal;
    }

    .queue-table th,
    .queue-table td {
        padding: 11px 10px;
    }

    .queue-pagination {
        align-items: flex-start;
        flex-direction: column;
    }
}

/* SMALL PHONES */

@media (max-width: 380px) {
    .summary-card {
        padding: 10px 7px;
    }

    .summary-text span {
        font-size: 8px;
    }

    .summary-text strong {
        font-size: 18px;
    }

    .counter-grid {
        grid-template-columns: 1fr;
    }

    .queue-table th,
    .queue-table td {
        padding: 10px 8px;
        font-size: 10px;
    }
}

/* REDUCED MOTION */

@media (prefers-reduced-motion: reduce) {
    .live-queue-page *,
    .live-queue-page *::before,
    .live-queue-page *::after {
        transition: none !important;
        animation: none !important;
    }
}
</style>

<main class="main-content">

    <div class="live-queue-page">

        <section class="page-intro">
            <p class="eyebrow">FINANCE OFFICE</p>
            <h1>Live Queue Status</h1>
            <p>
                Monitor the current queue for Cashier and Receivables.
                Check which tickets are being served and which are still waiting.
            </p>
        </section>

        <div class="live-status-bar">
            <div class="live-status">
                <span class="live-dot"></span>
                LIVE QUEUE UPDATES
            </div>

            <span class="last-updated" id="lastUpdated">
                Connecting to queue server...
            </span>
        </div>

        <section class="summary-grid">

            <div class="summary-card">
                <div class="summary-icon">S</div>
                <div class="summary-text">
                    <span>Currently Serving</span>
                    <strong id="totalServing">0</strong>
                </div>
            </div>

            <div class="summary-card">
                <div class="summary-icon">W</div>
                <div class="summary-text">
                    <span>Waiting Tickets</span>
                    <strong id="totalWaiting">0</strong>
                </div>
            </div>

            <div class="summary-card">
                <div class="summary-icon">C</div>
                <div class="summary-text">
                    <span>Active Counters</span>
                    <strong id="totalCounters">0</strong>
                </div>
            </div>

        </section>

        <div id="queueContainer">
            <div class="loading-state">
                Loading live queue information...
            </div>
        </div>

        <div class="queue-footer">
            QueueFlow — RMMC-MI Finance Queue Management System
        </div>

    </div>

</main>

<script>
/* =========================================================
   LIVE QUEUE CONFIGURATION
========================================================= */

const queueContainer = document.getElementById('queueContainer');

const QUEUE_PAGE_SIZE = 10;

let hasLoadedSuccessfully = false;
let queueServices = [];

const queueSearchTerms = {};
const queueCurrentPages = {};

/* =========================================================
   HTML ESCAPING
========================================================= */

function escapeHTML(value) {

    return String(value ?? '').replace(/[&<>"']/g, character => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    })[character]);

}

/* =========================================================
   SERVICE IDENTIFIER
========================================================= */

function getServiceKey(service, index) {
    return String(service.service_name || `service-${index}`);
}

/* =========================================================
   COUNTER STATUS CLASS
========================================================= */

function getStatusClass(status) {

    switch (String(status || '').toLowerCase()) {

        case 'serving':
            return 'status-serving';

        case 'available':
            return 'status-available';

        case 'busy':
            return 'status-busy';

        case 'unavailable':
            return 'status-unavailable';

        default:
            return 'status-not-configured';
    }
}

/* =========================================================
   COUNTER RENDERING
========================================================= */

function renderCounters(counters) {

    if (!counters.length) {
        return `
            <div class="empty-state">
                No counters configured for this service.
            </div>
        `;
    }

    return counters.map(counter => {

        const servingTickets = Array.isArray(counter.serving)
            ? counter.serving
            : [];

        const status = counter.status || 'Not configured';

        const ticketDisplay = servingTickets.length

            ? servingTickets.map(ticket => `
                <div class="serving-ticket">
                    ${escapeHTML(ticket.ticket_number || 'Ticket')}
                </div>
            `).join('')

            : `
                <div class="serving-ticket">
                    <span class="no-ticket">No ticket</span>
                </div>
            `;

        return `
            <div class="counter-card">

                <div class="counter-top">

                    <span class="counter-name">
                        ${escapeHTML(counter.counter_name || 'Counter')}
                    </span>

                    <span class="counter-status ${getStatusClass(status)}">
                        ${escapeHTML(status)}
                    </span>

                </div>

                <div class="serving-label">
                    NOW SERVING
                </div>

                ${ticketDisplay}

            </div>
        `;

    }).join('');
}

/* =========================================================
   WAITING QUEUE RENDERING
========================================================= */

function renderWaitingQueue(waiting, serviceKey) {

    const searchTerm = (
        queueSearchTerms[serviceKey] || ''
    ).trim().toLowerCase();

    const filtered = waiting.filter(ticket => {

        return String(ticket.ticket_number || '')
            .toLowerCase()
            .includes(searchTerm);

    });

    const totalPages = Math.max(
        1,
        Math.ceil(filtered.length / QUEUE_PAGE_SIZE)
    );

    let currentPage = queueCurrentPages[serviceKey] || 1;

    currentPage = Math.min(currentPage, totalPages);
    currentPage = Math.max(currentPage, 1);

    queueCurrentPages[serviceKey] = currentPage;

    const startIndex = (currentPage - 1) * QUEUE_PAGE_SIZE;

    const visibleTickets = filtered.slice(
        startIndex,
        startIndex + QUEUE_PAGE_SIZE
    );

    const tableRows = visibleTickets.length

        ? visibleTickets.map(ticket => {

            const originalPosition = waiting.indexOf(ticket) + 1;

            const ticketType = ticket.is_priority
                ? '<span class="priority-tag">PRIORITY</span>'
                : '<span class="regular-tag">Regular</span>';

            return `
                <tr>
                    <td>
                        <span class="queue-position">
                            ${String(originalPosition).padStart(2, '0')}
                        </span>
                    </td>

                    <td>
                        <span class="ticket-number">
                            ${escapeHTML(ticket.ticket_number)}
                        </span>
                    </td>

                    <td>${ticketType}</td>
                </tr>
            `;

        }).join('')

        : `
            <tr>
                <td colspan="3">
                    <div class="empty-state">
                        ${
                            searchTerm
                                ? 'No matching tickets found.'
                                : 'There are currently no waiting tickets.'
                        }
                    </div>
                </td>
            </tr>
        `;

    const paginationButtons = [];

    paginationButtons.push(`
        <button
            type="button"
            class="page-button"
            data-service="${escapeHTML(serviceKey)}"
            data-page="${currentPage - 1}"
            ${currentPage === 1 ? 'disabled' : ''}
        >
            Previous
        </button>
    `);

    for (let page = 1; page <= totalPages; page++) {

        paginationButtons.push(`
            <button
                type="button"
                class="page-button ${page === currentPage ? 'active' : ''}"
                data-service="${escapeHTML(serviceKey)}"
                data-page="${page}"
                ${page === currentPage ? 'aria-current="page"' : ''}
            >
                ${page}
            </button>
        `);

    }

    paginationButtons.push(`
        <button
            type="button"
            class="page-button"
            data-service="${escapeHTML(serviceKey)}"
            data-page="${currentPage + 1}"
            ${currentPage === totalPages ? 'disabled' : ''}
        >
            Next
        </button>
    `);

    const firstItem = filtered.length ? startIndex + 1 : 0;

    const lastItem = Math.min(
        startIndex + QUEUE_PAGE_SIZE,
        filtered.length
    );

    return `

        <div class="waiting-heading">
            <h3>Waiting Queue</h3>

            <span class="waiting-count">
                ${waiting.length} ticket(s) waiting
            </span>
        </div>

        <div class="queue-toolbar">

            <div class="queue-search">
                <input
                    type="search"
                    class="queue-search-input"
                    data-service="${escapeHTML(serviceKey)}"
                    placeholder="Search ticket number..."
                    value="${escapeHTML(queueSearchTerms[serviceKey] || '')}"
                    aria-label="Search waiting tickets"
                >
            </div>

            <span class="queue-results-label">
                ${filtered.length} matching ticket(s)
            </span>

        </div>

        <div class="queue-table-wrapper">

            <table class="queue-table">

                <thead>
                    <tr>
                        <th>Position</th>
                        <th>Ticket Number</th>
                        <th>Ticket Type</th>
                    </tr>
                </thead>

                <tbody>
                    ${tableRows}
                </tbody>

            </table>

        </div>

        <div class="queue-pagination">

            <span class="pagination-info">
                Showing ${firstItem}–${lastItem} of ${filtered.length} tickets
            </span>

            <div class="pagination-controls">
                ${paginationButtons.join('')}
            </div>

        </div>

    `;
}

/* =========================================================
   SERVICE RENDERING
========================================================= */

function renderService(service, index) {

    const counters = Array.isArray(service.counters)
        ? service.counters
        : [];

    const waiting = Array.isArray(service.waiting)
        ? service.waiting
        : [];

    const serviceKey = getServiceKey(service, index);

    return `

        <section class="service-section">

            <div class="service-heading">

                <h2>
                    ${escapeHTML(service.service_name || 'Finance Service')}
                </h2>

                <span class="service-count">
                    ${waiting.length} Waiting
                </span>

            </div>

            <div class="service-content">

                <h3 class="section-title">
                    Counter Status
                </h3>

                <div class="counter-grid">
                    ${renderCounters(counters)}
                </div>

                <div
                    class="waiting-queue-container"
                    data-service-container="${escapeHTML(serviceKey)}"
                >
                    ${renderWaitingQueue(waiting, serviceKey)}
                </div>

            </div>

        </section>

    `;
}

/* =========================================================
   RENDER ALL SERVICES
========================================================= */

function renderQueueServices(services) {

    if (!services.length) {

        queueContainer.innerHTML = `
            <div class="loading-state">
                No active finance services are currently available.
            </div>
        `;

        return;
    }

    const activeInput = document.activeElement;

    let activeService = null;
    let selectionStart = null;
    let selectionEnd = null;

    if (
        activeInput &&
        activeInput.classList.contains('queue-search-input')
    ) {

        activeService = activeInput.dataset.service;
        selectionStart = activeInput.selectionStart;
        selectionEnd = activeInput.selectionEnd;
    }

    queueContainer.innerHTML = services
        .map((service, index) => renderService(service, index))
        .join('');

    if (activeService !== null) {

        const replacementInput = Array.from(
            queueContainer.querySelectorAll('.queue-search-input')
        ).find(input => input.dataset.service === activeService);

        if (replacementInput) {

            replacementInput.focus();

            if (selectionStart !== null && selectionEnd !== null) {
                replacementInput.setSelectionRange(
                    selectionStart,
                    selectionEnd
                );
            }
        }
    }
}

/* =========================================================
   SEARCH EVENT
========================================================= */

queueContainer.addEventListener('input', function(event) {

    if (!event.target.matches('.queue-search-input')) {
        return;
    }

    const input = event.target;
    const serviceKey = input.dataset.service;

    queueSearchTerms[serviceKey] = input.value;
    queueCurrentPages[serviceKey] = 1;

    const serviceIndex = queueServices.findIndex(
        (service, index) =>
            getServiceKey(service, index) === serviceKey
    );

    if (serviceIndex === -1) return;

    const container = input.closest('.waiting-queue-container');

    if (!container) return;

    const waiting = Array.isArray(queueServices[serviceIndex].waiting)
        ? queueServices[serviceIndex].waiting
        : [];

    const cursor = input.selectionStart;

    container.innerHTML = renderWaitingQueue(
        waiting,
        serviceKey
    );

    const replacementInput = container.querySelector(
        '.queue-search-input'
    );

    if (replacementInput) {

        replacementInput.focus();

        if (cursor !== null) {
            replacementInput.setSelectionRange(cursor, cursor);
        }
    }
});

/* =========================================================
   PAGINATION EVENT
========================================================= */

queueContainer.addEventListener('click', function(event) {

    const button = event.target.closest('.page-button');

    if (!button || button.disabled) {
        return;
    }

    const serviceKey = button.dataset.service;
    const page = Number(button.dataset.page);

    if (!Number.isInteger(page) || page < 1) {
        return;
    }

    queueCurrentPages[serviceKey] = page;

    const serviceIndex = queueServices.findIndex(
        (service, index) =>
            getServiceKey(service, index) === serviceKey
    );

    if (serviceIndex === -1) return;

    const container = button.closest('.waiting-queue-container');

    if (!container) return;

    const waiting = Array.isArray(queueServices[serviceIndex].waiting)
        ? queueServices[serviceIndex].waiting
        : [];

    container.innerHTML = renderWaitingQueue(
        waiting,
        serviceKey
    );
});

/* =========================================================
   LOAD LIVE QUEUE DATA
========================================================= */

async function loadLiveQueue() {

    try {

        const response = await fetch(
            '/api/queue-monitor-data.php?_=' + Date.now(),
            {
                method: 'GET',
                cache: 'no-store',
                headers: {
                    'Accept': 'application/json'
                }
            }
        );

        if (!response.ok) {
            throw new Error('Unable to connect to the queue server.');
        }

        const data = await response.json();

        if (
            !data.success ||
            !Array.isArray(data.services)
        ) {
            throw new Error('Invalid queue information received.');
        }

        queueServices = data.services;

        let totalWaiting = 0;
        let totalServing = 0;
        let totalCounters = 0;

        queueServices.forEach(service => {

            const waiting = Array.isArray(service.waiting)
                ? service.waiting
                : [];

            const serving = Array.isArray(service.serving)
                ? service.serving
                : [];

            const counters = Array.isArray(service.counters)
                ? service.counters
                : [];

            totalWaiting += waiting.length;
            totalServing += serving.length;
            totalCounters += counters.length;
        });

        document.getElementById('totalWaiting').textContent =
            totalWaiting;

        document.getElementById('totalServing').textContent =
            totalServing;

        document.getElementById('totalCounters').textContent =
            totalCounters;

        renderQueueServices(queueServices);

        const updated = data.updated_at
            ? new Date(data.updated_at)
            : new Date();

        document.getElementById('lastUpdated').textContent =
            'Last updated: ' + updated.toLocaleTimeString();

        hasLoadedSuccessfully = true;

    } catch (error) {

        console.error('Live queue error:', error);

        if (!hasLoadedSuccessfully) {

            queueContainer.innerHTML = `
                <div class="error-state">
                    Unable to load live queue information.
                    Please try again later.
                </div>
            `;
        }

        document.getElementById('lastUpdated').textContent =
            'Connection issue. Retrying automatically...';
    }
}

/* =========================================================
   INITIAL LOAD AND AUTOMATIC REFRESH
========================================================= */

loadLiveQueue();

setInterval(loadLiveQueue, 3000);

</script>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
