/*
 * Reports newly created events to the push endpoint of the website.
 *
 * The script is called from .github/workflows/notify-event.yml, which works
 * out the new files of the last commit:
 *
 *   node scripts/notify-event.mjs src/content/events/2026-09-06-bergmesse.md …
 *
 * The environment variable PUSH_SEND_TOKEN is required, PUSH_ENDPOINT is
 * optional (default https://hegerberg.at/api/push-send.php).
 *
 * The replay guard sits on the server: the slug of the event goes along as the
 * key, and the same key is only ever sent once.
 */

import { readFile } from 'node:fs/promises';
import { basename } from 'node:path';
import { parse as parseYaml } from 'yaml';

const ENDPOINT = process.env.PUSH_ENDPOINT ?? 'https://hegerberg.at/api/push-send.php';
const TOKEN = process.env.PUSH_SEND_TOKEN ?? '';

/** Short descriptions are only teased in the notification. */
const MAX_TEXT = 140;

const dateFormat = new Intl.DateTimeFormat('de-AT', {
  day: 'numeric',
  month: 'long',
  year: 'numeric',
  timeZone: 'Europe/Vienna',
});

/**
 * Reads the YAML frontmatter of a markdown file.
 *
 * @returns {Promise<Record<string, unknown> | null>}
 */
async function frontmatter(path) {
  const content = await readFile(path, 'utf8');
  const match = /^---\r?\n([\s\S]*?)\r?\n---/.exec(content);
  if (!match) return null;

  const data = parseYaml(match[1]);

  return data && typeof data === 'object' ? data : null;
}

/**
 * „6. September 2026“ resp. „24.–25. Oktober 2026“ for multi-day events –
 * formatRange drops the shared parts on its own.
 */
function dateRange(date, endDate) {
  const start = new Date(date);
  if (Number.isNaN(start.getTime())) return '';

  const end = endDate ? new Date(endDate) : null;
  const multiDay =
    end && !Number.isNaN(end.getTime()) && end.getTime() !== start.getTime();

  return multiDay
    ? dateFormat.formatRange(start, end)
    : dateFormat.format(start);
}

/**
 * Builds the notification for one event file.
 *
 * @returns {Promise<{key: string, title: string, text: string, url: string} | null>}
 */
async function notification(path) {
  const data = await frontmatter(path);
  if (!data?.title) {
    console.log(`· ${path}: kein Titel im Frontmatter – übersprungen`);
    return null;
  }

  // An event that is already cancelled when it is created does not need to
  // reach anybody's phone.
  if (data.cancelled === true) {
    console.log(`· ${path}: als abgesagt angelegt – übersprungen`);
    return null;
  }

  // The slug equals the file name without the extension – that is how Astro
  // builds the address of the detail page too
  // (src/pages/veranstaltungen/[slug].astro).
  const slug = basename(path, '.md');

  const when = dateRange(data.date, data.endDate);
  const time = typeof data.time === 'string' && data.time ? `, ${data.time} Uhr` : '';
  const description = String(data.description ?? '')
    .replace(/\s+/g, ' ')
    .trim();

  const text = [when + time, description].filter(Boolean).join(' · ');

  return {
    key: slug,
    title: `Neue Veranstaltung: ${data.title}`,
    text: text.length > MAX_TEXT ? `${text.slice(0, MAX_TEXT - 1).trimEnd()}…` : text,
    url: `/veranstaltungen/${slug}/`,
  };
}

const files = process.argv.slice(2).filter((path) => path.endsWith('.md'));

if (files.length === 0) {
  console.log('Keine neuen Veranstaltungen – nichts zu tun.');
  process.exit(0);
}

if (!TOKEN) {
  console.error('PUSH_SEND_TOKEN fehlt – ohne Token nimmt der Server nichts an.');
  process.exit(1);
}

let failed = 0;

for (const path of files) {
  const message = await notification(path);
  if (!message) continue;

  const response = await fetch(ENDPOINT, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${TOKEN}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(message),
  });

  const result = await response.text();

  if (!response.ok) {
    // Keep going: one failed event should not block the rest. The job still
    // ends with an error at the end.
    console.error(`✗ ${message.key}: HTTP ${response.status} – ${result}`);
    failed++;
    continue;
  }

  console.log(`✓ ${message.key}: ${result}`);
}

process.exit(failed > 0 ? 1 : 0);
