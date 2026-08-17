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

/** The editorial weekday names mapped to schema.org DayOfWeek. */
const SCHEMA_WEEKDAYS: Record<string, string> = {
  Montag: 'Monday',
  Dienstag: 'Tuesday',
  Mittwoch: 'Wednesday',
  Donnerstag: 'Thursday',
  Freitag: 'Friday',
  Samstag: 'Saturday',
  Sonntag: 'Sunday',
};

export interface SchemaOpeningHours {
  '@type': 'OpeningHoursSpecification';
  dayOfWeek: string;
  opens: string;
  closes: string;
}

/**
 * schema.org openingHoursSpecification for the Place under the organisation.
 *
 * The compact string form ("Mo 11:30-22:00") is not an option here: plain
 * `openingHours` only exists on LocalBusiness and CivicStructure, whereas
 * `openingHoursSpecification` is defined on Place itself.
 *
 * Closed days are left out.
 */
export function schemaOpeningHours(days: OpeningDayData[]): SchemaOpeningHours[] {
  return days
    .filter((day) => !day.closed && day.from && day.until)
    .map((day) => ({
      '@type': 'OpeningHoursSpecification',
      dayOfWeek: SCHEMA_WEEKDAYS[day.day],
      opens: day.from!,
      closes: day.until!,
    }));
}
