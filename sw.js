/**
 * East Systems Colleges of Rizal - Quiz App
 * Progressive Web App (PWA) Service Worker
 */

const CACHE_NAME = 'escr-quiz-cache-v1';
const ASSETS_TO_CACHE = [
  './',
  'login.html',
  'quiz.html',
  'admin-dashboard.html',
  'api_client.js',
  'manifest.json',
  'icon-192.png',
  'icon-512.png',
  'icon.svg'
];

// Install Event — pre-cache core UI assets
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      return cache.addAll(ASSETS_TO_CACHE);
    }).then(() => self.skipWaiting())
  );
});

// Activate Event — clean up old caches
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => {
      return Promise.all(
        keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k))
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch Event — Network-first for api.php, cache-first for app shell
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);

  // If calling API, go network-first
  if (url.pathname.endsWith('api.php') || url.search.includes('action=')) {
    event.respondWith(
      fetch(event.request).catch(() => {
        return new Response(JSON.stringify({ success: false, offline: true }), {
          headers: { 'Content-Type': 'application/json' }
        });
      })
    );
    return;
  }

  // App shell assets: Cache with Network Fallback
  event.respondWith(
    caches.match(event.request).then(cached => {
      if (cached) {
        // Fetch in background to update cache
        fetch(event.request).then(res => {
          if (res && res.status === 200) {
            caches.open(CACHE_NAME).then(c => c.put(event.request, res));
          }
        }).catch(() => {});
        return cached;
      }
      return fetch(event.request);
    })
  );
});
