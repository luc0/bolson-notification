self.addEventListener('push', (event) => {
    if (!event.data) return;
    const payload = event.data.json(); // {title, body, icon, data:{url}}
    event.waitUntil(
        self.registration.showNotification(payload.title || 'Nuevo', {
            body: payload.body || '',
            icon: payload.icon || '/icons/icon-icon-192.png',
            badge: payload.badge || '/icons/icon-icon-192.png',
            data: payload.data || {},
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url || '/';
    event.waitUntil(clients.openWindow(url));
});

self.addEventListener('fetch', () => {});
self.addEventListener('install', e => self.skipWaiting());
self.addEventListener('activate', e => self.clients.claim());
