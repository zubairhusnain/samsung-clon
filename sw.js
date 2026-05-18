/* Local stub — no offline caching for samsung-clon mirror */
self.addEventListener('install', function (e) {
    self.skipWaiting();
});
self.addEventListener('activate', function (e) {
    e.waitUntil(self.clients.claim());
});
