# hegerberg.at

Website für das **Schutzhaus am Hegerberg** (Stössing, Bezirk St. Pölten Land)..
Statisch generiert mit [Astro](https://astro.build), redaktionell gepflegt über
[Decap CMS](https://decapcms.org). Package Manager und Runtime: **Bun**.

## Schnellstart

```bash
bun install
bun run dev          # → https://hegerberg.at
```

Für das CMS zusätzlich in einem **zweiten Terminal**:

```bash
bun run cms          # decap-server auf Port 8081
```



Danach ist das Redaktionssystem unter <https://hegerberg.at/admin/> erreichbar.
Durch `local_backend: true` in `public/admin/config.yml` schreibt Decap dabei
direkt in die Markdown-Dateien im Repository – **ohne Git-Login**.

| Befehl            | Wirkung                                    |
| ----------------- | ------------------------------------------ |
| `bun run dev`     | Dev-Server mit Hot Reload                  |
| `bun run build`   | Produktions-Build nach `dist/`             |
| `bun run preview` | Build lokal ausliefern                     |
| `bun run cms`     | Decap-Proxy für die lokale CMS-Bearbeitung |

## Projektstruktur

```
src/
├── components/       Header, Footer, Hero, Öffnungszeiten-Widget, Karten …
├── content/          Redaktionelle Inhalte als Markdown
│   ├── speisekarte/
│   ├── oeffnungszeiten/
│   ├── geschichte/
│   └── events/
├── content.config.ts Zod-Schemas der Content Collections
├── layouts/          BaseLayout mit SEO-Meta und JSON-LD
├── lib/              Stammdaten, Allergene, Formatierung, Öffnungszeiten-Logik
├── pages/            Eine Datei je Route
│   └── admin/        Decap-CMS-Oberfläche → dist/admin/index.html
└── styles/           Tailwind-Theme (global.css)

public/
├── admin/config.yml  Feldkonfiguration des CMS
├── api/              PHP-Endpunkte: Kontaktformular und Push-Benachrichtigungen
├── icons/            App-Symbole für den Home-Bildschirm
├── images/uploads/   Vom CMS hochgeladene Bilder
├── oauth/            OAuth-Proxy für den CMS-Login
├── sw.js             Service Worker für die Push-Benachrichtigungen
├── site.webmanifest  Damit sich die Seite installieren lässt
├── _headers          Security-Header (Netlify & Cloudflare Pages)
└── robots.txt

scripts/
└── veranstaltung-melden.mjs   Schickt neue Veranstaltungen an den Push-Endpunkt
```

Die CMS-Oberfläche liegt unter `src/pages/admin/index.astro` statt als statische
Datei in `public/`, weil der Astro-Dev-Server für Ordner in `public/` keinen
Directory-Index ausliefert – `/admin/` wäre lokal sonst nicht erreichbar. Der
Build erzeugt daraus wie gewohnt eine statische `dist/admin/index.html`.

## Inhalte pflegen

Alle vier Collections sind im Admin-Panel editierbar:

- **Speisekarte** – Kategorie, Bezeichnung, Beschreibung, Preis, Allergene (A–R),
  Verfügbarkeit, Hausspezialität, Sortierung
- **Öffnungszeiten** – ein Eintrag je Wochentag, mit Ruhetag-Flag und
  Schlechtwetter-Hinweis. Anlegen und Löschen ist bewusst deaktiviert.
- **Geschichte** – Kapitel mit Jahr, Kurzbeschreibung, Bild und Fließtext
- **Veranstaltungen** – Titel, Datum (optional Enddatum), Uhrzeit, Bild,
  Absage-Flag

Die Schemas in `src/content.config.ts` und die Felder in
`public/admin/config.yml` müssen zueinander passen – wird ein Feld ergänzt,
gehört es an beide Stellen.

### Öffnungszeiten-Status

Das Widget auf Start- und Öffnungszeiten-Seite zeigt „Jetzt geöffnet“ bzw.
„Derzeit geschlossen“. Der Status wird **clientseitig** in der Zeitzone
`Europe/Vienna` berechnet (`src/components/OeffnungszeitenWidget.astro`), damit
er trotz statischem Build stimmt. Ohne JavaScript bleibt die Wochentabelle
sichtbar, nur das Status-Badge fehlt.

## Stammdaten anpassen

Adresse, Telefonnummer, E-Mail, Geokoordinaten und Impressumsangaben stehen
zentral in **`src/lib/site.ts`**. Die dort mit `PLATZHALTER` markierten Werte
sind erfunden und müssen vor dem Livegang durch die echten Daten ersetzt werden.
Sie fließen in Header, Footer, Kontaktseite, Impressum und das JSON-LD für
Suchmaschinen ein.

Das Hero-Bild ist derzeit ein generiertes SVG-Panorama
(`public/images/hero.svg`). Für ein echtes Foto die Datei ersetzen oder in
`src/pages/index.astro` `<Hero bild="/images/foto.jpg" />` setzen.

## Deployment

Das Projekt wird bei **Hostinger** gehostet und nutzt **GitHub** als CMS-Backend.

### OAuth-Authentifizierung

Für die Authentifizierung über GitHub ist ein PHP-Script erforderlich:

- **Speicherort:** `public/oauth/`
- **Aufgabe:** Verarbeitet den OAuth-Flow zwischen Decap CMS und GitHub

### GitHub-Konfiguration

In den **GitHub App-Settings** muss folgende Redirect-/Callback-URL hinterlegt
werden:

```
https://hegerberg.at/oauth
```

### PHP-Bibliotheken auf dem Server

PHPMailer (Kontaktformular) und minishlink/web-push (Benachrichtigungen) liegen
**nicht** im Repository, sondern nur auf dem Server unter
`public_html/vendor/`. Hochgeladen werden sie über den manuellen Workflow
**Actions → „PHP-Bibliotheken hochladen“**.

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
2. **„Veranstaltung melden“** startet danach automatisch
   (`workflow_run`), vergleicht den Commit mit seinem Vorgänger und sammelt
   alle **neu hinzugekommenen** Dateien unter `src/content/events/`.
3. `scripts/veranstaltung-melden.mjs` schickt Titel, Datum und Kurzbeschreibung
   an `https://hegerberg.at/api/push-versand.php`.
4. Das PHP-Script verschlüsselt die Nachricht für jedes gespeicherte Gerät und
   übergibt sie an die Push-Dienste von Google, Mozilla und Apple.

Geändert oder gelöscht wird nichts gemeldet – nur neue Dateien. Eine
Veranstaltung, die schon beim Anlegen als *abgesagt* markiert ist, wird
übersprungen.

### Beteiligte Dateien

| Datei                                        | Aufgabe                                                      |
| -------------------------------------------- | ------------------------------------------------------------ |
| `src/components/PushAnmeldung.astro`         | Ein-/Ausschalter auf `/veranstaltungen/`                       |
| `public/sw.js`                               | Service Worker: zeigt die Mitteilung, öffnet die Detailseite   |
| `public/api/push.php`                        | An- und Abmeldung der Geräte                                   |
| `public/api/push-versand.php`                | Versand, nur mit `PUSH_VERSAND_TOKEN` erreichbar               |
| `public/api/push-speicher.php`               | Konfiguration und Ablage, von beiden Endpunkten genutzt        |
| `scripts/veranstaltung-melden.mjs`           | Baut die Nachricht aus dem Frontmatter                         |
| `.github/workflows/veranstaltung-melden.yml` | Trigger nach erfolgreichem Deploy                              |

### Wo die Abonnenten liegen

In `push-daten/abos.json` – eine Ebene **über** `public_html`, damit die Datei
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

**4. Workflow „PHP-Bibliotheken hochladen“** einmal starten, damit web-push auf
dem Server liegt.

**5. Deploy anstoßen** (Push auf `main` oder Actions → Deploy → Run workflow).
Dabei entsteht `api/push-config.php` aus den Secrets.

**6. Testen:** `/veranstaltungen/` öffnen, Benachrichtigungen einschalten, dann
von Hand eine Nachricht schicken:

```bash
curl -X POST https://hegerberg.at/api/push-versand.php \
  -H "Authorization: Bearer $PUSH_VERSAND_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"titel":"Testnachricht","text":"Sieht gut aus.","url":"/veranstaltungen/"}'
```

Ohne `schluessel` im Aufruf greift die Wiederholungssperre nicht – so lässt sich
beliebig oft testen. Die Antwort nennt Empfänger, Zustellungen und die Zahl der
aufgeräumten Geräte.

### Wiederholungssperre

`api/push-versand.php` merkt sich jeden versendeten Slug in
`push-daten/versendet.json` (die letzten 200). Damit löst weder ein von Hand
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

## Datenschutz-Hinweise

- Die Karte auf der Kontaktseite lädt OpenStreetMap erst nach explizitem Klick.
- Push-Benachrichtigungen werden nur nach ausdrücklichem Einschalten und
  Browser-Erlaubnis abonniert. Gespeichert wird allein die vom Browser erzeugte
  Abo-Adresse samt Verschlüsselungsschlüsseln – siehe
  `src/pages/datenschutz.astro`.
- Die Schriften kommen aktuell von Google Fonts (`src/layouts/BaseLayout.astro`).
  Für vollständige DSGVO-Konformität sollten sie selbst gehostet werden.
- `src/pages/datenschutz.astro` und `src/pages/impressum.astro` sind Vorlagen
  und ersetzen keine Rechtsberatung.
