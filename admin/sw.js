// Prakruthi Siri Admin Portal - Service Worker
const CACHE_NAME = 'ps-admin-v1';

self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(clients.claim());
});

self.addEventListener('fetch', (event) => {
  // Network first strategy for real-time admin portal operations
  event.respondWith(
    fetch(event.request).catch(() => caches.match(event.request))
  );
});
