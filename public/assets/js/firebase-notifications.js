
/* QueueFlow Firebase Messaging Service Worker */

importScripts(
    'https://www.gstatic.com/firebasejs/10.13.2/firebase-app-compat.js'
);

importScripts(
    'https://www.gstatic.com/firebasejs/10.13.2/firebase-messaging-compat.js'
);

firebase.initializeApp({
    apiKey: 'AIzaSyAQIMZ0dusBA4HwWNrKaPOP6mIbaB4gvbc',
    authDomain: 'queueflow-48aa4.firebaseapp.com',
    projectId: 'queueflow-48aa4',
    storageBucket: 'queueflow-48aa4.firebasestorage.app',
    messagingSenderId: '795833520143',
    appId: '1:795833520143:web:4db256f49d7aca56dc137a'
});

const messaging = firebase.messaging();

/*
 * Handle background messages.
 *
 * Firebase automatically displays messages that contain a
 * notification payload while the app is in the background.
 * Do not manually display those messages again.
 *
 * For data-only messages, display the notification manually.
 */
messaging.onBackgroundMessage((payload) => {
    // Avoid displaying a notification twice.
    if (payload.notification) {
        console.log(
            'QueueFlow: Firebase notification payload received; ' +
            'skipping manual display.'
        );

        return;
    }

    const data = payload.data || {};

    const notificationTitle = data.title || 'QueueFlow Update';

    const notificationOptions = {
        body: data.body || 'Your queue status has changed.',
        icon: '/assets/img/icon.png',
        data: {
            url: data.url || '/notifications.php'
        }
    };

    return self.registration.showNotification(
        notificationTitle,
        notificationOptions
    );
});

/*
 * Handle notification clicks.
 */
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const targetUrl = new URL(
        event.notification.data?.url || '/notifications.php',
        self.location.origin
    ).href;

    event.waitUntil(
        clients.matchAll({
            type: 'window',
            includeUncontrolled: true
        }).then(async (windowClients) => {
            // Reuse an existing QueueFlow tab if available.
            for (const client of windowClients) {
                if (client.url.startsWith(self.location.origin)) {
                    await client.navigate(targetUrl);
                    return client.focus();
                }
            }

            // Otherwise, open QueueFlow in a new tab.
            return clients.openWindow(targetUrl);
        })
    );
});
