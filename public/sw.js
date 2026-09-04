// Fanikisha service worker — deliberately minimal.
//
// This does NOT cache pledges, providers, or any dynamic page content — that
// data changes constantly and serving a stale cached copy would be worse than
// no offline support at all. Its only two jobs are:
//   1. Satisfy Chrome's PWA installability requirement (a fetch handler that
//      returns a Response), so Android's "Install app" prompt becomes available.
//   2. Show a friendly offline page instead of the browser's default error
//      screen when someone loses signal mid-navigation.
const CACHE_NAME = 'fanikisha-shell-v1';
const OFFLINE_URL = '/offline.html';
const PRECACHE_URLS = [OFFLINE_URL, '/manifest.json', '/icons/icon-192.png', '/icons/icon-512.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(PRECACHE_URLS))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key)))
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    // Only intervene for page navigations (clicking a link, typing a URL).
    // Everything else (CSS, JS, images, API-style requests) passes straight
    // through to the network exactly as if no service worker existed.
    if (event.request.mode !== 'navigate') return;

    event.respondWith(
        fetch(event.request).catch(() => caches.match(OFFLINE_URL))
    );
});
