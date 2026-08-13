import { getCollection } from 'astro:content';

export type OeffnungstagData = {
  tag: string;
  reihenfolge: number;
  geschlossen: boolean;
  von?: string;
  bis?: string;
  hinweis?: string;
};

/** Öffnungszeiten aus der Collection, sortiert Montag → Sonntag. */
export async function getOeffnungszeiten(): Promise<OeffnungstagData[]> {
  const eintraege = await getCollection('oeffnungszeiten');
  return eintraege
    .map((eintrag) => ({
      tag: eintrag.data.tag,
      reihenfolge: eintrag.data.reihenfolge,
      geschlossen: eintrag.data.geschlossen,
      von: eintrag.data.von,
      bis: eintrag.data.bis,
      hinweis: eintrag.data.hinweis,
    }))
    .sort((a, b) => a.reihenfolge - b.reihenfolge);
}

/** "11:30 – 22:00" bzw. "Geschlossen" */
export function zeitspanne(tag: OeffnungstagData): string {
  if (tag.geschlossen || !tag.von || !tag.bis) return 'Geschlossen';
  return `${tag.von} – ${tag.bis}`;
}

/**
 * schema.org openingHours, z.B. "Mo 11:30-22:00".
 * Geschlossene Tage werden weggelassen.
 */
const SCHEMA_KUERZEL: Record<string, string> = {
  Montag: 'Mo',
  Dienstag: 'Tu',
  Mittwoch: 'We',
  Donnerstag: 'Th',
  Freitag: 'Fr',
  Samstag: 'Sa',
  Sonntag: 'Su',
};

export function schemaOpeningHours(tage: OeffnungstagData[]): string[] {
  return tage
    .filter((tag) => !tag.geschlossen && tag.von && tag.bis)
    .map((tag) => `${SCHEMA_KUERZEL[tag.tag]} ${tag.von}-${tag.bis}`);
}
