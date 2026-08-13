// @ts-check
import { defineConfig } from 'astro/config';
import sitemap from '@astrojs/sitemap';
import tailwindcss from '@tailwindcss/vite';

// https://astro.build/config
export default defineConfig({
  site: 'https://hegerberg.at',
  output: 'static',
  trailingSlash: 'ignore',
  integrations: [
    sitemap({
      // Das Admin-Panel gehört nicht in die Sitemap.
      filter: (seite) => !seite.includes('/admin'),
    }),
  ],
  vite: {
    plugins: [tailwindcss()],
  },
});
