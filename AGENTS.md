## Code is English, content is German

Never write German code. No `klasse` instead of `class`, no `berechneSumme`,
no `$anfrage`, no `text-holz-950`. Everything the compiler, the browser or a
developer reads is English:

- variable, function, type, class and property names
- file and directory names
- CSS classes and design tokens
- code comments and JSDoc
- data attributes, HTML `id`s and SVG ids
- frontmatter keys of the content collections, and the matching `name:` fields
  in `public/admin/config.yml`
- JSON keys of the PHP endpoints, form field names and config keys

German stays only where a human reader sees it:

- all content and UI text (`Öffnungszeiten ansehen`, `Diese Veranstaltung wurde
  abgesagt.`)
- public URLs (`/veranstaltungen/`, `/aktivitaeten/`) and content slugs
- the CMS interface: `label`, `label_singular`, `hint`, `description` in
  `public/admin/config.yml`
- enum *values* that are rendered as they are (`Montag`, `Wanderung`, `mittel`,
  `Hauptspeisen`)
- error and status messages shown to visitors
- `README.md` and this file

Two exceptions carry a comment explaining themselves, do not "clean them up":
the data directory `push-daten/` and the legacy key `endpunkt` in
`public/api/push-send.php` — both point at live data on the server.

## Never commit `local_backend: true`

`public/admin/config.yml` must always be committed with `local_backend: false`.

Running `just`, `just dev` or `just cms` flips it to `true` through the
`local-backend-on` recipe, so it changes without anyone editing the file. If
that lands on `main`, the live CMS at hegerberg.at/admin/ looks for a local
proxy on port 8081 instead of talking to GitHub — editors are locked out.

Before every commit, check the flag and reset it if needed:

```
grep '^local_backend' public/admin/config.yml   # must read: local_backend: false
just local-backend-off                          # resets it
```

Also check it explicitly whenever you stage with `git add -A`, and never let it
slip into a commit as an unrelated side change.

## Development

When starting the dev server, use background mode:

```
astro dev --background
```

Manage the background server with `astro dev stop`, `astro dev status`, and `astro dev logs`.

## Documentation

Full documentation: https://docs.astro.build

Consult these guides before working on related tasks:

- [Adding pages, dynamic routes, or middleware](https://docs.astro.build/en/guides/routing/)
- [Working with Astro components](https://docs.astro.build/en/basics/astro-components/)
- [Using React, Vue, Svelte, or other framework components](https://docs.astro.build/en/guides/framework-components/)
- [Adding or managing content](https://docs.astro.build/en/guides/content-collections/)
- [Adding styles or using Tailwind](https://docs.astro.build/en/guides/styling/)
- [Supporting multiple languages](https://docs.astro.build/en/guides/internationalization/)
