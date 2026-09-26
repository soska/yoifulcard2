/**
 * Register the reader's service worker (public/sw.js). It serves the
 * offline page when the network is down and caches built assets so the app
 * shell still loads. Browsers only allow it on HTTPS or localhost.
 */
export function registerServiceWorker(): void {
    if (!('serviceWorker' in navigator) || !window.isSecureContext) {
        return;
    }

    navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {
        // Without a service worker the reader still works online.
    });
}
