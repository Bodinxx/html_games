/**
 * Simple Service Worker for ProTrack PWA
 * Strategy: Network First (required for PHP backend interaction)
 */

const CACHE_NAME = 'protrack-cache-v1';
const ASSETS = [
    'time_tracker.php',
    'manifest.json'
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => {
            return cache.addAll(ASSETS);
        })
    );
});

self.addEventListener('fetch', event => {
    // For PHP apps, always try the network first to ensure fresh data
    event.respondWith(
        fetch(event.request).catch(() => {
            return caches.match(event.request);
        })
    );
});