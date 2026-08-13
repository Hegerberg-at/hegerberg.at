import { defineCollection, z } from 'astro:content';
import { glob } from 'astro/loaders';

/**
 * Österreichische Allergen-Kennzeichnung (Codex-Kapitel B 33).
 * Wird auch in src/lib/allergene.ts für die Legende verwendet.
 */
const ALLERGEN_CODES = [
  'A',
  'B',
  'C',
  'D',
  'E',
  'F',
  'G',
  'H',
  'L',
  'M',
  'N',
  'O',
  'P',
  'R',
] as const;

const WOCHENTAGE = [
  'Montag',
  'Dienstag',
  'Mittwoch',
  'Donnerstag',
  'Freitag',
  'Samstag',
  'Sonntag',
] as const;

/** Wiederkehrender Aufbau eines Abschnitts-Titels (siehe SectionHeading.astro). */
const abschnitt = z.object({
  eyebrow: z.string().optional(),
  titel: z.string(),
  text: z.string().optional(),
});

/** Einzelner Eintrag – die redaktionellen Texte der Startseite. */
const startseite = defineCollection({
  loader: glob({ base: './src/content/startseite', pattern: '**/*.md' }),
  schema: z.object({
    hero: z.object({
      bild: z.string().optional(),
      titel: z.string().optional(),
      untertitel: z.string().optional(),
    }),
    willkommen: abschnitt,
    spezialitaeten: abschnitt,
    veranstaltungen: abschnitt,
    cta: z.object({
      titel: z.string(),
    }),
  }),
});

const speisekarte = defineCollection({
  loader: glob({ base: './src/content/speisekarte', pattern: '**/*.md' }),
  schema: z.object({
    name: z.string(),
    kategorie: z.enum([
      'Suppen',
      'Vorspeisen',
      'Hauptspeisen',
      'Desserts',
      'Getränke',
    ]),
    beschreibung: z.string().optional(),
    preis: z.number().nonnegative(),
    allergene: z.array(z.enum(ALLERGEN_CODES)).default([]),
    verfuegbar: z.boolean().default(true),
    hausspezialitaet: z.boolean().default(false),
    /** Kleinere Zahl = weiter oben innerhalb der Kategorie. */
    reihenfolge: z.number().int().default(100),
  }),
});

const oeffnungszeiten = defineCollection({
  loader: glob({ base: './src/content/oeffnungszeiten', pattern: '**/*.md' }),
  schema: z.object({
    tag: z.enum(WOCHENTAGE),
    /** Sortierung Mo=1 … So=7 */
    reihenfolge: z.number().int().min(1).max(7),
    geschlossen: z.boolean().default(false),
    /** Format "HH:MM" – nur relevant wenn geschlossen === false */
    von: z
      .string()
      .regex(/^([01]\d|2[0-3]):[0-5]\d$/, 'Format muss HH:MM sein')
      .optional(),
    bis: z
      .string()
      .regex(/^([01]\d|2[0-3]):[0-5]\d$/, 'Format muss HH:MM sein')
      .optional(),
    /** z.B. "Ruhetag" oder "Bei Schlechtwetter geschlossen" */
    hinweis: z.string().optional(),
  }),
});

const geschichte = defineCollection({
  loader: glob({ base: './src/content/geschichte', pattern: '**/*.md' }),
  schema: z.object({
    titel: z.string(),
    /** Jahr bzw. Zeitraum, z.B. "1928" oder "1970er" */
    jahr: z.string().optional(),
    beschreibung: z.string(),
    bild: z.string().optional(),
    bildAlt: z.string().optional(),
    reihenfolge: z.number().int().default(100),
  }),
});

const events = defineCollection({
  loader: glob({ base: './src/content/events', pattern: '**/*.md' }),
  schema: z.object({
    titel: z.string(),
    datum: z.coerce.date(),
    /** Optionales Enddatum für mehrtägige Veranstaltungen. */
    datumBis: z.coerce.date().optional(),
    uhrzeit: z.string().optional(),
    beschreibung: z.string(),
    bild: z.string().optional(),
    bildAlt: z.string().optional(),
    abgesagt: z.boolean().default(false),
  }),
});

const galerie = defineCollection({
  loader: glob({ base: './src/content/galerie', pattern: '**/*.md' }),
  schema: z.object({
    titel: z.string(),
    bild: z.string(),
    /** Alt-Text für Screenreader – fällt auf den Titel zurück. */
    bildAlt: z.string().optional(),
    beschreibung: z.string().optional(),
    kategorie: z
      .enum(['Haus & Terrasse', 'Küche', 'Aussicht', 'Veranstaltungen'])
      .default('Haus & Terrasse'),
    /** Größere Darstellung im Raster. */
    hervorheben: z.boolean().default(false),
    reihenfolge: z.number().int().default(100),
  }),
});

export const collections = {
  startseite,
  speisekarte,
  oeffnungszeiten,
  geschichte,
  events,
  galerie,
};
