/**
 * Service Worker para Progressive Web App (PWA)
 * v3 - HTML sempre do servidor, assets estáticos em cache, fallback offline com index.php
 */

const CACHE_NAME = 'cameras-app-v3';
const API_CACHE = 'cameras-api-v3';
const ASSETS_CACHE = 'cameras-assets-v3';

const ASSETS_TO_CACHE = [
  './assets/css/main.css',
  './assets/css/theme-enhancements.css',
  './assets/js/main.js',
  './assets/js/utils/ui/theme-manager.js',
  './assets/js/utils/search/AlarmeSearch.js',
  './images/favicon-32x32.png',
  './images/favicon-16x16.png',
  './images/apple-touch-icon.png',
  './index.php'
];

self.addEventListener('install', event => {
  event.waitUntil(
    Promise.all([
      caches.open(CACHE_NAME).then(cache => {
        return cache.addAll(ASSETS_TO_CACHE).catch(() => {});
      }),
      caches.open(ASSETS_CACHE).then(cache => {
        return cache.addAll([
          'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css',
          'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
          'https://code.jquery.com/jquery-3.7.1.min.js',
          'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js'
        ]).catch(() => {});
      })
    ]).catch(() => {})
  );
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME &&
              cacheName !== API_CACHE &&
              cacheName !== ASSETS_CACHE) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  self.clients.claim();
});

self.addEventListener('fetch', event => {
  const { request } = event;
  const url = new URL(request.url);

  if (request.method !== 'GET') return;

  // API requests - Network only, no cache
  if (url.pathname.includes('/api/') || url.search.includes('page=api/')) {
    event.respondWith(
      fetch(request).catch(() => caches.match(request))
    );
    return;
  }

  // Navegação (HTML) - SEMPRE do servidor, nunca do cache
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(() => caches.match('./index.php'))
    );
    return;
  }

  // Assets estáticos (css, js, imagens) - Cache first
  event.respondWith(
    caches.match(request).then(response => {
      if (response) return response;

      return fetch(request).then(response => {
        if (!response || response.status !== 200 || response.type !== 'basic') {
          return response;
        }
        const responseToCache = response.clone();
        caches.open(CACHE_NAME).then(cache => cache.put(request, responseToCache));
        return response;
      }).catch(() => caches.match('./index.php'));
    })
  );
});

self.addEventListener('sync', event => {
  if (event.tag === 'sync-api') {
    event.waitUntil(fetch(BASE_URL + '/index.php?page=api/api_health', { method: 'GET' }));
  }
});

self.addEventListener('push', event => {
  const options = {
    body: event.data ? event.data.text() : 'Notificação do Sistema',
    icon: '/images/android-chrome-192x192.png',
    badge: '/images/favicon-32x32.png',
    vibrate: [100, 50, 100],
    data: { dateOfArrival: Date.now(), primaryKey: 1 }
  };
  event.waitUntil(self.registration.showNotification('Sistema de Câmeras', options));
});

self.addEventListener('notificationclick', event => {
  event.notification.close();
  event.waitUntil(
    clients.matchAll({ type: 'window' }).then(clientList => {
      for (const client of clientList) {
        if (client.url === '/' && 'focus' in client) return client.focus();
      }
      if (clients.openWindow) return clients.openWindow('/');
    })
  );
});
