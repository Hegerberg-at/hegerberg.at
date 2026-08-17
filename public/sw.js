/*
 * Service worker for the push notifications about new events.
 *
 * It is registered from src/components/PushSignup.astro. It deliberately sits
 * in the root directory, because a service worker may only be responsible for
 * its own directory and everything below it – from /api/sw.js it could not
 * serve the whole site.
 *
 * The worker caches nothing: the site is static and gets cached by the browser
 * anyway. It only reacts to incoming messages and to clicks on them.
 *
 * public/api/push-send.php sends the payload as JSON:
 *   { "title": "…", "text": "…", "url": "/veranstaltungen/…/" }
 */

const DEFAULT_TITLE = 'Schutzhaus am Hegerberg';
const DEFAULT_TARGET = '/veranstaltungen/';
const ICON = '/icons/icon-192.png';

/**
 * Read the payload of the notification. Some push services also deliver
 * messages without content – then the defaults apply.
 */
function payload(event) {
  if (!event.data) return {};
  try {
    return event.data.json() ?? {};
  } catch {
    return { text: event.data.text() };
  }
}

// A new worker should take over immediately instead of waiting for all tabs to
// close – otherwise the old version stays active after a deploy.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) =>
  event.waitUntil(self.clients.claim()),
);

self.addEventListener('push', (event) => {
  const data = payload(event);
  const target = typeof data.url === 'string' && data.url.startsWith('/')
    ? data.url
    : DEFAULT_TARGET;

  event.waitUntil(
    self.registration.showNotification(data.title || DEFAULT_TITLE, {
      body: data.text || '',
      icon: ICON,
      badge: ICON,
      lang: 'de-AT',
      // Same tag = an older notification about the same event is replaced
      // instead of stacked.
      tag: target,
      data: { url: target },
    }),
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  const target = new URL(
    event.notification.data?.url ?? DEFAULT_TARGET,
    self.location.origin,
  ).href;

  event.waitUntil(
    (async () => {
      // If the page is already open, switch to it instead of opening a second
      // tab.
      const windows = await self.clients.matchAll({
        type: 'window',
        includeUncontrolled: true,
      });

      for (const client of windows) {
        if (client.url === target) return client.focus();
      }

      const open = windows.find((client) => 'navigate' in client);
      if (open) {
        const navigated = await open.navigate(target);
        return navigated?.focus();
      }

      return self.clients.openWindow(target);
    })(),
  );
});
