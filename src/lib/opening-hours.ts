import { getCollection } from 'astro:content';

export type OpeningDayData = {
  day: string;
  order: number;
  closed: boolean;
  from?: string;
  until?: string;
  note?: string;
};

/** Opening hours from the collection, sorted Monday → Sunday. */
export async function getOpeningHours(): Promise<OpeningDayData[]> {
  const entries = await getCollection('openingHours');
  return entries
    .map((entry) => ({
      day: entry.data.day,
      order: entry.data.order,
      closed: entry.data.closed,
      from: entry.data.from,
      until: entry.data.until,
      note: entry.data.note,
    }))
    .sort((a, b) => a.order - b.order);
}

/** "11:30 – 22:00" resp. "Geschlossen" */
export function timeRange(day: OpeningDayData): string {
  if (day.closed || !day.from || !day.until) return 'Geschlossen';
  return `${day.from} – ${day.until}`;
}

/**
 * schema.org openingHours, e.g. "Mo 11:30-22:00".
 * Closed days are left out.
 */
const SCHEMA_ABBREVIATIONS: Record<string, string> = {
  Montag: 'Mo',
  Dienstag: 'Tu',
  Mittwoch: 'We',
  Donnerstag: 'Th',
  Freitag: 'Fr',
  Samstag: 'Sa',
  Sonntag: 'Su',
};

export function schemaOpeningHours(days: OpeningDayData[]): string[] {
  return days
    .filter((day) => !day.closed && day.from && day.until)
    .map((day) => `${SCHEMA_ABBREVIATIONS[day.day]} ${day.from}-${day.until}`);
}
