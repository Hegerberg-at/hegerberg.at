import { getCollection, type CollectionEntry } from 'astro:content';
import { loadTourData, type TourData } from './gpx';

export interface Tour {
  entry: CollectionEntry<'tours'>;
  /** Key figures calculated from the GPX file. */
  data: TourData;
  /** Editorial override, otherwise the calculated value. */
  durationMinutes: number;
  href: string;
}

/** Mountain biking first, then hiking – within a type by `order`. */
const TYPES = ['Mountainbike', 'Wanderung'] as const;

export async function loadTours(): Promise<Tour[]> {
  const entries = await getCollection('tours');

  return entries
    .map((entry) => {
      const data = loadTourData(entry.data.gpx, entry.data.type);
      return {
        entry,
        data,
        durationMinutes: entry.data.durationMinutes ?? data.durationMinutes,
        href: `/aktivitaeten/${entry.id}/`,
      };
    })
    .sort(
      (a, b) =>
        TYPES.indexOf(a.entry.data.type) - TYPES.indexOf(b.entry.data.type) ||
        a.entry.data.order - b.entry.data.order ||
        a.entry.data.title.localeCompare(b.entry.data.title, 'de-AT'),
    );
}
