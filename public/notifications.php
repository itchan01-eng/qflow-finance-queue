<?php
require_once dirname(__DIR__) . '/includes/functions.php';

qflow_start_session();
qflow_require_login();

date_default_timezone_set('Asia/Manila');

$pdo = require dirname(__DIR__) . '/config/database.php';

$accountId = (string) ($_SESSION['account_id'] ?? '');
$error = '';
$success = '';

if ($accountId === '') {
    header('Location: login.php');
    exit;
}

if (!function_exists('notification_escape')) {
    function notification_escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('notification_icon')) {
    function notification_icon(string $type): string
    {
        return match ($type) {
            'queue_called' => '🔔',
            'queue_approaching' => '⏳',
            'queue_completed' => '✓',
            'queue_skipped', 'queue_no_show' => '⚠',
            'queue_cancelled' => '✕',
            default => '●',
        };
    }
}

// Verify that the logged-in account is active.
$accountStatement = $pdo->prepare(
    "SELECT account_id
     FROM users
     WHERE account_id = :account_id
       AND account_status = 'active'
     LIMIT 1"
);

$accountStatement->execute(['account_id' => $accountId]);
$accountId = $accountStatement->fetchColumn();

if ($accountId === false) {
    $_SESSION = [];
    session_destroy();

    header('Location: login.php');
    exit;
}

// Handle mark-all-as-read.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!qflow_verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif (($_POST['action'] ?? '') === 'mark_all_read') {
        $markRead = $pdo->prepare(
            "UPDATE notifications
             SET is_read = 1
             WHERE account_id = :account_id
               AND is_read = 0"
        );

        $markRead->execute(['account_id' => $accountId]);

        header('Location: notifications.php?read=1');
        exit;
    } else {
        $error = 'Invalid request.';
    }
}

if (isset($_GET['read'])) {
    $success = 'All notifications marked as read.';
}

// Load the latest 100 notifications for this account.
$notificationStatement = $pdo->prepare(
    "SELECT notification_id, queue_id, notification_type,
            title, message, is_read, created_at
     FROM notifications
     WHERE account_id = :account_id
     ORDER BY created_at DESC, notification_id DESC
     LIMIT 100"
);

$notificationStatement->execute(['account_id' => $accountId]);

$notifications = $notificationStatement->fetchAll(PDO::FETCH_ASSOC);

$unreadCount = 0;

foreach ($notifications as $notification) {
    if ((int) $notification['is_read'] === 0) {
        $unreadCount++;
    }
}

$pageTitle = 'Notifications';

require dirname(__DIR__) . '/includes/header.php';
require dirname(__DIR__) . '/includes/sidebar.php';
?>

<main class="main-content">

    <header class="page-header">
        <div>
            <p class="eyebrow">STUDENT PORTAL</p>
            <h1>Notifications</h1>
            <p class="page-description">
                Stay updated on your queue and finance transactions.
            </p>
        </div>
    </header>

    <div class="notification-enable-bar">
        <div class="notification-enable-info">
            <div class="notification-enable-icon" aria-hidden="true">
                ♧
            </div>

            <div>
                <h3>Never Miss a Queue Update</h3>
                <p id="notification-button-description">
                    Enable browser notifications to know when your queue is called.
                </p>
            </div>
        </div>

        <button
            type="button"
            id="enableNotificationsButton"
            class="notification-enable-button">

            <span aria-hidden="true">🔔</span>
            <span id="enableNotificationsButtonText">
                Enable Notifications
            </span>
        </button>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error" role="alert">
            <?= notification_escape($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success" role="status">
            <?= notification_escape($success) ?>
        </div>
    <?php endif; ?>

    <section class="section-block">

        <div class="section-heading">
            <div>
                <h2>Your Updates</h2>

                <p>
                    <span id="notification-unread-count">
                        <?= $unreadCount ?>
                    </span>
                    unread notification<span id="notification-plural"><?= $unreadCount === 1 ? '' : 's' ?></span>
                </p>

                <p id="notification-live-status"
                   class="muted-label"
                   aria-live="polite">
                    Checking notification status...
                </p>
            </div>

            <form method="POST"
                  action="notifications.php"
                  id="mark-all-read-form"
                  <?= $unreadCount === 0 ? 'hidden' : '' ?>>

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= notification_escape(qflow_csrf_token()) ?>">

                <input type="hidden"
                       name="action"
                       value="mark_all_read">

                <button type="submit" class="button button-secondary">
                    Mark All as Read
                </button>
            </form>
        </div>

        <div class="notification-list-frame">

            <div id="notification-list"
                 aria-live="polite"
                 aria-relevant="additions text">

                <?php if (!$notifications): ?>

                    <div class="empty-state">
                        <h3>You're all caught up!</h3>

                        <p>
                            Your queue notifications will appear here when
                            there are updates to your ticket.
                        </p>

                        <a href="my-queue.php" class="button button-primary">
                            View My Queue
                        </a>
                    </div>

                <?php else: ?>

                    <?php foreach ($notifications as $notification): ?>

                        <?php
                        $isUnread = (int) $notification['is_read'] === 0;
                        $notificationType = (string) $notification['notification_type'];
                        ?>

                        <article
                            class="notification-item<?= $isUnread ? ' notification-unread' : '' ?>"
                            data-notification-id="<?= (int) $notification['notification_id'] ?>"
                            data-type="<?= notification_escape($notificationType) ?>">

                            <div class="notification-icon" aria-hidden="true">
                                <?= notification_escape(notification_icon($notificationType)) ?>
                            </div>

                            <div class="notification-content">

                                <div class="notification-heading">

                                    <h3>
                                        <?= notification_escape($notification['title']) ?>
                                    </h3>

                                    <?php if ($isUnread): ?>
                                        <span class="status-pill status-waiting">
                                            New
                                        </span>
                                    <?php endif; ?>

                                </div>

                                <p>
                                    <?= nl2br(notification_escape($notification['message'])) ?>
                                </p>

                                <small class="muted-label">
                                    <?= notification_escape(
                                        date(
                                            'M d, Y · h:i A',
                                            strtotime($notification['created_at'])
                                        )
                                    ) ?>
                                </small>

                                <?php if (!empty($notification['queue_id'])): ?>

                                    <div class="notification-actions">
                                        <a href="my-queue.php" class="text-link">
                                            View My Queue
                                        </a>
                                    </div>

                                <?php endif; ?>

                            </div>
                        </article>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>
        </div>

    </section>

    <footer class="page-footer">
        <span>QueueFlow — RMMCMI Finance Queue Management System</span>
        <span><?= date('Y') ?></span>
    </footer>

</main>


<!-- In-app notification popup container -->
<div id="queueflow-toast-container"
     aria-live="polite"
     aria-relevant="additions"
     style="
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 99999;
        display: flex;
        flex-direction: column;
        gap: 10px;
        width: min(360px, calc(100% - 40px));
        pointer-events: none;
     ">
</div>


<!-- Browser notification permission popup -->
<div id="notification-consent"
     class="notification-consent"
     role="dialog"
     aria-labelledby="notification-consent-title"
     aria-describedby="notification-consent-description"
     hidden>

    <div class="consent-icon" aria-hidden="true">
        🔔
    </div>

    <div class="consent-content">

        <h3 id="notification-consent-title">
            Stay updated with QueueFlow
        </h3>

        <p id="notification-consent-description">
            Allow notifications to know when your queue is called
            or your ticket status changes.
        </p>

        <div id="notification-consent-status"
             class="consent-status"
             role="status"
             aria-live="polite">
        </div>

        <div class="consent-actions">

            <button type="button"
                    id="allowQueueNotifications"
                    class="consent-allow">
                Allow Notifications
            </button>

            <button type="button"
                    id="dismissQueueNotifications"
                    class="consent-dismiss">
                Not now
            </button>

        </div>
    </div>

    <button type="button"
            id="closeQueueNotificationPopup"
            class="consent-close"
            aria-label="Close notification prompt">
        &times;
    </button>

</div>


<!-- Firebase compat SDK -->
<script src="https://www.gstatic.com/firebasejs/10.13.2/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.13.2/firebase-messaging-compat.js"></script>


<style>
@keyframes queueflowToastIn {
    from {
        opacity: 0;
        transform: translateX(30px);
    }

    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.queueflow-toast {
    background: #ffffff;
    color: #1f2937;
    padding: 16px;
    border-radius: 12px;
    box-shadow: 0 5px 25px rgba(0, 0, 0, 0.18);
    border-left: 4px solid #2563eb;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    animation: queueflowToastIn 0.3s ease;
    max-width: 100%;
    pointer-events: auto;
    cursor: pointer;
}

.queueflow-toast-icon {
    font-size: 22px;
    flex-shrink: 0;
}

.queueflow-toast-content {
    flex: 1;
    min-width: 0;
}

.queueflow-toast-title {
    display: block;
    font-size: 14px;
    margin-bottom: 5px;
}

.queueflow-toast-message {
    font-size: 13px;
    line-height: 1.5;
    margin: 0;
    color: #4b5563;
    overflow-wrap: anywhere;
}

.queueflow-toast-close {
    border: none;
    background: transparent;
    color: #6b7280;
    font-size: 22px;
    cursor: pointer;
    padding: 0;
    line-height: 1;
    flex-shrink: 0;
}

@media (max-width: 480px) {
    #queueflow-toast-container {
        top: 10px !important;
        right: 10px !important;
        width: calc(100% - 20px) !important;
    }
}
</style>


<script>
(() => {
    'use strict';

    const list = document.getElementById('notification-list');
    const countElement = document.getElementById('notification-unread-count');
    const pluralElement = document.getElementById('notification-plural');
    const liveStatus = document.getElementById('notification-live-status');
    const markAllForm = document.getElementById('mark-all-read-form');

    const consentPopup = document.getElementById('notification-consent');
    const allowButton = document.getElementById('allowQueueNotifications');
    const dismissButton = document.getElementById('dismissQueueNotifications');
    const closeButton = document.getElementById('closeQueueNotificationPopup');
    const consentStatus = document.getElementById('notification-consent-status');

    const toastContainer = document.getElementById('queueflow-toast-container');

    const consentStorageKey = 'queueflow_notification_dismissed';

    if (!list || !countElement || !liveStatus) {
        return;
    }

    let isPolling = false;

    // IDs already displayed on initial page load.
    let previousIds = new Set(
        [...list.querySelectorAll('[data-notification-id]')]
            .map(item => String(item.dataset.notificationId))
    );

    const icons = {
        queue_called: '🔔',
        queue_approaching: '⏳',
        queue_completed: '✓',
        queue_skipped: '⚠',
        queue_no_show: '⚠',
        queue_cancelled: '✕'
    };

    // =========================================
    // In-page notification popup
    // Works even if browser permission is denied.
    // =========================================

    function showInPageNotification(item) {
        if (!toastContainer) {
            return;
        }

        const toast = document.createElement('div');
        toast.className = 'queueflow-toast';

        const icon = document.createElement('div');
        icon.className = 'queueflow-toast-icon';
        icon.textContent = icons[item.notification_type] || '●';

        const content = document.createElement('div');
        content.className = 'queueflow-toast-content';

        const title = document.createElement('strong');
        title.className = 'queueflow-toast-title';
        title.textContent = item.title || 'QueueFlow Update';

        const message = document.createElement('p');
        message.className = 'queueflow-toast-message';
        message.textContent = item.message || 'Your queue has been updated.';

        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'queueflow-toast-close';
        close.textContent = '×';
        close.setAttribute('aria-label', 'Dismiss notification');

        content.append(title, message);
        toast.append(icon, content, close);

        close.addEventListener('click', (event) => {
            event.stopPropagation();
            toast.remove();
        });

        toast.addEventListener('click', () => {
            if (item.queue_id) {
                window.location.href = 'my-queue.php';
            }
        });

        toastContainer.appendChild(toast);

        window.setTimeout(() => {
            toast.remove();
        }, 8000);

        // Keep the popup container from becoming overcrowded.
        while (toastContainer.children.length > 5) {
            toastContainer.firstElementChild.remove();
        }
    }

    // =========================================
    // Firebase browser push registration
    // =========================================

    const enableNotificationsButton = document.getElementById(
        'enableNotificationsButton'
    );

    const enableNotificationsButtonText = document.getElementById(
        'enableNotificationsButtonText'
    );

    const notificationButtonDescription = document.getElementById(
        'notification-button-description'
    );

    const firebaseConfig = {
        apiKey: 'AIzaSyAQIMZ0dusBA4HwWNrKaPOP6mIbaB4gvbc',
        authDomain: 'queueflow-48aa4.firebaseapp.com',
        projectId: 'queueflow-48aa4',
        storageBucket: 'queueflow-48aa4.firebasestorage.app',
        messagingSenderId: '795833520143',
        appId: '1:795833520143:web:4db256f49d7aca56dc137a'
    };

    const firebaseVapidKey =
        'BGHZ_zdwtwYnn4_vBTvd8xdLZGahUzE0wfReCNviY3G-6aWbJQEZ7Q7ixH_QcCPNvl3OJVi5kVIBcV_kY3bXn2M';

    const csrfToken = <?= json_encode(
        qflow_csrf_token(),
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
    ) ?>;

    let pushRegistrationSaved = false;
    let pushRegistrationInProgress = false;
    let messagingInstance = null;
    let serviceWorkerRegistration = null;

    function isBrowserNotificationSupported() {
        return 'Notification' in window &&
            'serviceWorker' in navigator &&
            window.isSecureContext;
    }

    function updateNotificationButton() {
        if (!enableNotificationsButton || !enableNotificationsButtonText) {
            return;
        }

        enableNotificationsButton.classList.remove(
            'is-enabled',
            'is-blocked',
            'is-unsupported'
        );

        if (!('Notification' in window) || !('serviceWorker' in navigator)) {
            enableNotificationsButton.classList.add('is-unsupported');
            enableNotificationsButtonText.textContent =
                'Notifications Not Supported';
            enableNotificationsButton.disabled = true;

            if (notificationButtonDescription) {
                notificationButtonDescription.textContent =
                    'This browser does not support the required notification features.';
            }

            return;
        }

        if (!window.isSecureContext) {
            enableNotificationsButton.classList.add('is-unsupported');
            enableNotificationsButtonText.textContent = 'HTTPS Required';
            enableNotificationsButton.disabled = true;

            if (notificationButtonDescription) {
                notificationButtonDescription.textContent =
                    'Open QueueFlow using its HTTPS website address.';
            }

            return;
        }

        if (pushRegistrationSaved) {
            enableNotificationsButton.classList.add('is-enabled');
            enableNotificationsButtonText.textContent = 'Notifications Enabled';
            enableNotificationsButton.disabled = true;

            if (notificationButtonDescription) {
                notificationButtonDescription.textContent =
                    'This browser is registered to receive QueueFlow push notifications.';
            }

            return;
        }

        if (Notification.permission === 'denied') {
            enableNotificationsButton.classList.add('is-blocked');
            enableNotificationsButtonText.textContent =
                'Enable in Browser Settings';
            enableNotificationsButton.disabled = true;

            if (notificationButtonDescription) {
                notificationButtonDescription.textContent =
                    'Browser notifications are blocked. QueueFlow will still show in-page notifications while this page is open.';
            }

            return;
        }

        enableNotificationsButton.disabled = pushRegistrationInProgress;

        enableNotificationsButtonText.textContent =
            pushRegistrationInProgress
                ? 'Setting Up...'
                : Notification.permission === 'granted'
                    ? 'Finish Notification Setup'
                    : 'Enable Notifications';

        if (notificationButtonDescription) {
            notificationButtonDescription.textContent =
                Notification.permission === 'granted'
                    ? 'Browser permission is granted, but this browser still needs to be registered with QueueFlow.'
                    : 'Allow browser notifications to know when your queue is called.';
        }
    }

    function showConsentPopup() {
        if (!consentPopup || !allowButton) {
            return;
        }

        if (pushRegistrationSaved) {
            consentPopup.hidden = true;
            consentPopup.classList.remove('is-visible');
            consentPopup.setAttribute('aria-hidden', 'true');
            return;
        }

        if (!('Notification' in window) || !('serviceWorker' in navigator)) {
            consentPopup.hidden = true;
            return;
        }

        if (Notification.permission === 'denied') {
            consentPopup.hidden = false;

            if (consentStatus) {
                consentStatus.textContent =
                    'Browser notifications are blocked. QueueFlow in-page notifications will still work while this page is open.';
            }

            allowButton.disabled = true;
            return;
        }

        allowButton.disabled = pushRegistrationInProgress;

        allowButton.textContent =
            Notification.permission === 'granted'
                ? 'Finish Setup'
                : 'Allow Notifications';

        if (Notification.permission === 'granted') {
            if (consentStatus) {
                consentStatus.textContent =
                    'Permission is already granted. Complete the QueueFlow registration to receive push updates.';
            }

            consentPopup.hidden = false;
            return;
        }

        try {
            if (sessionStorage.getItem(consentStorageKey) === '1') {
                consentPopup.hidden = true;
                return;
            }
        } catch (error) {
            // Continue without session storage.
        }

        consentPopup.hidden = false;
    }

    function dismissConsentPopup() {
        if (consentPopup) {
            consentPopup.hidden = true;
        }

        try {
            sessionStorage.setItem(consentStorageKey, '1');
        } catch (error) {
            // Dismissal still works if storage is unavailable.
        }
    }

    if (dismissButton) {
        dismissButton.addEventListener('click', dismissConsentPopup);
    }

    if (closeButton) {
        closeButton.addEventListener('click', dismissConsentPopup);
    }

    async function registerQueueFlowPush() {
        if (pushRegistrationInProgress) {
            return;
        }

        if (!isBrowserNotificationSupported()) {
            throw new Error(
                'Notifications require a supported browser and a secure HTTPS connection.'
            );
        }

        if (Notification.permission === 'denied') {
            throw new Error(
                'Notifications are blocked. Enable them in your browser site settings.'
            );
        }

        pushRegistrationInProgress = true;
        updateNotificationButton();

        if (consentStatus) {
            consentStatus.textContent = 'Setting up QueueFlow notifications...';
        }

        try {
            if (Notification.permission !== 'granted') {
                const permission = await Notification.requestPermission();

                if (permission !== 'granted') {
                    throw new Error(
                        permission === 'denied'
                            ? 'Permission was denied. Enable notifications in your browser settings.'
                            : 'Notification permission was not granted.'
                    );
                }
            }

            if (!window.firebase) {
                throw new Error(
                    'Firebase could not be loaded. Refresh the page and try again.'
                );
            }

            if (!firebase.apps.length) {
                firebase.initializeApp(firebaseConfig);
            }

            if (
                !firebase.messaging ||
                !(await firebase.messaging.isSupported())
            ) {
                throw new Error(
                    'Firebase messaging is not supported by this browser.'
                );
            }

            messagingInstance = firebase.messaging();

            serviceWorkerRegistration = await navigator.serviceWorker.register(
                '/firebase-messaging-sw.js',
                { scope: '/' }
            );

            await navigator.serviceWorker.ready;

            if (!serviceWorkerRegistration.active) {
                throw new Error(
                    'Service worker is not active. Please refresh and try again.'
                );
            }

            const token = await messagingInstance.getToken({
                vapidKey: firebaseVapidKey,
                serviceWorkerRegistration: serviceWorkerRegistration
            });

            if (!token) {
                throw new Error(
                    'Firebase did not return a registration token. Please try again.'
                );
            }

            const response = await fetch('/api/save-push-token.php', {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ token: token })
            });

            let result;

            try {
                result = await response.json();
            } catch (error) {
                throw new Error(
                    'The server returned an invalid response while saving your token.'
                );
            }

            if (!response.ok || !result || result.success !== true) {
                throw new Error(
                    result && result.message
                        ? result.message
                        : 'QueueFlow could not save this browser registration.'
                );
            }

            pushRegistrationSaved = true;

            try {
                sessionStorage.setItem(consentStorageKey, '1');
            } catch (error) {
                // Registration succeeded even if session storage is unavailable.
            }

            if (consentPopup) {
                consentPopup.hidden = true;
                consentPopup.classList.remove('is-visible');
                consentPopup.setAttribute('aria-hidden', 'true');
            }

            if (consentStatus) {
                consentStatus.textContent = 'QueueFlow notifications are ready.';
            }

            if (notificationButtonDescription) {
                notificationButtonDescription.textContent =
                    'This browser is registered to receive QueueFlow push notifications.';
            }

        } finally {
            pushRegistrationInProgress = false;
            updateNotificationButton();

            if (!pushRegistrationSaved) {
                showConsentPopup();
            }
        }
    }

    async function handleEnableNotifications() {
        try {
            await registerQueueFlowPush();
        } catch (error) {
            console.error('QueueFlow push registration error:', error);

            const message = error instanceof Error
                ? error.message
                : 'Could not set up notifications. Please try again.';

            if (consentStatus) {
                consentStatus.textContent = message;
            }

            if (notificationButtonDescription) {
                notificationButtonDescription.textContent = message;
            }
        }
    }

    if (allowButton) {
        allowButton.addEventListener('click', handleEnableNotifications);
    }

    if (enableNotificationsButton) {
        enableNotificationsButton.addEventListener(
            'click',
            handleEnableNotifications
        );
    }

    updateNotificationButton();

    // Automatically refresh Firebase token only when permission is granted.
    // Do not stop in-page notifications when permission is denied.
    if (
        'Notification' in window &&
        Notification.permission === 'granted'
    ) {
        registerQueueFlowPush().catch(error => {
            console.error('QueueFlow push token refresh failed:', error);
            showConsentPopup();
        });
    } else {
        showConsentPopup();
    }

    // =========================================
    // Browser notification
    // =========================================

    function showBrowserNotification(item) {
        if (!('Notification' in window)) {
            return;
        }

        if (Notification.permission !== 'granted') {
            return;
        }

        try {
            const notification = new Notification(
                item.title || 'QueueFlow Update',
                {
                    body: item.message || 'Your queue has been updated.',
                    tag: 'queueflow-' + item.notification_id
                }
            );

            notification.onclick = () => {
                window.focus();
                window.location.href = 'my-queue.php';
                notification.close();
            };

        } catch (error) {
            console.error(
                'Could not display browser notification:',
                error
            );
        }
    }

    // =========================================
    // Safely create DOM elements
    // =========================================

    function createElement(tag, className, text) {
        const element = document.createElement(tag);

        if (className) {
            element.className = className;
        }

        if (text !== undefined) {
            element.textContent = text;
        }

        return element;
    }

    // =========================================
    // Render notifications
    // =========================================

    function renderNotifications(notifications) {
        const fragment = document.createDocumentFragment();

        if (notifications.length === 0) {
            const empty = createElement('div', 'empty-state');

            empty.append(
                createElement('h3', '', "You're all caught up!")
            );

            empty.append(
                createElement(
                    'p',
                    '',
                    'Your queue notifications will appear here when there are updates to your ticket.'
                )
            );

            const link = createElement(
                'a',
                'button button-primary',
                'View My Queue'
            );

            link.href = 'my-queue.php';

            empty.append(link);
            fragment.append(empty);

        } else {

            for (const item of notifications) {
                const notificationId = String(item.notification_id);
                const unread = Number(item.is_read) === 0;
                const type = String(item.notification_type || '');

                const article = createElement(
                    'article',
                    'notification-item' +
                        (unread ? ' notification-unread' : '')
                );

                article.dataset.notificationId = notificationId;
                article.dataset.type = type;

                article.append(
                    createElement(
                        'div',
                        'notification-icon',
                        icons[type] || '●'
                    )
                );

                const content = createElement(
                    'div',
                    'notification-content'
                );

                const heading = createElement(
                    'div',
                    'notification-heading'
                );

                heading.append(
                    createElement(
                        'h3',
                        '',
                        item.title || 'Notification'
                    )
                );

                if (unread) {
                    heading.append(
                        createElement(
                            'span',
                            'status-pill status-waiting',
                            'New'
                        )
                    );
                }

                content.append(heading);

                content.append(
                    createElement('p', '', item.message || '')
                );

                const date = new Date(
                    String(item.created_at || '').replace(' ', 'T')
                );

                content.append(
                    createElement(
                        'small',
                        'muted-label',
                        Number.isNaN(date.getTime())
                            ? String(item.created_at || '')
                            : date.toLocaleString([], {
                                month: 'short',
                                day: '2-digit',
                                year: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit'
                            })
                    )
                );

                if (item.queue_id) {
                    const actions = createElement(
                        'div',
                        'notification-actions'
                    );

                    const link = createElement(
                        'a',
                        'text-link',
                        'View My Queue'
                    );

                    link.href = 'my-queue.php';

                    actions.append(link);
                    content.append(actions);
                }

                article.append(content);
                fragment.append(article);
            }
        }

        list.replaceChildren(fragment);
    }

    // =========================================
    // Poll notification data every five seconds
    // =========================================

    async function refreshNotifications() {
        if (isPolling || document.hidden) {
            return;
        }

        isPolling = true;

        try {
            const response = await fetch('/api/notifications-data.php', {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    Accept: 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error(
                    'Notification refresh failed: ' + response.status
                );
            }

            const data = await response.json();

            if (!data.success || !Array.isArray(data.notifications)) {
                throw new Error('Invalid notification response.');
            }

            const notifications = data.notifications;
            const unread = Number(data.unread_count) || 0;

            const currentIds = new Set(
                notifications.map(item => String(item.notification_id))
            );

            const newNotifications = notifications.filter(item =>
                !previousIds.has(String(item.notification_id))
            );

            countElement.textContent = String(unread);

            if (pluralElement) {
                pluralElement.textContent = unread === 1 ? '' : 's';
            }

            if (markAllForm) {
                markAllForm.hidden = unread === 0;
            }

            renderNotifications(notifications);

            if (newNotifications.length > 0) {
                liveStatus.textContent = 'You have a new notification.';
                liveStatus.classList.remove('is-error');

                newNotifications.forEach(item => {

                    // Always display the in-page popup.
                    showInPageNotification(item);

                    // Display system notification only if allowed.
                    if (
                        'Notification' in window &&
                        Notification.permission === 'granted'
                    ) {
                        showBrowserNotification(item);
                    }
                });

            } else {
                liveStatus.textContent = 'Notifications are up to date.';
                liveStatus.classList.remove('is-error');
            }

            previousIds = currentIds;

        } catch (error) {
            console.error('Notification refresh error:', error);

            liveStatus.textContent =
                'Could not refresh notifications. Retrying...';

            liveStatus.classList.add('is-error');

        } finally {
            isPolling = false;
        }
    }

    // Initial refresh.
    refreshNotifications();

    // Refresh every five seconds.
    window.setInterval(refreshNotifications, 5000);

    // Refresh immediately when the student returns to the page.
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            showConsentPopup();
            refreshNotifications();
        }
    });

})();
</script>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>