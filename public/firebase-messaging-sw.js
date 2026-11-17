
/* Firebase Messaging service worker */
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

messaging.onBackgroundMessage((payload) => {
    const notificationTitle =
        payload.notification?.title || 'QueueFlow Update';

    const notificationOptions = {
        body: payload.notification?.body || 'Your queue status has changed.',
        icon: '/assets/img/icon.png',
        data: {
            url: '/notifications.php'
        }
    };

    return self.registration.showNotification(
        notificationTitle,
        notificationOptions
    );
});

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
        }).then((windowClients) => {
            for (const client of windowClients) {
                if (client.url.startsWith(self.location.origin)) {
                    return client.navigate(targetUrl).then(() => client.focus());
                }
            }

            return clients.openWindow(targetUrl);
        })
    );
});
