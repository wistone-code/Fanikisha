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
// VERSIONING IS AUTOMATIC. The page registers this file as /sw.js?v=<code> (see App\Support\PwaVersion),
// and the code changes whenever the build, this file, the offline page, the manifest or the icons change.
// No one edits a version number by hand any more.
//   - The SHELL cache carries that code in its name, so each deploy gets a fresh one and the old ones
//     are deleted when the new worker activates.
//   - The RUNTIME cache (the saved check-in page and its files) keeps ONE stable name on purpose: a
//     deploy the night before an event must never wipe the saved check-in page. Instead, old built
//     CSS/JS files are removed only after a fresh check-in page has been saved, and only the ones that
//     page no longer uses (see pruneBuildFiles).
//
// Guest data (the offline guest list and queued scans) is NOT stored here — it lives in the page's
// IndexedDB — so a new version never loses un-synced check-ins.
const VERSION = new URL(self.location.href).searchParams.get('v') || 'dev';
const SHELL_CACHE = 'fanikisha-shell-' + VERSION;
const RUNTIME_CACHE = 'fanikisha-runtime';
const LEGACY_RUNTIME_PREFIX = 'fanikisha-runtime-'; // older workers used fanikisha-runtime-v3 etc.
const OFFLINE_URL = '/offline.html';
const PRECACHE_URLS = [OFFLINE_URL, '/manifest.json', '/icons/icon-192.png', '/icons/icon-512.png', '/icons/favicon-32.png', '/icons/apple-touch-icon.png'];

// Third-party hosts whose files the app's pages need (icon font, text fonts).
const CROSS_ORIGIN_HOSTS = ['cdnjs.cloudflare.com', 'fonts.googleapis.com', 'fonts.gstatic.com'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(SHELL_CACHE).then((cache) => cache.addAll(PRECACHE_URLS)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const keys = await caches.keys();
        const runtime = await caches.open(RUNTIME_CACHE);

        for (const key of keys) {
            if (key === SHELL_CACHE || key === RUNTIME_CACHE) continue;

            // A phone updating from an older worker: rescue its saved check-in page and files first,
            // so an update never leaves a prepared phone without its offline check-in.
            if (key.startsWith(LEGACY_RUNTIME_PREFIX)) {
                try {
                    const old = await caches.open(key);
                    for (const request of await old.keys()) {
                        if (await runtime.match(request)) continue;
                        const response = await old.match(request);
                        if (response) await runtime.put(request, response);
                    }
                } catch (e) { /* nothing to rescue */ }
            }

            await caches.delete(key); // old shell caches and old versioned runtime caches
        }

        await self.clients.claim();
    })());
});

// Safari refuses to show a page when the worker answers a navigation with a response that followed a
// redirect ("Response served by service worker has redirections"). Rebuilding it as a plain response fixes that.
async function plain(response) {
    if (!response || !response.redirected) return response;
    return new Response(await response.blob(), { status: response.status, statusText: response.statusText, headers: response.headers });
}

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

// Remove saved /build/ CSS and JS files that the freshly saved check-in page no longer uses, so
// old deploys' files do not pile up. Safe by design: it only ever looks at files in /build/ ending in
// .css or .js, only runs when the page actually names some, and never touches anything else.
async function pruneBuildFiles(pageHtml) {
    try {
        const used = new Set((pageHtml.match(/\/build\/[^"'\s)<>?#]+/g) || []));
        if (![...used].some((path) => /\.(css|js)$/.test(path))) return;

        const cache = await caches.open(RUNTIME_CACHE);
        for (const request of await cache.keys()) {
            const path = new URL(request.url).pathname;
            if (path.startsWith('/build/') && /\.(css|js)$/.test(path) && !used.has(path)) {
                await cache.delete(request);
            }
        }
    } catch (e) { /* cleanup is best-effort */ }
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
                            const forPrune = response.clone();
                            caches.open(RUNTIME_CACHE)
                                .then((cache) => cache.put(request, copy))
                                .then(() => forPrune.text())
                                .then(pruneBuildFiles)
                                .catch(() => {});
                        }
                        return plain(response);
                    })
                    .catch(() => caches.match(request, { ignoreVary: true }).then((cached) => cached || caches.match(OFFLINE_URL)))
            );
            return;
        }

        event.respondWith(fetch(request).then(plain).catch(() => caches.match(OFFLINE_URL)));
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
                })).then(async () => {
                    // Tidy up old built files now that a fresh copy of the check-in page is saved.
                    const first = data.urls[0] && new URL(data.urls[0], self.location.origin);
                    if (first && isCheckinPage(first)) {
                        const page = await cache.match(data.urls[0], { ignoreVary: true });
                        if (page) await pruneBuildFiles(await page.clone().text());
                    }
                })
            ).then(() => { if (reply) reply.postMessage({ done: true }); })
        );
        return;
    }

    // Logging out: drop the saved check-in page and its copy of the arrival log.
    if (data.type === 'CLEAR_RUNTIME') {
        event.waitUntil(caches.delete(RUNTIME_CACHE).then(() => { if (reply) reply.postMessage({ done: true }); }));
    }
});
