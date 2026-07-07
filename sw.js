/**
 * NexusChat - Service Worker
 */
const CACHE_NAME = 'nexuschat-v1';
const CORE_ASSETS = [
  '/', '/index.php', '/login.php',
  '/assets/css/main.css', '/assets/css/chat.css', '/assets/css/wallet.css',
  '/assets/css/themes.css', '/assets/css/animations.css',
  '/assets/js/app.js', '/assets/js/chat.js', '/assets/js/wallet.js',
];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE_NAME).then(c => c.addAll(CORE_ASSETS).catch(() => {})));
  self.skipWaiting();
});

self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))));
  self.clients.claim();
});

self.addEventListener('fetch', (e) => {
  if (e.request.method !== 'GET') return;
  if (e.request.url.includes('/api/')) return;
  e.respondWith(
    caches.match(e.request).then(cached => cached || fetch(e.request).then(res => {
      if (res.ok && (e.request.url.includes('/assets/') || e.request.url.includes('/uploads/'))) {
        const clone = res.clone();
        caches.open(CACHE_NAME).then(c => c.put(e.request, clone));
      }
      return res;
    }).catch(() => cached))
  );
});

self.addEventListener('push', (e) => {
  let data = { title: 'NexusChat', body: 'پیام جدید دارید', url: '/' };
  if (e.data) { try { data = { ...data, ...e.data.json() }; } catch (err) { data.body = e.data.text(); } }
  e.waitUntil(self.registration.showNotification(data.title, {
    body: data.body, icon: data.icon || '/icon.png', badge: data.badge || '/badge.png',
    data: { url: data.url || '/' }, dir: 'rtl', lang: 'fa', vibrate: [200, 100, 200],
  }));
});

self.addEventListener('notificationclick', (e) => {
  e.notification.close();
  const url = e.notification.data?.url || '/';
  e.waitUntil(clients.openWindow(url));
});
