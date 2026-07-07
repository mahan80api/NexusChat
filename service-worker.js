/**
 * Service Worker for NexusChat Push Notifications
 * Handles incoming push, notification display, and click actions
 */
const CACHE_NAME = 'nexuschat-v1';
const APP_ICON  = '/assets/icon-192.png';
const BADGE_ICON = '/assets/badge-72.png';

self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
  let data = { title: 'NexusChat', body: 'پیام جدید دارید', icon: APP_ICON, badge: BADGE_ICON };
  try {
    if (event.data) data = event.data.json();
  } catch (e) { /* ignore */ }

  const options = {
    body: data.body,
    icon: data.icon || APP_ICON,
    badge: data.badge || BADGE_ICON,
    image: data.image,
    tag: data.tag || 'nexuschat',
    renotify: data.renotify !== false,
    requireInteraction: data.requireInteraction || false,
    silent: data.silent || false,
    vibrate: data.vibrate || [200, 100, 200],
    dir: 'rtl',
    lang: 'fa',
    timestamp: Date.now(),
    data: data.data || {},
    actions: data.actions || [
      { action: 'open', title: 'باز کردن', icon: '/assets/action-open.png' },
      { action: 'mute', title: 'بی‌صدا', icon: '/assets/action-mute.png' },
    ],
  };

  event.waitUntil(
    self.registration.showNotification(data.title, options)
      .then(() => updateBadge())
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const action = event.action;
  const data = event.notification.data || {};

  if (action === 'mute' && data.chat_id) {
    // Tell client to mute
    event.waitUntil(
      fetch('/api/push', {
        method: 'POST',
        credentials: 'include',
        body: new URLSearchParams({ action: 'mute_chat', chat_id: data.chat_id, duration: '8h' }),
      }).then(() => self.registration.showNotification('🔕 بی‌صدا شد', { body: 'اعلان‌های این چت برای ۸ ساعت بی‌صدا شد', tag: 'mute_acked' }))
    );
    return;
  }

  // Default: open chat
  const url = data.url || (data.chat_id ? '/chat?open=' + data.chat_id : '/chat');
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      for (const client of clientList) {
        if ('focus' in client) {
          client.postMessage({ type: 'navigate', url, data });
          return client.focus();
        }
      }
      if (clients.openWindow) return clients.openWindow(url);
    })
  );
});

self.addEventListener('notificationclose', (event) => {
  // Mark as dismissed in backend
  const data = event.notification.data || {};
  if (data.notification_id) {
    fetch('/api/push?action=mark_clicked', {
      method: 'POST',
      credentials: 'include',
      body: new URLSearchParams({ notification_id: data.notification_id, status: 'dismissed' }),
    });
  }
});

async function updateBadge() {
  if ('setAppBadge' in self.navigator) {
    try {
      const res = await fetch('/api/messages?action=unread_count', { credentials: 'include' });
      const data = await res.json();
      if (data.count > 0) await self.navigator.setAppBadge(data.count);
      else await self.navigator.clearAppBadge();
    } catch (e) {}
  }
}
