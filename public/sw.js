/*
 * Service Worker für die Push-Benachrichtigungen bei neuen Veranstaltungen.
 *
 * Registriert wird er aus src/components/PushAnmeldung.astro. Er liegt
 * bewusst im Wurzelverzeichnis, weil ein Service Worker nur für sein eigenes
 * Verzeichnis und darunter zuständig sein darf – aus /api/sw.js ließe sich die
 * ganze Seite nicht bedienen.
 *
 * Der Worker speichert nichts zwischen: Die Seite ist statisch und wird vom
 * Browser ohnehin gecacht. Er reagiert nur auf eintreffende Nachrichten und
 * auf Klicks darauf.
 *
 * Die Nutzlast schickt public/api/push-versand.php als JSON:
 *   { "titel": "…", "text": "…", "url": "/veranstaltungen/…/" }
 */

const STANDARD_TITEL = 'Schutzhaus am Hegerberg';
const STANDARD_ZIEL = '/veranstaltungen/';
const SYMBOL = '/icons/icon-192.png';

/**
 * Nutzlast der Benachrichtigung lesen. Manche Push-Dienste stellen auch
 * Nachrichten ohne Inhalt zu – dann bleibt es bei den Standardwerten.
 */
function inhalt(ereignis) {
  if (!ereignis.data) return {};
  try {
    return ereignis.data.json() ?? {};
  } catch {
    return { text: ereignis.data.text() };
  }
}

// Neuer Worker soll sofort übernehmen statt auf das Schließen aller Tabs zu
// warten – sonst bleibt nach einem Deploy die alte Fassung aktiv.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (ereignis) =>
  ereignis.waitUntil(self.clients.claim()),
);

self.addEventListener('push', (ereignis) => {
  const daten = inhalt(ereignis);
  const ziel = typeof daten.url === 'string' && daten.url.startsWith('/')
    ? daten.url
    : STANDARD_ZIEL;

  ereignis.waitUntil(
    self.registration.showNotification(daten.titel || STANDARD_TITEL, {
      body: daten.text || '',
      icon: SYMBOL,
      badge: SYMBOL,
      lang: 'de-AT',
      // Gleiches Tag = eine ältere Benachrichtigung zur selben Veranstaltung
      // wird ersetzt statt gestapelt.
      tag: ziel,
      data: { url: ziel },
    }),
  );
});

self.addEventListener('notificationclick', (ereignis) => {
  ereignis.notification.close();

  const ziel = new URL(
    ereignis.notification.data?.url ?? STANDARD_ZIEL,
    self.location.origin,
  ).href;

  ereignis.waitUntil(
    (async () => {
      // Ist die Seite schon offen, dorthin wechseln statt einen zweiten Tab
      // aufzumachen.
      const fenster = await self.clients.matchAll({
        type: 'window',
        includeUncontrolled: true,
      });

      for (const client of fenster) {
        if (client.url === ziel) return client.focus();
      }

      const offen = fenster.find((client) => 'navigate' in client);
      if (offen) {
        const gewechselt = await offen.navigate(ziel);
        return gewechselt?.focus();
      }

      return self.clients.openWindow(ziel);
    })(),
  );
});
