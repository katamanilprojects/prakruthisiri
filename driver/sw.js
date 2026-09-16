// Prakruthi Siri Driver Portal - Service Worker
const CACHE_NAME = 'ps-driver-v1';

self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(clients.claim());
});

self.addEventListener('fetch', (event) => {
  // Network first strategy for live delivery tracking and status updates
  event.respondWith(
    fetch(event.request).catch(() => caches.match(event.request))
  );
});
