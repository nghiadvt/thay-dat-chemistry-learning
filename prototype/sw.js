/** Minimal SW — đủ tiêu chí cài PWA; không cache gameplay realtime. */
self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});
