# hegerberg.at

Website für das **Schutzhaus am Hegerberg** (Stössing, Bezirk St. Pölten Land).
Statisch generiert mit [Astro](https://astro.build), redaktionell gepflegt über
[Decap CMS](https://decapcms.org). Package Manager und Runtime: **Bun**.

## Schnellstart

```bash
bun install
bun run dev          # → http://localhost:4321
```

Für das CMS zusätzlich in einem **zweiten Terminal**:

```bash
bun run cms          # decap-server auf Port 8081
```

Danach ist das Redaktionssystem unter <http://localhost:4321/admin/> erreichbar.
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
├── images/uploads/   Vom CMS hochgeladene Bilder
├── _headers          Security-Header (Netlify & Cloudflare Pages)
└── robots.txt
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

### Netlify (empfohlen, weil Git Gateway integriert)

1. Repository verbinden – `netlify.toml` liefert Build-Command und Publish-Verzeichnis.
2. **Site configuration → Identity** aktivieren.
3. Registration auf **Invite only** stellen.
4. **Services → Git Gateway** aktivieren.
5. Redakteur:innen über **Invite users** einladen.

Danach ist `/admin/` mit dem Netlify-Identity-Login nutzbar.

### Cloudflare Pages

Build-Command `bun run build`, Output-Verzeichnis `dist`. `public/_headers` wird
automatisch übernommen. Da Cloudflare kein Git Gateway anbietet, muss das
Backend in `public/admin/config.yml` auf GitHub OAuth umgestellt werden:

```yaml
backend:
  name: github
  repo: <organisation>/<repository>
  branch: main
  base_url: https://<eigener-oauth-proxy>
```

## Datenschutz-Hinweise

- Die Karte auf der Kontaktseite lädt OpenStreetMap erst nach explizitem Klick.
- Die Schriften kommen aktuell von Google Fonts (`src/layouts/BaseLayout.astro`).
  Für vollständige DSGVO-Konformität sollten sie selbst gehostet werden.
- `src/pages/datenschutz.astro` und `src/pages/impressum.astro` sind Vorlagen
  und ersetzen keine Rechtsberatung.
