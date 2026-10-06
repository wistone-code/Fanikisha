// Fanikisha service worker.
//
// What it caches, and what it deliberately does not:
//   - The app shell: the friendly offline page, manifest and icons (precached on install).
//   - The ENTRANCE CHECK-IN page (/checkin) and the CSS/JS/fonts it needs, so the scanner opens
//     with no internet at the venue. The page is fetched network-first (a connected phone always
//     gets the live page) and the last good copy is the fallback.
//   - Nothing else. Pledges, finances, providers and every other page are never cached: that data
//     changes constantly and a stale copy would be worse than none.
//
// HOW TO BUST THE CACHE ON DEPLOY: change CACHE_VERSION below whenever the check-in page, this file,
// or the scanner library changes in a way phones must pick up. On activation the worker deletes every
// cache that does not carry the current version, and phones fetch a fresh copy of the check-in page
// the next time they are online. Vite's /build/ files already change name when their content changes,
// so those never go stale.
//
// Guest data (the offline guest list and queued scans) is NOT stored here — it lives in the page's
// IndexedDB — so bumping the version never loses un-synced check-ins.
const CACHE_VERSION = 'v2';
const SHELL_CACHE = 'fanikisha-shell-' + CACHE_VERSION;
const RUNTIME_CACHE = 'fanikisha-runtime-' + CACHE_VERSION;
const OFFLINE_URL = '/offline.html';
const PRECACHE_URLS = [OFFLINE_URL, '/manifest.json', '/icons/icon-192.png', '/icons/icon-512.png'];

// Third-party hosts whose files the app's pages need (icon font, text fonts).
const CROSS_ORIGIN_HOSTS = ['cdnjs.cloudflare.com', 'fonts.googleapis.com', 'fonts.gstatic.com'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(SHELL_CACHE).then((cache) => cache.addAll(PRECACHE_URLS)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((key) => key !== SHELL_CACHE && key !== RUNTIME_CACHE).map((key) => caches.delete(key)))
        )
    );
    self.clients.claim();
});

function isCacheable(response) {
    // ok responses, plus opaque ones (cross-origin no-cors, status 0) for fonts and icon CSS.
    return response && (response.ok || response.type === 'opaque');
}

function isStaticAsset(url) {
    return url.origin === self.location.origin
        && (url.pathname.startsWith('/build/') || url.pathname.startsWith('/js/') || url.pathname.startsWith('/icons/'));
}

function isCheckinPage(url) {
    return url.origin === self.location.origin && (url.pathname === '/checkin' || url.pathname === '/checkin/');
}

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);

    // Page navigations.
    if (request.mode === 'navigate') {
        if (isCheckinPage(url)) {
            event.respondWith(
                fetch(request)
                    .then((response) => {
                        // Keep a copy only of a real page (not a redirect to the login screen).
                        if (response.ok && !response.redirected) {
                            const copy = response.clone();
                            caches.open(RUNTIME_CACHE).then((cache) => cache.put(request, copy));
                        }
                        return response;
                    })
                    .catch(() => caches.match(request, { ignoreVary: true }).then((cached) => cached || caches.match(OFFLINE_URL)))
            );
            return;
        }

        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
        return;
    }

    // Built files and the self-hosted scanner library: cache first.
    if (isStaticAsset(url)) {
        event.respondWith(
            caches.match(request).then((cached) => {
                if (cached) return cached;
                return fetch(request).then((response) => {
                    if (isCacheable(response)) {
                        const copy = response.clone();
                        caches.open(RUNTIME_CACHE).then((cache) => cache.put(request, copy));
                    }
                    return response;
                });
            })
        );
        return;
    }

    // Fonts / icon font from known hosts: serve the saved copy, refresh it in the background.
    if (CROSS_ORIGIN_HOSTS.includes(url.hostname)) {
        event.respondWith(
            caches.match(request).then((cached) => {
                const refresh = fetch(request)
                    .then((response) => {
                        if (isCacheable(response)) {
                            const copy = response.clone();
                            caches.open(RUNTIME_CACHE).then((cache) => cache.put(request, copy));
                        }
                        return response;
                    })
                    .catch(() => cached);
                return cached || refresh;
            })
        );
    }

    // Everything else passes straight through to the network.
});

self.addEventListener('message', (event) => {
    const data = event.data || {};
    const reply = event.ports && event.ports[0];

    // The check-in page asks us to save itself and its files (the very first visit happens before
    // this worker is in control, so those requests were never seen).
    if (data.type === 'CACHE_URLS' && Array.isArray(data.urls)) {
        event.waitUntil(
            caches.open(RUNTIME_CACHE).then((cache) =>
                Promise.all(data.urls.map((href) => {
                    const sameOrigin = new URL(href, self.location.origin).origin === self.location.origin;
                    const request = new Request(href, sameOrigin ? { credentials: 'same-origin' } : { mode: 'no-cors' });
                    return fetch(request)
                        .then((response) => {
                            // Skip a login redirect — caching that would hide the real page later.
                            if (isCacheable(response) && !response.redirected) return cache.put(request, response);
                        })
                        .catch(() => {});
                }))
            ).then(() => { if (reply) reply.postMessage({ done: true }); })
        );
        return;
    }

    // Logging out: drop the saved check-in page and its copy of the arrival log.
    if (data.type === 'CLEAR_RUNTIME') {
        event.waitUntil(caches.delete(RUNTIME_CACHE).then(() => { if (reply) reply.postMessage({ done: true }); }));
    }
});
