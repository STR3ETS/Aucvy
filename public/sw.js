/*
 | De service worker voor web push (briefing hfst. 14). Bewust minimaal:
 | melding tonen, klik opent de kavel. Geen caching, geen offline - dat is
 | een ander project.
 */
self.addEventListener('push', (event) => {
    if (! event.data) return;

    const payload = event.data.json();

    event.waitUntil(self.registration.showNotification(payload.title, {
        body: payload.body,
        tag: payload.tag,
        data: payload.data,
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = event.notification.data?.url;
    if (! url) return;

    event.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
        const open = windows.find((client) => client.url === url);

        return open ? open.focus() : clients.openWindow(url);
    }));
});
