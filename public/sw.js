/*
 * Yoiful reader service worker.
 *
 * - Page loads go to the network. When the network is down, the cached
 *   offline page is shown instead.
 * - Built assets (/build/assets/*) have hashed names, so they are served
 *   from the cache once fetched. That keeps the app shell loading offline.
 * - Nothing else is cached: pages and Inertia responses carry signed-in data.
 *
 * Bump VERSION to drop old caches when this file's caching rules change.
 */
const VERSION = 'v1';
const SHELL_CACHE = `yoiful-shell-${VERSION}`;
const ASSET_CACHE = `yoiful-assets-${VERSION}`;
const OFFLINE_URL = '/offline';
const SHELL_URLS = [
    OFFLINE_URL,
    '/manifest.webmanifest',
    '/icons/icon-192x192.png',
    '/icons/icon-512x512.png',
    '/favicon.ico',
];
const MAX_ASSETS = 120;

/** Same-origin /build/ URLs referenced by an HTML page. */
function buildAssetsIn(html) {
    const urls = new Set();

    for (const match of html.matchAll(/(?:href|src)="([^"]+)"/g)) {
        const url = new URL(match[1], self.location.origin);

        if (
            url.origin === self.location.origin &&
            url.pathname.startsWith('/build/')
        ) {
            urls.add(url.pathname);
        }
    }

    return [...urls];
}

async function precacheShell() {
    const shell = await caches.open(SHELL_CACHE);
    await shell.addAll(SHELL_URLS);

    // Also cache the stylesheet the offline page links to.
    const offline = await shell.match(OFFLINE_URL);
    const assets = offline ? buildAssetsIn(await offline.clone().text()) : [];

    if (assets.length > 0) {
        const cache = await caches.open(ASSET_CACHE);
        await cache.addAll(assets);
    }
}

async function trimAssets() {
    const cache = await caches.open(ASSET_CACHE);
    const keys = await cache.keys();

    // Cache keys come back in insertion order: drop the oldest.
    await Promise.all(
        keys
            .slice(0, Math.max(0, keys.length - MAX_ASSETS))
            .map((key) => cache.delete(key)),
    );
}

self.addEventListener('install', (event) => {
    event.waitUntil(precacheShell().then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter(
                            (key) =>
                                key.startsWith('yoiful-') &&
                                key !== SHELL_CACHE &&
                                key !== ASSET_CACHE,
                        )
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

async function pageOrOffline(request) {
    try {
        return await fetch(request);
    } catch (error) {
        const offline = await caches.match(OFFLINE_URL, {
            cacheName: SHELL_CACHE,
        });

        if (offline) {
            return offline;
        }

        throw error;
    }
}

async function cachedAsset(request) {
    const cache = await caches.open(ASSET_CACHE);
    const cached = await cache.match(request);

    if (cached) {
        return cached;
    }

    const response = await fetch(request);

    if (response.ok) {
        await cache.put(request, response.clone());
        await trimAssets();
    }

    return response;
}

async function cachedShellFile(request) {
    try {
        return await fetch(request);
    } catch (error) {
        const cached = await caches.match(request, { cacheName: SHELL_CACHE });

        if (cached) {
            return cached;
        }

        throw error;
    }
}

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(pageOrOffline(request));

        return;
    }

    if (url.pathname.startsWith('/build/assets/')) {
        event.respondWith(cachedAsset(request));

        return;
    }

    if (SHELL_URLS.includes(url.pathname) && url.pathname !== OFFLINE_URL) {
        event.respondWith(cachedShellFile(request));
    }
});
