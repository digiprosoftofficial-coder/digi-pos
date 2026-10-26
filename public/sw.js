/* digipro POS service worker — offline app shell + runtime asset caching.
 * Hand-written (no build step) so it is served from the site root and
 * therefore controls the entire origin (scope "/"). */

const VERSION = 'v1';
const SHELL_CACHE = `digipro-shell-${VERSION}`;
const ASSET_CACHE = `digipro-assets-${VERSION}`;
const SHELL_URL = '__app_shell__';

// Take control as soon as possible.
self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        (async () => {
            const keys = await caches.keys();
            await Promise.all(
                keys
                    .filter((k) => k !== SHELL_CACHE && k !== ASSET_CACHE)
                    .map((k) => caches.delete(k))
            );
            await self.clients.claim();
        })()
    );
});

// Allow the page to trigger an immediate activation after an update.
self.addEventListener('message', (event) => {
    if (event.data === 'SKIP_WAITING') self.skipWaiting();
});

function isAsset(url) {
    return (
        url.pathname.startsWith('/build/') ||
        url.pathname.startsWith('/icon-') ||
        url.pathname === '/manifest.webmanifest' ||
        url.hostname.endsWith('fonts.bunny.net')
    );
}

// Network-first for navigations: always try fresh HTML, fall back to the
// cached app shell so the SPA boots offline on any route.
async function handleNavigate(request) {
    const cache = await caches.open(SHELL_CACHE);
    try {
        const response = await fetch(request);
        // Persist the latest shell for offline boots.
        cache.put(SHELL_URL, response.clone());
        return response;
    } catch (e) {
        const cached = (await cache.match(request)) || (await cache.match(SHELL_URL));
        if (cached) return cached;
        return new Response(
            '<!doctype html><meta charset="utf-8"><title>Offline</title><body style="font-family:sans-serif;padding:2rem;text-align:center">You are offline and the app has not been cached yet. Connect once to install digipro POS.</body>',
            { headers: { 'Content-Type': 'text/html' }, status: 503 }
        );
    }
}

// Stale-while-revalidate for static assets & fonts.
async function handleAsset(request) {
    const cache = await caches.open(ASSET_CACHE);
    const cached = await cache.match(request);
    const network = fetch(request)
        .then((response) => {
            if (response && (response.ok || response.type === 'opaque')) {
                cache.put(request, response.clone());
            }
            return response;
        })
        .catch(() => cached);
    return cached || network;
}

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);

    // Never cache API traffic — the app handles offline data via IndexedDB.
    if (url.pathname.startsWith('/api/')) return;

    if (request.mode === 'navigate') {
        event.respondWith(handleNavigate(request));
        return;
    }

    if (isAsset(url)) {
        event.respondWith(handleAsset(request));
    }
});
