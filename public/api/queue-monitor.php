
<?php
require_once '../../includes/functions.php';

$pageTitle = 'QueueFlow | Live Queue Display';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">

<title><?= qflow_escape($pageTitle) ?></title>

<link rel="icon" type="image/png" href="../assets/img/icon.png">

<style>
:root {
    --red: #a71930;
    --dark-red: #781225;
    --navy: #172b49;
    --blue: #24568a;
    --green: #187343;
    --orange: #a85d0a;
    --gray: #64748b;
    --light: #f1f4f8;
    --border: #dce2e9;
}

* {
    box-sizing: border-box;
}

html, body {
    width: 100%;
    height: 100%;
    margin: 0;
    overflow: hidden;
    font-family: Arial, Helvetica, sans-serif;
    background: #e9edf2;
    color: #1d2939;
}

body {
    display: flex;
    flex-direction: column;
}

/* HEADER */

.header {
    height: 100px;
    flex-shrink: 0;
    background: var(--red);
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 3%;
    border-bottom: 6px solid var(--dark-red);
}

.brand {
    display: flex;
    align-items: center;
    gap: 16px;
}

.brand img {
    width: 65px;
    height: 65px;
    object-fit: contain;
    background: white;
    border-radius: 50%;
    padding: 5px;
}

.brand h1 {
    font-size: clamp(25px, 2.2vw, 36px);
    margin: 0;
    font-weight: 900;
    letter-spacing: 1px;
}

.brand p {
    margin: 5px 0 0;
    font-size: 13px;
    letter-spacing: 2px;
    font-weight: bold;
}

.header-center {
    text-align: center;
    font-size: clamp(17px, 1.5vw, 24px);
    font-weight: 900;
    letter-spacing: 2px;
}

.header-center span {
    display: block;
    margin-top: 6px;
    font-size: 11px;
    letter-spacing: 3px;
    font-weight: normal;
}

/* VOICE BUTTON */

.voice-button {
    margin-top: 9px;
    padding: 8px 16px;
    border: 2px solid white;
    border-radius: 5px;
    background: white;
    color: var(--red);
    font-size: 12px;
    font-weight: 900;
    cursor: pointer;
    letter-spacing: .5px;
}

.voice-button:hover {
    background: #f1f4f8;
}

.voice-button.enabled {
    background: var(--green);
    border-color: var(--green);
    color: white;
    cursor: default;
}

.voice-button:disabled {
    opacity: 1;
}

.clock {
    text-align: right;
    min-width: 160px;
}

.clock strong {
    display: block;
    font-size: clamp(21px, 2vw, 31px);
}

.clock span {
    font-size: 13px;
}

/* MAIN SCREEN */

.main {
    flex: 1;
    min-height: 0;
    display: grid;
    grid-template-rows: auto minmax(0, 1fr);
    gap: 13px;
    padding: 15px 2.5%;
    overflow: hidden;
}

.section-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.section-heading h2 {
    margin: 0;
    color: var(--navy);
    font-size: clamp(18px, 1.5vw, 25px);
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.section-heading small {
    color: var(--gray);
    font-size: 12px;
}

/* COUNTERS */

.counter-area {
    min-height: 0;
    display: flex;
    flex-direction: column;
}

.counter-groups {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 13px;
    min-height: 0;
}

.counter-group {
    min-width: 0;
    background: white;
    border: 1px solid var(--border);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 5px rgba(0,0,0,.04);
}

.group-title {
    background: var(--navy);
    color: white;
    padding: 11px 15px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: clamp(15px, 1.3vw, 21px);
    font-weight: 900;
    letter-spacing: 1px;
}

.group-title span {
    font-size: 11px;
    font-weight: normal;
    letter-spacing: 0;
}

.counter-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    padding: 11px;
}

.receivables-grid {
    grid-template-columns: 1fr;
}

.counter-card {
    min-width: 0;
    min-height: 105px;
    background: #f7f8fa;
    border: 1px solid var(--border);
    border-left: 6px solid var(--gray);
    border-radius: 6px;
    padding: 10px 13px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.counter-card.available {
    border-left-color: var(--green);
}

.counter-card.serving {
    border-left-color: var(--blue);
    background: #f0f5fc;
}

.counter-card.busy {
    border-left-color: var(--orange);
}

.counter-card.unavailable {
    border-left-color: #bd3030;
    background: #fff5f5;
}

.counter-card.not-configured {
    border-left-color: var(--gray);
}

.counter-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 5px;
}

.counter-name {
    font-size: clamp(13px, 1.1vw, 18px);
    font-weight: 900;
    color: var(--navy);
}

.status {
    padding: 5px 8px;
    border-radius: 3px;
    color: white;
    font-size: clamp(9px, .75vw, 12px);
    font-weight: 900;
    text-transform: uppercase;
    white-space: nowrap;
}

.status.available {
    background: var(--green);
}

.status.serving {
    background: var(--blue);
}

.status.busy {
    background: var(--orange);
}

.status.unavailable {
    background: #bd3030;
}

.status.not-configured {
    background: var(--gray);
}

.ticket-label {
    color: var(--gray);
    font-size: 10px;
    font-weight: bold;
    letter-spacing: 1px;
    margin-top: 7px;
}

.ticket-number {
    color: var(--red);
    font-size: clamp(25px, 2.5vw, 40px);
    font-weight: 900;
    line-height: 1.1;
    margin-top: 2px;
}

.no-ticket {
    color: #7b8491;
    font-size: clamp(13px, 1vw, 16px);
    font-weight: bold;
    padding: 7px 0;
}

.priority {
    display: inline-block;
    margin-top: 3px;
    color: #875500;
    background: #fff0c7;
    padding: 3px 7px;
    border-radius: 3px;
    font-size: 10px;
    font-weight: bold;
    width: fit-content;
}

/* WAITING QUEUE */

.waiting-area {
    min-height: 0;
    display: flex;
    flex-direction: column;
    background: white;
    border: 1px solid var(--border);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 5px rgba(0,0,0,.04);
}

.waiting-header {
    flex-shrink: 0;
    padding: 10px 16px;
    background: var(--red);
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.waiting-header h2 {
    margin: 0;
    font-size: clamp(17px, 1.4vw, 23px);
    font-weight: 900;
    letter-spacing: 1px;
}

.total-waiting {
    background: white;
    color: var(--red);
    padding: 5px 12px;
    border-radius: 4px;
    font-weight: 900;
    font-size: 14px;
}

.waiting-content {
    flex: 1;
    min-height: 0;
    display: grid;
    grid-template-columns: 2fr 1fr;
    overflow: hidden;
}

.waiting-service {
    min-width: 0;
    min-height: 0;
    display: flex;
    flex-direction: column;
    border-right: 1px solid var(--border);
}

.waiting-service:last-child {
    border-right: none;
}

.waiting-service-title {
    flex-shrink: 0;
    padding: 9px 13px;
    background: #edf1f6;
    color: var(--navy);
    font-size: clamp(13px, 1vw, 17px);
    font-weight: 900;
    display: flex;
    justify-content: space-between;
}

.waiting-list {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    padding: 5px 10px;
    scrollbar-width: thin;
    scrollbar-color: #aab4c1 #edf0f4;
}

.waiting-list::-webkit-scrollbar {
    width: 9px;
}

.waiting-list::-webkit-scrollbar-track {
    background: #edf0f4;
}

.waiting-list::-webkit-scrollbar-thumb {
    background: #aab4c1;
    border-radius: 10px;
}

.waiting-item {
    display: grid;
    grid-template-columns: 38px minmax(0, 1fr) auto;
    align-items: center;
    gap: 10px;
    padding: 8px 7px;
    border-bottom: 1px solid #edf0f3;
    min-height: 49px;
}

.waiting-item.priority-item {
    background: #fffaf0;
    border-left: 4px solid #e2a52b;
}

.position {
    width: 29px;
    height: 29px;
    border-radius: 4px;
    background: #e9edf3;
    color: var(--navy);
    display: grid;
    place-items: center;
    font-weight: 900;
    font-size: 13px;
}

.waiting-ticket {
    font-size: clamp(17px, 1.4vw, 23px);
    font-weight: 900;
    color: var(--red);
}

.waiting-note {
    color: var(--gray);
    font-size: 10px;
    text-align: right;
}

.empty-queue {
    padding: 18px;
    text-align: center;
    color: var(--gray);
    font-size: 13px;
    font-weight: bold;
}

/* FOOTER */

.footer {
    height: 34px;
    flex-shrink: 0;
    background: var(--navy);
    color: white;
    padding: 0 2.5%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 11px;
}

/* SMALLER TV / SCREEN */

@media (max-height: 700px) {
    .header {
        height: 78px;
        padding-top: 7px;
        padding-bottom: 7px;
    }

    .brand img {
        width: 48px;
        height: 48px;
    }

    .main {
        padding-top: 9px;
        padding-bottom: 9px;
        gap: 8px;
    }

    .counter-card {
        min-height: 83px;
        padding: 7px 10px;
    }

    .counter-grid {
        gap: 7px;
        padding: 8px;
    }

    .group-title {
        padding: 7px 12px;
    }

    .waiting-header {
        padding: 7px 13px;
    }

    .waiting-service-title {
        padding: 6px 10px;
    }

    .waiting-item {
        min-height: 39px;
        padding: 5px;
    }

    .footer {
        height: 27px;
    }
}

@media (max-width: 800px) {
    .header-center {
        display: none;
    }

    .counter-groups {
        grid-template-columns: 1fr 1fr;
    }

    .waiting-content {
        grid-template-columns: 1fr 1fr;
    }
}
</style>
</head>

<body>

<header class="header">

    <div class="brand">
        <img src="../assets/img/icon.png" alt="QueueFlow">

        <div>
            <h1>QUEUEFLOW</h1>
            <p>RMMC-MI FINANCE OFFICE</p>
        </div>
    </div>

    <div class="header-center">
        LIVE QUEUE MONITOR
        <span>PLEASE WAIT FOR YOUR TICKET NUMBER</span>

        <button type="button" id="voiceButton" class="voice-button">
            ENABLE VOICE ANNOUNCEMENTS
        </button>
    </div>

    <div class="clock">
        <strong id="clock">--:--:--</strong>
        <span id="date">Loading...</span>
    </div>

</header>

<main class="main">

    <section class="counter-area">

        <div class="section-heading">
            <h2>Counter Status</h2>
            <small id="lastUpdated">Connecting to server...</small>
        </div>

        <div id="counterGroups" class="counter-groups">

            <div class="counter-group">
                <div class="group-title">CASHIER</div>
                <div class="counter-grid">Loading...</div>
            </div>

            <div class="counter-group">
                <div class="group-title">RECEIVABLES</div>
                <div class="counter-grid">Loading...</div>
            </div>

        </div>

    </section>

    <section class="waiting-area">

        <div class="waiting-header">
            <h2>WAITING QUEUE</h2>
            <span class="total-waiting" id="totalWaiting">0 WAITING</span>
        </div>

        <div class="waiting-content" id="waitingContent">

            <div class="waiting-service">
                <div class="waiting-service-title">CASHIER</div>
                <div class="empty-queue">Loading tickets...</div>
            </div>

            <div class="waiting-service">
                <div class="waiting-service-title">RECEIVABLES</div>
                <div class="empty-queue">Loading tickets...</div>
            </div>

        </div>

    </section>

</main>

<footer class="footer">
    <span>QUEUEFLOW | RMMC-MI FINANCE MANAGEMENT SYSTEM</span>
    <span>PLEASE PROCEED TO YOUR ASSIGNED COUNTER WHEN CALLED</span>
</footer>

<script>
(() => {

    const counterGroups = document.getElementById('counterGroups');
    const waitingContent = document.getElementById('waitingContent');
    const voiceButton = document.getElementById('voiceButton');

    let requestRunning = false;
    let initialServingSnapshotLoaded = false;
    let announcementRunning = false;
    let voiceEnabled = false;

    const announcementQueue = [];

    const speechSupported =
        'speechSynthesis' in window &&
        'SpeechSynthesisUtterance' in window;

    /*
    |--------------------------------------------------------------------------
    | PERSISTENT ANNOUNCEMENT HISTORY
    |--------------------------------------------------------------------------
    */

    const STORAGE_KEY = 'qflow_announced_call_events_v2';

    let announcedCalls = new Set();

    try {

        const savedCalls = JSON.parse(
            localStorage.getItem(STORAGE_KEY) || '[]'
        );

        if (Array.isArray(savedCalls)) {
            announcedCalls = new Set(savedCalls);
        }

    } catch (error) {
        console.warn('Could not load announcement history:', error);
    }

    function saveAnnouncedCalls() {

        try {

            const history = Array.from(announcedCalls).slice(-500);

            announcedCalls = new Set(history);

            localStorage.setItem(
                STORAGE_KEY,
                JSON.stringify(history)
            );

        } catch (error) {
            console.warn('Could not save announcement history:', error);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | VOICE ACTIVATION
    |--------------------------------------------------------------------------
    */

    if (!speechSupported) {

        voiceButton.textContent = 'VOICE NOT SUPPORTED';
        voiceButton.disabled = true;

    } else {

        voiceButton.addEventListener('click', () => {

            voiceEnabled = true;

            window.speechSynthesis.resume();

            voiceButton.textContent = 'VOICE ANNOUNCEMENTS ENABLED';
            voiceButton.classList.add('enabled');
            voiceButton.disabled = true;

            processAnnouncementQueue();

        });

    }

    /*
    |--------------------------------------------------------------------------
    | SPEAK ONE ANNOUNCEMENT
    |--------------------------------------------------------------------------
    */

    function speakOnce(message) {

        return new Promise(resolve => {

            if (!speechSupported || !voiceEnabled) {
                resolve();
                return;
            }

            const utterance =
                new SpeechSynthesisUtterance(message);

            utterance.lang = 'en-PH';
            utterance.rate = 0.9;
            utterance.pitch = 1;
            utterance.volume = 1;

            let finished = false;

            function finish() {

                if (finished) return;

                finished = true;
                resolve();
            }

            utterance.onend = finish;

            utterance.onerror = error => {
                console.error('Speech error:', error);
                finish();
            };

            window.speechSynthesis.speak(utterance);

        });
    }

    /*
    |--------------------------------------------------------------------------
    | SPEAK EACH TICKET TWICE
    |--------------------------------------------------------------------------
    */

    async function speakQueueAnnouncement(ticket) {

        const ticketType = ticket.is_priority
            ? 'priority'
            : 'regular';

        const counterName =
            ticket.counter_name || 'your assigned counter';

        const message =
            `Now serving ${ticketType} ticket ` +
            `${ticket.ticket_number}. ` +
            `Please proceed to ${counterName}.`;

        // First announcement.
        await speakOnce(message);

        // Pause before repeating.
        await new Promise(resolve => setTimeout(resolve, 700));

        // Second announcement.
        await speakOnce(message);
    }

    /*
    |--------------------------------------------------------------------------
    | ANNOUNCEMENT QUEUE
    |--------------------------------------------------------------------------
    */

    async function processAnnouncementQueue() {

        if (announcementRunning || !voiceEnabled) {
            return;
        }

        announcementRunning = true;

        while (announcementQueue.length > 0 && voiceEnabled) {

            const ticket = announcementQueue.shift();

            try {

                await speakQueueAnnouncement(ticket);

            } catch (error) {

                console.error('Announcement error:', error);

            }
        }

        announcementRunning = false;
    }

    /*
    |--------------------------------------------------------------------------
    | CALL EVENT IDENTIFICATION
    |--------------------------------------------------------------------------
    */

    function getCallKey(ticket) {

        const queueId = ticket.queue_id;

        if (queueId === undefined || queueId === null) {
            return null;
        }

        /*
         * called_at must change whenever staff calls a ticket again.
         * This makes a new call distinguishable from an old call.
         */

        if (
            ticket.called_at !== undefined &&
            ticket.called_at !== null &&
            String(ticket.called_at).trim() !== ''
        ) {

            return `${queueId}:${ticket.called_at}`;

        }

        /*
         * Fallback:
         * Prevents duplicate announcements for the same queue ID.
         * Does not support announcing a later recall of that ticket.
         */

        return String(queueId);
    }

    /*
    |--------------------------------------------------------------------------
    | DETECT NEWLY CALLED TICKETS
    |--------------------------------------------------------------------------
    */

    function detectNewServingTickets(services) {

        const currentServingTickets = [];

        services.forEach(service => {

            (service.serving || []).forEach(ticket => {

                const callKey = getCallKey(ticket);

                if (!callKey) {

                    console.warn(
                        'Serving ticket has no queue_id:',
                        ticket
                    );

                    return;
                }

                currentServingTickets.push({
                    key: callKey,
                    ticket: ticket
                });

            });

        });

        /*
         * FIRST LOAD:
         * Record existing serving tickets without announcing them.
         */

        if (!initialServingSnapshotLoaded) {

            currentServingTickets.forEach(item => {
                announcedCalls.add(item.key);
            });

            saveAnnouncedCalls();

            initialServingSnapshotLoaded = true;

            return;
        }

        /*
         * SUBSEQUENT LOADS:
         * Announce only unseen call events.
         */

        currentServingTickets.forEach(item => {

            if (announcedCalls.has(item.key)) {
                return;
            }

            // Save before speaking to prevent duplicate announcements.
            announcedCalls.add(item.key);

            saveAnnouncedCalls();

            announcementQueue.push(item.ticket);

        });

        processAnnouncementQueue();
    }

    /*
    |--------------------------------------------------------------------------
    | HTML ESCAPING
    |--------------------------------------------------------------------------
    */

    function escapeHtml(value) {

        return String(value ?? '').replace(/[&<>"']/g, char => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        })[char]);

    }

    /*
    |--------------------------------------------------------------------------
    | CLOCK
    |--------------------------------------------------------------------------
    */

    function updateClock() {

        const now = new Date();

        document.getElementById('clock').textContent =
            now.toLocaleTimeString('en-PH', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            });

        document.getElementById('date').textContent =
            now.toLocaleDateString('en-PH', {
                weekday: 'long',
                month: 'long',
                day: 'numeric',
                year: 'numeric'
            });

    }

    /*
    |--------------------------------------------------------------------------
    | COUNTER STATUS
    |--------------------------------------------------------------------------
    */

    function getStatusClass(status) {

        switch (String(status || '').toLowerCase()) {

            case 'available':
                return 'available';

            case 'serving':
                return 'serving';

            case 'busy':
                return 'busy';

            case 'unavailable':
                return 'unavailable';

            default:
                return 'not-configured';
        }
    }

    function renderCounter(counter) {

        const status = counter.status || 'Not configured';
        const statusClass = getStatusClass(status);
        const ticket = (counter.serving || [])[0];

        return `
            <article class="counter-card ${statusClass}">

                <div class="counter-top">

                    <span class="counter-name">
                        ${escapeHtml(counter.counter_name)}
                    </span>

                    <span class="status ${statusClass}">
                        ${escapeHtml(status)}
                    </span>

                </div>

                <div>

                    ${
                        ticket
                        ? `
                            <div class="ticket-label">NOW SERVING</div>

                            <div class="ticket-number">
                                ${escapeHtml(ticket.ticket_number)}
                            </div>

                            ${
                                ticket.is_priority
                                ? `<span class="priority">★ PRIORITY</span>`
                                : ''
                            }
                        `
                        : `
                            <div class="ticket-label">NOW SERVING</div>
                            <div class="no-ticket">NO ACTIVE TICKET</div>
                        `
                    }

                </div>

            </article>
        `;
    }

    /*
    |--------------------------------------------------------------------------
    | SERVICE COUNTER GROUPS
    |--------------------------------------------------------------------------
    */

    function renderGroup(service) {

        const counters = service.counters || [];

        const isCashier =
            String(service.service_name)
                .toLowerCase()
                .includes('cashier');

        const gridClass = isCashier
            ? 'counter-grid'
            : 'counter-grid receivables-grid';

        return `
            <section class="counter-group">

                <div class="group-title">
                    ${escapeHtml(service.service_name)}
                    <span>${counters.length} COUNTERS</span>
                </div>

                <div class="${gridClass}">

                    ${
                        counters.length
                        ? counters.map(renderCounter).join('')
                        : '<div class="empty-queue">No active counters</div>'
                    }

                </div>

            </section>
        `;
    }

    /*
    |--------------------------------------------------------------------------
    | WAITING QUEUE DISPLAY
    |--------------------------------------------------------------------------
    */

    function renderWaitingService(service) {

        const tickets = service.waiting || [];

        return `
            <section class="waiting-service">

                <div class="waiting-service-title">
                    <span>${escapeHtml(service.service_name)}</span>
                    <span>${tickets.length} WAITING</span>
                </div>

                <div class="waiting-list">

                    ${
                        tickets.length
                        ? tickets.map((ticket, index) => `

                            <div class="waiting-item ${
                                ticket.is_priority ? 'priority-item' : ''
                            }">

                                <span class="position">
                                    ${index + 1}
                                </span>

                                <div>

                                    <div class="waiting-ticket">
                                        ${escapeHtml(ticket.ticket_number)}
                                    </div>

                                    ${
                                        ticket.is_priority
                                        ? '<span class="priority">★ PRIORITY</span>'
                                        : ''
                                    }

                                </div>

                                <span class="waiting-note">
                                    WAITING
                                </span>

                            </div>

                        `).join('')
                        : '<div class="empty-queue">NO WAITING TICKETS</div>'
                    }

                </div>

            </section>
        `;
    }

    /*
    |--------------------------------------------------------------------------
    | REFRESH LIVE QUEUE
    |--------------------------------------------------------------------------
    */

    async function refreshQueue() {

        if (requestRunning) {
            return;
        }

        requestRunning = true;

        try {

            const response = await fetch('queue-monitor-data.php', {
                cache: 'no-store',
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error('Unable to load queue information.');
            }

            const data = await response.json();

            if (!data.success || !Array.isArray(data.services)) {
                throw new Error('Invalid queue information.');
            }

            const services = data.services;

            detectNewServingTickets(services);

            const cashier = services.find(service =>
                String(service.service_name)
                    .toLowerCase()
                    .includes('cashier')
            );

            const receivables = services.find(service =>
                String(service.service_name)
                    .toLowerCase()
                    .includes('receivables')
            );

            const orderedServices = [
                cashier,
                receivables
            ].filter(Boolean);

            counterGroups.innerHTML = orderedServices.length
                ? orderedServices.map(renderGroup).join('')
                : '<div class="empty-queue">No active services found.</div>';

            waitingContent.innerHTML = orderedServices.length
                ? orderedServices.map(renderWaitingService).join('')
                : '<div class="empty-queue">No active services found.</div>';

            const total = services.reduce((sum, service) => {
                return sum + (service.waiting || []).length;
            }, 0);

            document.getElementById('totalWaiting').textContent =
                `${total} WAITING`;

            document.getElementById('lastUpdated').textContent =
                'LAST UPDATED: ' +
                new Date().toLocaleTimeString('en-PH');

        } catch (error) {

            console.error(error);

            document.getElementById('lastUpdated').textContent =
                'CONNECTION ERROR - RETRYING';

        } finally {

            requestRunning = false;

        }
    }

    /*
    |--------------------------------------------------------------------------
    | START DISPLAY
    |--------------------------------------------------------------------------
    */

    updateClock();
    refreshQueue();

    setInterval(updateClock, 1000);
    setInterval(refreshQueue, 3000);

})();
</script>

</body>
</html>
