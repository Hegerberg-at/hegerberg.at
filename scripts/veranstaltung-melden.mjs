/*
 * Meldet neu angelegte Veranstaltungen an den Push-Endpunkt der Website.
 *
 * Aufgerufen wird das Script aus .github/workflows/veranstaltung-melden.yml,
 * das die neuen Dateien aus dem letzten Commit ermittelt:
 *
 *   node scripts/veranstaltung-melden.mjs src/content/events/2026-09-06-bergmesse.md …
 *
 * Erwartet wird die Umgebungsvariable PUSH_VERSAND_TOKEN, optional PUSH_ENDPUNKT
 * (Voreinstellung https://hegerberg.at/api/push-versand.php).
 *
 * Die Wiederholungssperre sitzt auf dem Server: Als Schlüssel geht der Slug der
 * Veranstaltung mit, und derselbe Schlüssel wird dort nur einmal versendet.
 */

import { readFile } from 'node:fs/promises';
import { basename } from 'node:path';
import { parse as yamlParsen } from 'yaml';

const ENDPUNKT = process.env.PUSH_ENDPUNKT ?? 'https://hegerberg.at/api/push-versand.php';
const TOKEN = process.env.PUSH_VERSAND_TOKEN ?? '';

/** Kurzbeschreibungen werden in der Benachrichtigung nur angerissen. */
const MAX_TEXT = 140;

const datumsformat = new Intl.DateTimeFormat('de-AT', {
  day: 'numeric',
  month: 'long',
  year: 'numeric',
  timeZone: 'Europe/Vienna',
});

/**
 * Liest das YAML-Frontmatter einer Markdown-Datei.
 *
 * @returns {Promise<Record<string, unknown> | null>}
 */
async function frontmatter(pfad) {
  const inhalt = await readFile(pfad, 'utf8');
  const treffer = /^---\r?\n([\s\S]*?)\r?\n---/.exec(inhalt);
  if (!treffer) return null;

  const daten = yamlParsen(treffer[1]);

  return daten && typeof daten === 'object' ? daten : null;
}

/**
 * „6. September 2026“ bzw. „24.–25. Oktober 2026“ bei mehrtägigen Terminen –
 * formatRange kürzt die gemeinsamen Bestandteile selbst weg.
 */
function zeitraum(datum, datumBis) {
  const beginn = new Date(datum);
  if (Number.isNaN(beginn.getTime())) return '';

  const ende = datumBis ? new Date(datumBis) : null;
  const mehrtaegig =
    ende && !Number.isNaN(ende.getTime()) && ende.getTime() !== beginn.getTime();

  return mehrtaegig
    ? datumsformat.formatRange(beginn, ende)
    : datumsformat.format(beginn);
}

/**
 * Baut die Benachrichtigung zu einer Veranstaltungsdatei.
 *
 * @returns {Promise<{schluessel: string, titel: string, text: string, url: string} | null>}
 */
async function benachrichtigung(pfad) {
  const daten = await frontmatter(pfad);
  if (!daten?.titel) {
    console.log(`· ${pfad}: kein Titel im Frontmatter – übersprungen`);
    return null;
  }

  // Eine Veranstaltung, die schon beim Anlegen abgesagt ist, muss niemand
  // aufs Handy bekommen.
  if (daten.abgesagt === true) {
    console.log(`· ${pfad}: als abgesagt angelegt – übersprungen`);
    return null;
  }

  // Der Slug entspricht dem Dateinamen ohne Endung – so bildet Astro auch die
  // Adresse der Detailseite (src/pages/veranstaltungen/[slug].astro).
  const slug = basename(pfad, '.md');

  const wann = zeitraum(daten.datum, daten.datumBis);
  const uhrzeit = typeof daten.uhrzeit === 'string' && daten.uhrzeit ? `, ${daten.uhrzeit} Uhr` : '';
  const beschreibung = String(daten.beschreibung ?? '')
    .replace(/\s+/g, ' ')
    .trim();

  const text = [wann + uhrzeit, beschreibung].filter(Boolean).join(' · ');

  return {
    schluessel: slug,
    titel: `Neue Veranstaltung: ${daten.titel}`,
    text: text.length > MAX_TEXT ? `${text.slice(0, MAX_TEXT - 1).trimEnd()}…` : text,
    url: `/veranstaltungen/${slug}/`,
  };
}

const dateien = process.argv.slice(2).filter((pfad) => pfad.endsWith('.md'));

if (dateien.length === 0) {
  console.log('Keine neuen Veranstaltungen – nichts zu tun.');
  process.exit(0);
}

if (!TOKEN) {
  console.error('PUSH_VERSAND_TOKEN fehlt – ohne Token nimmt der Server nichts an.');
  process.exit(1);
}

let fehlgeschlagen = 0;

for (const pfad of dateien) {
  const nachricht = await benachrichtigung(pfad);
  if (!nachricht) continue;

  const antwort = await fetch(ENDPUNKT, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${TOKEN}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(nachricht),
  });

  const ergebnis = await antwort.text();

  if (!antwort.ok) {
    // Weitermachen: Eine fehlgeschlagene Veranstaltung soll die übrigen nicht
    // blockieren. Am Ende endet der Job trotzdem mit einem Fehler.
    console.error(`✗ ${nachricht.schluessel}: HTTP ${antwort.status} – ${ergebnis}`);
    fehlgeschlagen++;
    continue;
  }

  console.log(`✓ ${nachricht.schluessel}: ${ergebnis}`);
}

process.exit(fehlgeschlagen > 0 ? 1 : 0);
