import { type CollectionEntry } from 'astro:content';
import { isoDate } from './format';

/**
 * Last day an event counts as upcoming, as YYYY-MM-DD in the Vienna time zone.
 * Multi-day events stay upcoming until their end date has passed.
 *
 * The value is rendered into `data-until` so the browser can redo the split of
 * a static build – see src/lib/event-split.ts.
 */
export function eventUntil(event: CollectionEntry<'events'>): string {
  return isoDate(event.data.endDate ?? event.data.date);
}
