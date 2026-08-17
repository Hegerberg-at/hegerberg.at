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
