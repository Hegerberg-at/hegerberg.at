import { getCollection, type CollectionEntry } from 'astro:content';
import { ladeTourdaten, type Tourdaten } from './gpx';

export interface Tour {
  eintrag: CollectionEntry<'touren'>;
  /** Aus der GPX-Datei berechnete Kennzahlen. */
  daten: Tourdaten;
  /** Redaktionelle Vorgabe, sonst der berechnete Wert. */
  dauerMinuten: number;
  href: string;
}

/** Mountainbike zuerst, dann Wandern – innerhalb der Art nach Reihenfolge. */
const ARTEN = ['Mountainbike', 'Wanderung'] as const;

export async function ladeTouren(): Promise<Tour[]> {
  const eintraege = await getCollection('touren');

  return eintraege
    .map((eintrag) => {
      const daten = ladeTourdaten(eintrag.data.gpx, eintrag.data.art);
      return {
        eintrag,
        daten,
        dauerMinuten: eintrag.data.dauerMinuten ?? daten.dauerMinuten,
        href: `/aktivitaeten/${eintrag.id}/`,
      };
    })
    .sort(
      (a, b) =>
        ARTEN.indexOf(a.eintrag.data.art) - ARTEN.indexOf(b.eintrag.data.art) ||
        a.eintrag.data.reihenfolge - b.eintrag.data.reihenfolge ||
        a.eintrag.data.titel.localeCompare(b.eintrag.data.titel, 'de-AT'),
    );
}
