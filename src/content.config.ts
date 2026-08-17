import { defineCollection, z } from 'astro:content';
import { glob } from 'astro/loaders';

/**
 * Austrian allergen labelling (Codex chapter B 33).
 * Also used by src/lib/allergens.ts for the legend.
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

/** Weekday names as shown on the site – editorial values, kept in German. */
const WEEKDAYS = [
  'Montag',
  'Dienstag',
  'Mittwoch',
  'Donnerstag',
  'Freitag',
  'Samstag',
  'Sonntag',
] as const;

/**
 * Decap CMS does not drop emptied fields from the frontmatter, it writes an
 * empty string instead (`from: ""`). For fields with a format check that would
 * break schema validation – so an empty value is treated as "not set" here.
 */
const emptyAsUndefined = (value: unknown) =>
  typeof value === 'string' && value.trim() === '' ? undefined : value;

/** Time of day in "HH:MM" format – an empty field is allowed. */
const timeOfDay = z.preprocess(
  emptyAsUndefined,
  z
    .string()
    .regex(/^([01]\d|2[0-3]):[0-5]\d$/, 'Format muss HH:MM sein')
    .optional(),
);

/** Recurring shape of a section heading (see SectionHeading.astro). */
const section = z.object({
  eyebrow: z.string().optional(),
  title: z.string(),
  text: z.string().optional(),
});

/** Single entry – the editorial texts of the home page. */
const home = defineCollection({
  loader: glob({ base: './src/content/home', pattern: '**/*.md' }),
  schema: z.object({
    hero: z.object({
      image: z.string().optional(),
      title: z.string().optional(),
      subtitle: z.string().optional(),
    }),
    welcome: section,
    specialties: section,
    events: section,
  }),
});

const menu = defineCollection({
  loader: glob({ base: './src/content/menu', pattern: '**/*.md' }),
  schema: z.object({
    name: z.string(),
    category: z.enum([
      'Suppen',
      'Vorspeisen',
      'Hauptspeisen',
      'Desserts',
      'Getränke',
    ]),
    description: z.string().optional(),
    price: z.number().nonnegative(),
    allergens: z.array(z.enum(ALLERGEN_CODES)).default([]),
    available: z.boolean().default(true),
    houseSpecialty: z.boolean().default(false),
    /** Lower number = further up within the category. */
    order: z.number().int().default(100),
  }),
});

const openingHours = defineCollection({
  loader: glob({ base: './src/content/opening-hours', pattern: '**/*.md' }),
  schema: z
    .object({
      day: z.enum(WEEKDAYS),
      /** Sort order Mon=1 … Sun=7 */
      order: z.number().int().min(1).max(7),
      closed: z.boolean().default(false),
      /** "HH:MM" format – only relevant while closed === false */
      from: timeOfDay,
      until: timeOfDay,
      /** e.g. "Ruhetag" or "Bei Schlechtwetter geschlossen" */
      note: z.string().optional(),
    })
    /**
     * On a rest day, times left over in the CMS do not count – the file keeps
     * them (handy when switching back), the website ignores them reliably.
     */
    .transform((day) =>
      day.closed ? { ...day, from: undefined, until: undefined } : day,
    ),
});

const history = defineCollection({
  loader: glob({ base: './src/content/history', pattern: '**/*.md' }),
  schema: z.object({
    title: z.string(),
    /** Year or period, e.g. "1928" or "1970er" */
    year: z.string().optional(),
    description: z.string(),
    image: z.string().optional(),
    imageAlt: z.string().optional(),
    order: z.number().int().default(100),
  }),
});

const events = defineCollection({
  loader: glob({ base: './src/content/events', pattern: '**/*.md' }),
  schema: z.object({
    title: z.string(),
    date: z.coerce.date(),
    /** Optional end date for events spanning several days. */
    endDate: z.preprocess(emptyAsUndefined, z.coerce.date().optional()),
    time: timeOfDay,
    description: z.string(),
    image: z.string().optional(),
    imageAlt: z.string().optional(),
    cancelled: z.boolean().default(false),
    /**
     * Extra photos that only show up on the event's detail page. Decap writes
     * an emptied list as `null` – hence the fallback to an empty array.
     */
    gallery: z.preprocess(
      (value) => value ?? [],
      z.array(
        z.object({
          image: z.string(),
          imageAlt: z.string().optional(),
          description: z.string().optional(),
        }),
      ),
    ),
  }),
});

const gallery = defineCollection({
  loader: glob({ base: './src/content/gallery', pattern: '**/*.md' }),
  schema: z.object({
    title: z.string(),
    image: z.string(),
    /** Alt text for screen readers – falls back to the title. */
    imageAlt: z.string().optional(),
    description: z.string().optional(),
    category: z
      .enum(['Haus & Terrasse', 'Küche', 'Aussicht', 'Veranstaltungen'])
      .default('Haus & Terrasse'),
    /** Larger presentation within the grid. */
    highlight: z.boolean().default(false),
    order: z.number().int().default(100),
  }),
});

const tours = defineCollection({
  loader: glob({ base: './src/content/tours', pattern: '**/*.md' }),
  schema: z.object({
    title: z.string(),
    type: z.enum(['Mountainbike', 'Wanderung']),
    difficulty: z.enum(['leicht', 'mittel', 'schwer']).default('mittel'),
    description: z.string(),
    /**
     * Public path of the GPX file, e.g. "/gpx/gipfelrunde.gpx".
     * Distance, elevation gain and profile are derived from it at build time.
     */
    gpx: z.string().regex(/^\/.+\.gpx$/i, 'Pfad muss mit / beginnen und auf .gpx enden'),
    /** Starting point – the Schutzhaus when left empty. */
    start: z.string().optional(),
    /** Overrides the calculated walking resp. riding time (in minutes). */
    durationMinutes: z.preprocess(
      emptyAsUndefined,
      z.coerce.number().int().positive().optional(),
    ),
    /** e.g. "Im Winter oft vereist" */
    note: z.string().optional(),
    image: z.string().optional(),
    imageAlt: z.string().optional(),
    /** Highlighted on the overview page. */
    featured: z.boolean().default(false),
    order: z.number().int().default(100),
  }),
});

export const collections = {
  home,
  menu,
  openingHours,
  history,
  events,
  gallery,
  tours,
};
