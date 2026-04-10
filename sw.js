const CACHE_NAME = 'elina-pwa-v1';
const urlsToCache = [
    '/ELINA/',
    '/ELINA/auth/login.php',
    '/ELINA/assets/css/style.css',
    '/ELINA/assets/js/app.js',
    '/ELINA/assets/img/logo2.png',
    '/ELINA/assets/img/logoELINA.svg'
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                return cache.addAll(urlsToCache);
            })
    );
});

self.addEventListener('fetch', event => {
    // Basic network-first strategy, fallback to cache
    // Useful for dynamic dashboards where we prioritize fresh data over offline readiness,
    // but at least standard assets and shell load if network is completely off
    event.respondWith(
        fetch(event.request).catch(() => {
            return caches.match(event.request);
        })
    );
});
