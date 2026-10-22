/* Replaces leftover RecordPlus Workbox SW (NavigationRoute → cached index.html). */
self.addEventListener('install', (event) => {
    event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        await self.clients.claim();
        const keys = await caches.keys();
        await Promise.all(keys.map((key) => caches.delete(key)));
        await self.registration.unregister();
        const clients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        await Promise.all(clients.map((client) => client.navigate(client.url)));
    })());
});

// While briefly active, never serve RecordPlus caches — always hit the network.
self.addEventListener('fetch', (event) => {
    event.respondWith(fetch(event.request));
});
