# Hegerberg.at – tasks for local development.
#
#   just          start the dev server + CMS proxy
#   just -l       list all recipes
#
# Requires: bun (https://bun.sh) and just.

set shell := ["bash", "-uc"]

cms_config := "public/admin/config.yml"
site_url := "http://localhost:4321"
admin_url := "http://localhost:4321/admin/"

# Start the dev environment (default)
default: dev

# Start the Astro dev server and the Decap CMS proxy together – Ctrl+C stops both
dev: deps local-backend-on
    #!/usr/bin/env bash
    set -euo pipefail
    # Take the whole process group down on exit, otherwise decap-server lingers.
    trap 'kill 0' EXIT
    echo "→ Website: {{site_url}}"
    echo "→ CMS:     {{admin_url}}  (local_backend aktiv – Änderungen gehen direkt ins Dateisystem)"
    echo
    bun run cms &
    bun run dev

# Only the Astro dev server
dev-only: deps
    bun run dev

# Only the Decap CMS proxy (port 8081)
cms: deps local-backend-on
    bun run cms

# Turn on local_backend in the CMS config (Decap writes locally instead of through GitHub)
local-backend-on:
    #!/usr/bin/env bash
    set -euo pipefail
    sed -i 's/^local_backend:.*/local_backend: true/' {{cms_config}}
    grep -q '^local_backend: true$' {{cms_config}} \
        || { echo "Schlüssel local_backend fehlt in {{cms_config}}" >&2; exit 1; }

# Turn off local_backend – the CMS then talks to GitHub locally as well
local-backend-off:
    #!/usr/bin/env bash
    set -euo pipefail
    sed -i 's/^local_backend:.*/local_backend: false/' {{cms_config}}

# Install dependencies when node_modules is missing
deps:
    #!/usr/bin/env bash
    set -euo pipefail
    [ -d node_modules ] || bun install

# Reinstall dependencies
install:
    bun install

# Production build into dist/
build: deps
    bun run build

# Preview the production build locally
preview: build
    bun run preview

# Astro and TypeScript checks
check: deps
    bunx astro check

# Delete build artefacts and the Astro cache
clean:
    rm -rf dist .astro
