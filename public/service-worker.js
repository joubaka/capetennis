const CACHE_NAME = 'cape-tennis-shell-v2';
const OFFLINE_URL = '/offline.html';
const SHELL_FILES = [
  OFFLINE_URL,
  '/manifest.webmanifest',
  '/assets/img/pwa/cape-tennis-app-192.png',
  '/assets/img/pwa/cape-tennis-app-512.png',
  '/assets/img/pwa/cape-tennis-app.svg'
];

self.addEventListener('install', event => {
  event.waitUntil(caches.open(CACHE_NAME).then(cache => cache.addAll(SHELL_FILES)));
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys()
      .then(keys => Promise.all(keys
        .filter(key => key.startsWith('cape-tennis-shell-') && key !== CACHE_NAME)
        .map(key => caches.delete(key))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', event => {
  if (event.request.method !== 'GET' || event.request.mode !== 'navigate') return;

  event.respondWith(
    fetch(event.request).catch(() => caches.match(OFFLINE_URL))
  );
});
