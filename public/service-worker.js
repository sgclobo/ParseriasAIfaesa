const CACHE_NAME = 'parseiras-v1';
const STATIC_ASSETS = [
  '/',
  '/index.html',
  '/app.json',
  '/assets/images/favicon.png',
  '/assets/images/icon.png',
  '/assets/images/apple-touch-icon.png',
  '/assets/images/pwa/icon-192.png',
  '/assets/images/pwa/icon-512.png',
  '/manifest.json'
];

// Install: Pre-cache static assets
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      console.log('[ServiceWorker] Caching static assets');
      return cache.addAll(STATIC_ASSETS).catch((err) => {
        console.warn('[ServiceWorker] Some assets failed to cache:', err);
        // Don't fail install if some assets are missing
        return Promise.resolve();
      });
    })
  );
  self.skipWaiting();
});

// Activate: Clean up old caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            console.log('[ServiceWorker] Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  self.clients.claim();
});

// Fetch: Network first for API, cache first for static assets
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // Skip non-GET requests
  if (request.method !== 'GET') {
    return;
  }

  // API requests: network first, fallback to offline response
  if (url.pathname.includes('/api/') || url.pathname.includes('/web/api/')) {
    event.respondWith(
      fetch(request)
        .then((response) => {
          // Cache successful responses for offline use
          if (response.ok) {
            const responseToCache = response.clone();
            caches.open(CACHE_NAME).then((cache) => {
              cache.put(request, responseToCache);
            });
          }
          return response;
        })
        .catch(() => {
          // Return offline placeholder for API requests
          return caches.match(request).then((cachedResponse) => {
            return (
              cachedResponse ||
              new Response(
                JSON.stringify({ error: 'Offline', cached: false }),
                { 
                  status: 503,
                  statusText: 'Service Unavailable',
                  headers: { 'Content-Type': 'application/json' }
                }
              )
            );
          });
        })
    );
    return;
  }

  // Static assets: cache first, fallback to network
  event.respondWith(
    caches
      .match(request)
      .then((cachedResponse) => {
        if (cachedResponse) {
          return cachedResponse;
        }
        return fetch(request).then((response) => {
          // Cache successful responses
          if (response.ok) {
            const responseToCache = response.clone();
            caches.open(CACHE_NAME).then((cache) => {
              cache.put(request, responseToCache);
            });
          }
          return response;
        });
      })
      .catch(() => {
        // Offline fallback
        return new Response('Offline - Asset not available', { status: 503 });
      })
  );
});

// Handle background sync for PWA (future enhancement)
self.addEventListener('sync', (event) => {
  if (event.tag === 'sync-data') {
    event.waitUntil(
      // Implement sync logic here in future
      Promise.resolve()
    );
  }
});
