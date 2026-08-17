# hegerberg.at

Website für das **Schutzhaus am Hegerberg** (Stössing, Bezirk St. Pölten Land).
Statisch generiert mit [Astro](https://astro.build), redaktionell gepflegt über
[Decap CMS](https://decapcms.org), gehostet bei **Hostinger**. Package Manager
und Runtime: **Bun**.

Die Seite wird komplett zu HTML gebaut. Auf dem Server läuft PHP nur für drei
Dinge, die statisch nicht gehen: den CMS-Login, das Mitgliedschafts-Formular und
die Push-Benachrichtigungen.

## Schnellstart

Mit [just](https://github.com/casey/just) startet beides zusammen:

```bash
just                 # Dev-Server + CMS-Proxy, Strg+C beendet beide
```

Von Hand geht es auch – dann braucht es zwei Terminals:

```bash
bun install
bun run dev          # → http://localhost:4321
bun run cms          # decap-server auf Port 8081
```

Das Redaktionssystem liegt dann unter <http://localhost:4321/admin/>. `just`
setzt dafür `local_backend: true` in `public/admin/config.yml`, damit Decap
direkt in die Markdown-Dateien schreibt – **ohne Git-Login**. Vor dem Commit mit
`just local-backend-off` wieder zurückstellen.

| Befehl               | Wirkung                                       |
| -------------------- | --------------------------------------------- |
| `just`               | Dev-Server und CMS-Proxy zusammen              |
| `just dev-only`      | nur der Astro-Dev-Server                       |
| `just check`         | `astro check` (Astro- und TypeScript-Prüfung)  |
| `just build`         | Produktions-Build nach `dist/`                 |
| `just preview`       | Build lokal ausliefern                         |
| `just clean`         | `dist/` und den Astro-Cache löschen            |
| `just local-backend-off` | CMS wieder auf das GitHub-Backend stellen  |

Dieselben Schritte gibt es als `bun run dev` / `build` / `preview` / `cms`.

## Funktionsumfang

| Bereich                     | Adresse             | Inhalt kommt aus          | Stand |
| --------------------------- | ------------------- | ------------------------- | ----- |
| **Startseite**              | `/`                 | `home/` + die übrigen Collections | live |
| **Veranstaltungen**         | `/veranstaltungen/` | `events/`                 | live |
| **Push-Benachrichtigungen** | `/veranstaltungen/` | –                         | live, [Einrichtung nötig](#push-benachrichtigungen) |
| **Aktivitäten** (Touren)    | `/aktivitaeten/`    | `tours/` + GPX-Dateien    | live |
| **Öffnungszeiten**          | `/oeffnungszeiten/` | `opening-hours/`          | live |
| **Kontakt & Anfahrt**       | `/kontakt/`         | `src/lib/site.ts`         | live |
| **Mitglied werden**         | `/kontakt/`, `/`    | Formular → E-Mail         | live |
| **Galerie**                 | `/galerie/`         | `gallery/`                | live, **noch keine Bilder gepflegt** |
| **Geschichte**              | `/geschichte/`      | `history/`                | live, **noch keine Kapitel gepflegt** |
| **Speisekarte**             | `/speisekarte/` (wird nicht gebaut) | `menu/`         | **derzeit deaktiviert**, [siehe unten](#speisekarte--derzeit-deaktiviert) |
| **Impressum & Datenschutz** | `/impressum/`, `/datenschutz/` | Vorlagen im Code | live |
| **Redaktionssystem**        | `/admin/`           | Decap CMS + GitHub-OAuth  | live |

### Was die Bereiche können

- **Startseite** – Titelbild, Begrüßung, kompaktes Öffnungszeiten-Widget,
  Geschichte-Teaser, die drei nächsten Veranstaltungen, alle Touren und das
  Formular „Mitglied werden“. Die redaktionellen Texte der Abschnitte stehen in
  `src/content/home/index.md`.
- **Veranstaltungen** – Übersicht mit kommenden Terminen, darunter „Bereits
  gewesen“ (die letzten sechs). Jede Veranstaltung hat eine Detailseite mit
  Fließtext und optionaler Bildergalerie. Abgesagte Termine werden nicht
  gelöscht, sondern ausgegraut und mit einem roten „Abgesagt:“ gekennzeichnet.
  Mehrtägige Veranstaltungen haben ein Enddatum.
- **Push-Benachrichtigungen** – Wer will, bekommt aufs Handy eine Mitteilung,
  sobald über das CMS eine neue Veranstaltung angelegt wird. Details weiter
  unten.
- **Aktivitäten** – Mountainbike-Touren und Wanderungen. Länge, Höhenmeter,
  Dauer und Höhenprofil werden **beim Build** aus der hochgeladenen GPX-Datei
  berechnet (`src/lib/gpx.ts`); im Browser landet nur noch der fertige
  Linienzug. Detailseite mit OpenStreetMap-Karte, Höhenprofil als SVG,
  Kennzahlen und GPX-Download.
- **Öffnungszeiten** – Wochentabelle mit Ruhetagen und Hinweisen, dazu ein
  Live-Status „Jetzt geöffnet“ / „Derzeit geschlossen“.
- **Kontakt** – Anfahrt, Kontaktdaten und eine OpenStreetMap-Karte, die erst
  auf Klick geladen wird.
- **Mitglied werden** – Formular, das über `api/membership.php` und
  PHPMailer zwei E-Mails verschickt: die Anfrage ans Schutzhaus und eine
  Bestätigung an die anfragende Person. Mit JavaScript prüft zod die Eingaben
  vorab, ohne JavaScript greift der normale POST samt Weiterleitung.
- **Galerie** – Raster nach Kategorien mit Großansicht. Collection, CMS-Maske
  und Seite stehen bereit, `src/content/gallery/` ist aber noch leer – die
  Seite bleibt deshalb vorerst ohne Bilder.
- **Geschichte** – Kapitel mit Jahr, Bild und Fließtext. Ebenfalls fertig
  angelegt, aber `src/content/history/` ist noch leer. Solange das so ist,
  blendet die Startseite auch den Geschichte-Teaser aus.

Für die beiden leeren Collections meldet der Build
`The collection "galerie"/"geschichte" does not exist or is empty` – das ist
kein Fehler, sondern verschwindet mit dem ersten Eintrag.

### Speisekarte – derzeit deaktiviert

Die Speisekarte ist **vollständig vorhanden, aber abgeschaltet**. Deaktiviert
wurde sie an drei Stellen:

| Stelle                            | Zustand                                        |
| --------------------------------- | ---------------------------------------------- |
| `src/pages/speisekarte.astro.disabled` | Endung `.disabled` – Astro baut keine Seite |
| `src/lib/site.ts`                 | Navigationseintrag auskommentiert               |
| `src/pages/index.astro`           | Block „Hausspezialitäten“ auskommentiert        |

Unberührt bleiben die 17 Einträge in `src/content/menu/`, das Schema in
`src/content.config.ts`, die CMS-Maske und die Komponenten `MenuItem` und
`AllergenLegend`. Zum Wiedereinschalten also: Datei in `speisekarte.astro`
umbenennen und die beiden auskommentierten Blöcke wieder aktivieren – inklusive
Allergen-Kennzeichnung A–R nach österreichischem Codex-Kapitel B 33.

## Projektstruktur

```
src/
├── components/       Header, Footer, Hero, Karten, Höhenprofil, Formulare …
├── content/          Redaktionelle Inhalte als Markdown
│   ├── home/             Texte der Abschnitte auf /
│   ├── events/           Veranstaltungen
│   ├── tours/            Mountainbike- und Wandertouren
│   ├── opening-hours/    Ein Eintrag je Wochentag
│   ├── menu/             Speisen und Getränke (Seite derzeit deaktiviert)
│   ├── history/          Kapitel (noch leer)
│   └── gallery/          Fotos (noch nicht angelegt)
├── content.config.ts Zod-Schemas der Content Collections
├── layouts/          BaseLayout mit SEO-Meta, JSON-LD und Web-Manifest
├── lib/              Stammdaten, GPX-Auswertung, Öffnungszeiten-Logik, …
├── pages/            Eine Datei je Route
│   └── admin/        Decap-CMS-Oberfläche → dist/admin/index.html
└── styles/           Tailwind-Theme (global.css)

public/
├── admin/config.yml  Feldkonfiguration des CMS
├── api/              PHP: Kontaktformular und Push-Benachrichtigungen
├── oauth/            PHP: OAuth-Proxy für den CMS-Login
├── gpx/              GPX-Dateien der Touren
├── icons/            App-Symbole für den Home-Bildschirm
├── images/uploads/   Vom CMS hochgeladene Bilder
├── sw.js             Service Worker der Push-Benachrichtigungen
├── site.webmanifest  Damit sich die Seite installieren lässt
├── _headers          Security-Header (nur Netlify & Cloudflare Pages)
└── robots.txt

scripts/
└── notify-event.mjs   Meldet neue Veranstaltungen an den Push-Endpunkt
```

Die CMS-Oberfläche liegt unter `src/pages/admin/index.astro` statt als statische
Datei in `public/`, weil der Astro-Dev-Server für Ordner in `public/` keinen
Directory-Index ausliefert – `/admin/` wäre lokal sonst nicht erreichbar. Der
Build erzeugt daraus wie gewohnt eine statische `dist/admin/index.html`.

## Inhalte pflegen

Sieben Collections sind im Admin-Panel editierbar:

| Collection         | Anlegen/Löschen | Besonderheit                                        |
| ------------------ | --------------- | --------------------------------------------------- |
| **Startseite**     | –               | Einzeleintrag, nur die Abschnittstexte               |
| **Veranstaltungen**| ja              | Slug `JJJJ-MM-TT-titel`, löst eine Push-Nachricht aus |
| **Aktivitäten**    | ja              | GPX-Upload, Kennzahlen werden berechnet              |
| **Öffnungszeiten** | nein            | fest sieben Einträge, Anlegen bewusst gesperrt       |
| **Speisekarte**    | ja              | pflegbar, Seite aber deaktiviert                     |
| **Geschichte**     | ja              | noch keine Einträge                                  |
| **Galerie**        | ja              | noch keine Einträge                                  |

Die Schemas in `src/content.config.ts` und die Felder in
`public/admin/config.yml` müssen zueinander passen – wird ein Feld ergänzt,
gehört es an beide Stellen.

Zwei Eigenheiten von Decap, die in den Schemas abgefangen werden: Geleerte
Felder werden als leerer String geschrieben statt entfernt (`leerAlsUndefined`),
und eine geleerte Liste kommt als `null` an (Fallback auf `[]` bei
`events.galerie`).

### Öffnungszeiten-Status

Das Widget auf Start- und Öffnungszeiten-Seite zeigt „Jetzt geöffnet“ bzw.
„Derzeit geschlossen“. Der Status wird **clientseitig** in der Zeitzone
`Europe/Vienna` berechnet (`src/components/OpeningHoursWidget.astro`), damit
er trotz statischem Build stimmt. Ohne JavaScript bleibt die Wochentabelle
sichtbar, nur das Status-Badge fehlt.

### Touren und GPX

Eine neue Tour braucht nur eine GPX-Datei; alles Weitere entsteht beim Build:

- `src/lib/gpx.ts` liest die Datei aus `public/gpx/`, berechnet Distanz,
  Höhenmeter, Dauer und Profil und vereinfacht den Linienzug für die Karte.
- Die Dauer lässt sich im CMS mit `durationMinutes` überschreiben, wenn die
  Schätzung nicht passt.
- Die Karte (`TourMap.astro`, Leaflet) lädt ihre Kacheln erst, wenn sie ins
  Sichtfeld scrollt. Ohne JavaScript wird stattdessen der GPX-Download
  angeboten.

## Stammdaten anpassen

Adresse, Telefonnummer, E-Mail, Geokoordinaten, Navigation und
Impressumsangaben stehen zentral in **`src/lib/site.ts`**. Die dort mit
`PLATZHALTER` markierten Werte sind erfunden und müssen vor dem Livegang durch
die echten Daten ersetzt werden. Sie fließen in Header, Footer, Kontaktseite,
Impressum und das JSON-LD für Suchmaschinen ein.

Das Hero-Bild ist derzeit ein generiertes SVG-Panorama
(`public/images/hero.svg`). Für ein echtes Foto die Datei ersetzen oder im CMS
unter „Startseite → Titelbild“ eines hochladen.

## Deployment

Jeder Push auf `main` – also auch jede Änderung über `/admin/` – baut die Seite
und lädt `dist/` per FTP zu Hostinger
(`.github/workflows/deploy.yml`). Die Upload-Action **löscht auf dem Server
nichts**: Gelöschte Seiten bleiben liegen, bis sie von Hand aus dem Dateimanager
entfernt werden.

Beim Deploy entstehen aus GitHub-Secrets drei Konfigurationsdateien, die nicht
im Repository stehen: `oauth/config.php`, `api/config.php` (SMTP) und
`api/push-config.php`. Welche Secrets dafür in der Environment **FTP** liegen
müssen, steht im Kopf von `deploy.yml`.

### OAuth-Authentifizierung

Der CMS-Login läuft über `public/oauth/index.php` auf derselben Domain –
gespeichert wird danach direkt über die GitHub-API aus dem Browser. In den
GitHub-App-Settings muss diese Callback-URL hinterlegt sein:

```
https://hegerberg.at/oauth
```

### PHP-Bibliotheken auf dem Server

PHPMailer (Kontaktformular) und minishlink/web-push (Benachrichtigungen) liegen
**nicht** im Repository, sondern nur auf dem Server unter
`public_html/vendor/`. Hochgeladen werden sie über den manuellen Workflow
**Actions → „Upload PHP libraries“**.

Beide stecken bewusst in einem gemeinsamen Composer-Projekt: Zwei getrennte
Projekte würden sich in `vendor/` den Autoloader überschreiben, und die jeweils
zuletzt hochgeladene Bibliothek wäre die einzige, die sich noch laden lässt.
Der Workflow muss also immer beide zusammen ausrollen.

Voraussetzung auf dem Webhosting: PHP **≥ 8.2** mit den Erweiterungen `curl`,
`mbstring`, `openssl` und `json`. `gmp` oder `bcmath` sind optional und
beschleunigen nur die Signatur.

## Push-Benachrichtigungen

Wird über `/admin/` eine neue Veranstaltung angelegt, bekommen alle Geräte, die
das abonniert haben, eine Mitteilung. Der Weg dorthin:

1. **Deploy** läuft wie immer und stellt die neue Seite online.
2. **„Notify about events“** startet danach automatisch
   (`workflow_run`), vergleicht den Commit mit seinem Vorgänger und sammelt
   alle **neu hinzugekommenen** Dateien unter `src/content/events/`.
3. `scripts/notify-event.mjs` schickt Titel, Datum und Kurzbeschreibung
   an `https://hegerberg.at/api/push-send.php`.
4. Das PHP-Script verschlüsselt die Nachricht für jedes gespeicherte Gerät und
   übergibt sie an die Push-Dienste von Google, Mozilla und Apple.

Geändert oder gelöscht wird nichts gemeldet – nur neue Dateien. Eine
Veranstaltung, die schon beim Anlegen als *abgesagt* markiert ist, wird
übersprungen.

> Der Melde-Workflow greift erst, wenn er auf `main` liegt: GitHub liest
> `workflow_run`-Workflows ausschließlich vom Default-Branch.

### Beteiligte Dateien

| Datei                                        | Aufgabe                                                      |
| -------------------------------------------- | ------------------------------------------------------------ |
| `src/components/PushSignup.astro`         | Ein-/Ausschalter auf `/veranstaltungen/`                       |
| `public/sw.js`                               | Service Worker: zeigt die Mitteilung, öffnet die Detailseite   |
| `public/api/push.php`                        | An- und Abmeldung der Geräte                                   |
| `public/api/push-send.php`                | Versand, nur mit `PUSH_VERSAND_TOKEN` erreichbar               |
| `public/api/push-storage.php`               | Konfiguration und Ablage, von beiden Endpunkten genutzt        |
| `scripts/notify-event.mjs`           | Baut die Nachricht aus dem Frontmatter                         |
| `.github/workflows/notify-event.yml` | Trigger nach erfolgreichem Deploy                              |

### Wo die Abonnenten liegen

In `push-daten/subscriptions.json` – eine Ebene **über** `public_html`, damit die Datei
über den Browser nicht erreichbar ist. Lässt sich das Verzeichnis dort nicht
anlegen, weicht das Script auf `public_html/api/push-daten/` aus und sperrt es
per `.htaccess`. Angelegt wird es beim ersten Zugriff von selbst.

Gespeichert wird ausschließlich die technische Adresse des Browsers samt den
beiden Schlüsseln zur Verschlüsselung – kein Name, keine E-Mail-Adresse. Meldet
ein Push-Dienst ein Gerät als abgemeldet (HTTP 404/410), fliegt der Eintrag beim
nächsten Versand automatisch raus.

Die Datei gehört **nicht** ins Repository und steht darum in `.gitignore`. Ein
Deploy überschreibt sie auch nicht: Die FTP-Action überträgt nur `dist/`.

### Einrichtung (einmalig)

**1. VAPID-Schlüsselpaar erzeugen.** Es weist die Website gegenüber den
Push-Diensten aus:

```bash
bunx web-push generate-vapid-keys
```

Ohne npm-Paket tut es auch OpenSSL:

```bash
openssl ecparam -genkey -name prime256v1 -noout -out vapid.pem
# privat (43 Zeichen):
openssl ec -in vapid.pem -outform DER | tail -c +8 | head -c 32 \
  | base64 | tr -d '=\n' | tr '/+' '_-'; echo
# öffentlich (87 Zeichen, beginnt mit „B“):
openssl ec -in vapid.pem -pubout -outform DER | tail -c 65 \
  | base64 | tr -d '=\n' | tr '/+' '_-'; echo
```

> Das Schlüsselpaar wird **einmal** erzeugt und danach nie gewechselt – ein
> neues Paar macht sämtliche bestehenden Abonnements ungültig, alle müssten sich
> neu anmelden.

**2. Versand-Token erzeugen** – das gemeinsame Geheimnis zwischen GitHub Action
und Server:

```bash
openssl rand -hex 32
```

**3. Drei Secrets hinterlegen** unter Settings → Environments → **FTP** →
Environment secrets:

| Secret               | Wert                              |
| -------------------- | --------------------------------- |
| `VAPID_PUBLIC_KEY`   | öffentlicher Schlüssel aus 1.     |
| `VAPID_PRIVATE_KEY`  | privater Schlüssel aus 1.         |
| `PUSH_VERSAND_TOKEN` | Token aus 2.                      |

Optional als *Environment variable*: `VAPID_SUBJECT` (Voreinstellung
`mailto:office@hegerberg.at`) und `PUSH_ENDPUNKT`, falls der Endpunkt einmal
woanders liegt.

Fehlt eines der drei Secrets, läuft der Deploy trotzdem grün durch und meldet
nur eine Warnung – die Website funktioniert dann vollständig, lediglich die
Benachrichtigungen bleiben aus.

**4. Workflow „Upload PHP libraries“** einmal starten, damit web-push auf
dem Server liegt.

**5. Deploy anstoßen** (Push auf `main` oder Actions → Deploy → Run workflow).
Dabei entsteht `api/push-config.php` aus den Secrets.

**6. Testen:** `/veranstaltungen/` öffnen, Benachrichtigungen einschalten, dann
von Hand eine Nachricht schicken:

```bash
curl -X POST https://hegerberg.at/api/push-send.php \
  -H "Authorization: Bearer $PUSH_VERSAND_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"title":"Testnachricht","text":"Sieht gut aus.","url":"/veranstaltungen/"}'
```

Ohne `key` im Aufruf greift die Wiederholungssperre nicht – so lässt sich
beliebig oft testen. Die Antwort nennt Empfänger, Zustellungen und die Zahl der
aufgeräumten Geräte.

### Wiederholungssperre

`api/push-send.php` merkt sich jeden versendeten Slug in
`push-daten/sent.json` (die letzten 200). Damit löst weder ein von Hand
wiederholter Deploy noch ein neuer Lauf des Melde-Workflows eine zweite
Nachricht zur selben Veranstaltung aus.

### iPhone und iPad

Apple erlaubt Web-Push nur, wenn die Seite auf dem Home-Bildschirm liegt: in
Safari auf **Teilen → Zum Home-Bildschirm**, danach die Seite von dort öffnen
und dort einschalten. Dafür gibt es `public/site.webmanifest` und die Symbole in
`public/icons/`. Der Schalter auf `/veranstaltungen/` blendet auf iOS-Geräten
den passenden Hinweis ein, solange das nicht passiert ist.

### Lokal ausprobieren

Push braucht HTTPS oder `localhost` – über den Astro-Dev-Server auf
`http://localhost:4321` funktioniert es also. Die PHP-Endpunkte laufen dort
allerdings nicht; dafür `public/` mit `php -S 127.0.0.1:8099` ausliefern, eine
`public/api/push-config.php` nach dem Muster von `push-config.example.php`
anlegen und die Bibliotheken lokal per Composer nach `public/vendor/`
installieren.

## Suchmaschinen und Metadaten

`src/layouts/BaseLayout.astro` setzt Titel, Beschreibung, Canonical-URL,
Open-Graph-Angaben und ein JSON-LD vom Typ `Restaurant` samt Adresse,
Geokoordinaten und Öffnungszeiten. `@astrojs/sitemap` erzeugt beim Build eine
Sitemap und lässt `/admin/` bewusst aus.

## Datenschutz-Hinweise

- Die Karte auf der **Kontaktseite** lädt OpenStreetMap erst nach explizitem
  Klick.
- Die Karten auf den **Tour-Detailseiten** laden ihre Kacheln dagegen
  automatisch, sobald sie ins Sichtfeld scrollen – dabei geht die IP-Adresse an
  OpenStreetMap. Wer das auch dort erst nach Zustimmung möchte, müsste
  `TourMap.astro` auf denselben Klick-Mechanismus umstellen wie die
  Kontaktseite.
- Push-Benachrichtigungen werden nur nach ausdrücklichem Einschalten und
  Browser-Erlaubnis abonniert. Gespeichert wird allein die vom Browser erzeugte
  Abo-Adresse samt Verschlüsselungsschlüsseln.
- Die Schriften kommen aktuell von Google Fonts (`src/layouts/BaseLayout.astro`).
  Für vollständige DSGVO-Konformität sollten sie selbst gehostet werden.
- Keine Tracking-Cookies, keine Analyse-Tools, keine Werbenetzwerke.
- `src/pages/datenschutz.astro` und `src/pages/impressum.astro` sind Vorlagen
  und ersetzen keine Rechtsberatung.
