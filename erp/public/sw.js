// ============================================================
// ERP PRO - Service Worker (PWA)
// ============================================================

const CACHE_NAME = 'erp-pro-v1';
const STATIC_ASSETS = [
  '/erp/public/index.html',
  '/erp/public/login.html',
  '/erp/public/assets/css/erp.css',
  '/erp/public/assets/js/erp-core.js',
  '/erp/public/manifest.json',
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(STATIC_ASSETS)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);
  if (url.pathname.startsWith('/erp/api/')) {
    // Network first for API
    event.respondWith(
      fetch(event.request).catch(() => new Response(JSON.stringify({ error: 'Offline' }), { headers: { 'Content-Type': 'application/json' } }))
    );
    return;
  }
  // Cache first for static
  event.respondWith(
    caches.match(event.request).then(cached => cached || fetch(event.request).then(response => {
      if (response.ok) caches.open(CACHE_NAME).then(cache => cache.put(event.request, response.clone()));
      return response;
    }))
  );
});

// Push notifications
self.addEventListener('push', event => {
  const data = event.data?.json() || {};
  event.waitUntil(
    self.registration.showNotification(data.title || 'ERP Pro', {
      body: data.body || '',
      icon: '/erp/public/assets/images/icon-192.png',
      badge: '/erp/public/assets/images/icon-192.png',
      data: data,
    })
  );
});

self.addEventListener('notificationclick', event => {
  event.notification.close();
  event.waitUntil(clients.openWindow('/erp/public/index.html'));
});
