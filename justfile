# Hegerberg.at – Aufgaben für die lokale Entwicklung.
#
#   just          Dev-Server + CMS-Proxy starten
#   just -l       alle Rezepte auflisten
#
# Voraussetzung: bun (https://bun.sh) und just.

set shell := ["bash", "-uc"]

cms_config := "public/admin/config.yml"
site_url := "http://localhost:4321"
admin_url := "http://localhost:4321/admin/"

# Dev-Umgebung starten (Standard)
default: dev

# Astro-Dev-Server und Decap-CMS-Proxy zusammen starten – Strg+C beendet beide
dev: deps local-backend-on
    #!/usr/bin/env bash
    set -euo pipefail
    # Beim Beenden die ganze Prozessgruppe mitnehmen, sonst bleibt decap-server hängen.
    trap 'kill 0' EXIT
    echo "→ Website: {{site_url}}"
    echo "→ CMS:     {{admin_url}}  (local_backend aktiv – Änderungen gehen direkt ins Dateisystem)"
    echo
    bun run cms &
    bun run dev

# Nur den Astro-Dev-Server
dev-only: deps
    bun run dev

# Nur den Decap-CMS-Proxy (Port 8081)
cms: deps local-backend-on
    bun run cms

# local_backend im CMS-Config einschalten (Decap schreibt lokal statt über GitHub)
local-backend-on:
    #!/usr/bin/env bash
    set -euo pipefail
    sed -i 's/^local_backend:.*/local_backend: true/' {{cms_config}}
    grep -q '^local_backend: true$' {{cms_config}} \
        || { echo "Schlüssel local_backend fehlt in {{cms_config}}" >&2; exit 1; }

# local_backend ausschalten – das CMS spricht dann auch lokal mit GitHub
local-backend-off:
    #!/usr/bin/env bash
    set -euo pipefail
    sed -i 's/^local_backend:.*/local_backend: false/' {{cms_config}}

# Abhängigkeiten installieren, falls node_modules fehlt
deps:
    #!/usr/bin/env bash
    set -euo pipefail
    [ -d node_modules ] || bun install

# Abhängigkeiten neu installieren
install:
    bun install

# Produktions-Build nach dist/
build: deps
    bun run build

# Produktions-Build lokal ansehen
preview: build
    bun run preview

# Astro- und TypeScript-Prüfung
check: deps
    bunx astro check

# Build-Artefakte und Astro-Cache löschen
clean:
    rm -rf dist .astro
