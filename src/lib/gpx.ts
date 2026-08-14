import { existsSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

/**
 * Auswertung der GPX-Dateien aus `public/gpx/`.
 *
 * Die Dateien werden ausschließlich beim Build gelesen – Distanz, Höhenmeter
 * und Profil landen fertig berechnet im HTML. Im Browser wird nur noch die
 * vereinfachte Linie für die Karte gebraucht, nicht die ganze GPX-Datei.
 */

export interface Punkt {
  lat: number;
  lng: number;
  /** Seehöhe in Metern. */
  ele: number;
}

export interface Profilpunkt {
  /** Zurückgelegte Strecke ab Start, in Kilometern. */
  km: number;
  ele: number;
}

export interface Tourdaten {
  /** Vereinfachter Linienzug für die Karte: [lat, lng]. */
  linie: [number, number][];
  distanzKm: number;
  aufstieg: number;
  abstieg: number;
  hoeheMin: number;
  hoeheMax: number;
  /** Südwest- und Nordost-Ecke für das Einpassen der Karte. */
  bounds: [[number, number], [number, number]];
  profil: Profilpunkt[];
  /** Geschätzte Dauer in Minuten (siehe `schaetzeDauer`). */
  dauerMinuten: number;
  /** True, wenn Start und Ziel praktisch zusammenfallen. */
  rundtour: boolean;
}

export type Tourart = 'Mountainbike' | 'Wanderung';

const ERDRADIUS = 6_371_000;

/** Höhenrauschen unterhalb dieser Schwelle zählt nicht als Auf-/Abstieg. */
const HOEHEN_SCHWELLE = 4;

/** Toleranz der Linienvereinfachung für die Karte, in Metern. */
const VEREINFACHUNG = 6;

/** Stützstellen des Höhenprofils. */
const PROFIL_PUNKTE = 140;

const zwischenspeicher = new Map<string, Tourdaten>();

export function abstandMeter(a: Punkt, b: Punkt): number {
  const zuBogen = Math.PI / 180;
  const dLat = (b.lat - a.lat) * zuBogen;
  const dLng = (b.lng - a.lng) * zuBogen;
  const mitte = (a.lat + b.lat) / 2 * zuBogen;
  const x = dLng * Math.cos(mitte);
  return Math.hypot(x, dLat) * ERDRADIUS;
}

/**
 * Minimal-Parser für GPX 1.0/1.1. Gelesen werden Track-Punkte, ersatzweise
 * Routen-Punkte – mehr braucht keine der hier verwendeten Dateien.
 */
export function parseGpx(xml: string): Punkt[] {
  const tag = /<(trkpt|rtept)\b[^>]*\blat="([-\d.]+)"[^>]*\blon="([-\d.]+)"[^>]*?(\/?)>/gi;
  const trackpunkte: Punkt[] = [];
  const routenpunkte: Punkt[] = [];

  let treffer: RegExpExecArray | null;
  while ((treffer = tag.exec(xml)) !== null) {
    const [voll, art, lat, lng, selbstschliessend] = treffer;
    let ele = 0;
    if (!selbstschliessend) {
      // Die Seehöhe steht im Rumpf des Punktes, also vor dem schließenden Tag.
      const rumpf = xml.slice(
        treffer.index + voll.length,
        treffer.index + voll.length + 400,
      );
      const hoehe = /<ele>\s*([-\d.]+)\s*<\/ele>/i.exec(rumpf);
      if (hoehe) ele = Number(hoehe[1]);
    }

    const punkt = { lat: Number(lat), lng: Number(lng), ele };
    if (!Number.isFinite(punkt.lat) || !Number.isFinite(punkt.lng)) continue;
    (art.toLowerCase() === 'trkpt' ? trackpunkte : routenpunkte).push(punkt);
  }

  return trackpunkte.length > 0 ? trackpunkte : routenpunkte;
}

/** Ramer-Douglas-Peucker, damit die Karte nicht tausende Punkte laden muss. */
function vereinfache(punkte: Punkt[], toleranz: number): Punkt[] {
  if (punkte.length < 3) return punkte;

  const behalten = new Uint8Array(punkte.length);
  behalten[0] = 1;
  behalten[punkte.length - 1] = 1;

  const stapel: [number, number][] = [[0, punkte.length - 1]];
  while (stapel.length > 0) {
    const [start, ende] = stapel.pop()!;
    if (ende - start < 2) continue;

    const a = punkte[start];
    const b = punkte[ende];
    const laenge = abstandMeter(a, b);

    let maxAbstand = -1;
    let maxIndex = start;
    for (let i = start + 1; i < ende; i++) {
      const p = punkte[i];
      let abstand: number;
      if (laenge === 0) {
        abstand = abstandMeter(a, p);
      } else {
        // Abstand Punkt–Strecke in einer lokal ebenen Näherung.
        const zuMeterY = 111_320;
        const zuMeterX = 111_320 * Math.cos((a.lat * Math.PI) / 180);
        const ax = 0;
        const ay = 0;
        const bx = (b.lng - a.lng) * zuMeterX;
        const by = (b.lat - a.lat) * zuMeterY;
        const px = (p.lng - a.lng) * zuMeterX;
        const py = (p.lat - a.lat) * zuMeterY;
        const t = Math.max(
          0,
          Math.min(1, ((px - ax) * (bx - ax) + (py - ay) * (by - ay)) / (bx * bx + by * by)),
        );
        abstand = Math.hypot(px - t * bx, py - t * by);
      }
      if (abstand > maxAbstand) {
        maxAbstand = abstand;
        maxIndex = i;
      }
    }

    if (maxAbstand > toleranz) {
      behalten[maxIndex] = 1;
      stapel.push([start, maxIndex], [maxIndex, ende]);
    }
  }

  return punkte.filter((_, i) => behalten[i] === 1);
}

/**
 * Gehzeit nach der Regel des Alpenvereins (Auf- und Abstiegszeit werden zur
 * Hälfte addiert), fürs Rad eine einfache Summe aus Rollzeit und Steigzeit.
 */
export function schaetzeDauer(
  art: Tourart,
  distanzKm: number,
  aufstieg: number,
  abstieg: number,
): number {
  if (art === 'Mountainbike') {
    const rollen = (distanzKm / 13) * 60;
    const steigen = (aufstieg / 500) * 60;
    return Math.round((rollen + steigen) / 5) * 5;
  }

  const waagrecht = (distanzKm / 4) * 60;
  const senkrecht = (aufstieg / 400 + abstieg / 800) * 60;
  const minuten = Math.max(waagrecht, senkrecht) + Math.min(waagrecht, senkrecht) / 2;
  return Math.round(minuten / 5) * 5;
}

export function formatDauer(minuten: number): string {
  const stunden = Math.floor(minuten / 60);
  const rest = minuten % 60;
  if (stunden === 0) return `${rest} min`;
  return `${stunden}:${String(rest).padStart(2, '0')} h`;
}

export function formatDistanz(km: number): string {
  return `${km.toLocaleString('de-AT', {
    minimumFractionDigits: 1,
    maximumFractionDigits: 1,
  })} km`;
}

function werteAus(punkte: Punkt[], art: Tourart): Tourdaten {
  if (punkte.length < 2) {
    throw new Error('GPX-Datei enthält weniger als zwei Punkte.');
  }

  // Distanz und laufende Kilometrierung
  const kilometrierung: number[] = [0];
  let strecke = 0;
  for (let i = 1; i < punkte.length; i++) {
    strecke += abstandMeter(punkte[i - 1], punkte[i]);
    kilometrierung.push(strecke);
  }

  // Höhenmeter mit Schwellwert, damit GPS-Rauschen nicht mitgezählt wird.
  let aufstieg = 0;
  let abstieg = 0;
  let referenz = punkte[0].ele;
  for (const punkt of punkte) {
    const differenz = punkt.ele - referenz;
    if (differenz > HOEHEN_SCHWELLE) {
      aufstieg += differenz;
      referenz = punkt.ele;
    } else if (differenz < -HOEHEN_SCHWELLE) {
      abstieg -= differenz;
      referenz = punkt.ele;
    }
  }

  const hoehen = punkte.map((p) => p.ele);
  const breiten = punkte.map((p) => p.lat);
  const laengen = punkte.map((p) => p.lng);

  // Profil: gleichmäßig über die Strecke verteilte Stützstellen.
  const profil: Profilpunkt[] = [];
  const schritt = strecke / (PROFIL_PUNKTE - 1);
  let zeiger = 0;
  for (let i = 0; i < PROFIL_PUNKTE; i++) {
    const ziel = i * schritt;
    while (zeiger < kilometrierung.length - 2 && kilometrierung[zeiger + 1] < ziel) {
      zeiger++;
    }
    const a = punkte[zeiger];
    const b = punkte[Math.min(zeiger + 1, punkte.length - 1)];
    const spanne = kilometrierung[zeiger + 1] - kilometrierung[zeiger] || 1;
    const anteil = Math.max(0, Math.min(1, (ziel - kilometrierung[zeiger]) / spanne));
    profil.push({
      km: Number((ziel / 1000).toFixed(3)),
      ele: Math.round(a.ele + (b.ele - a.ele) * anteil),
    });
  }

  const distanzKm = Number((strecke / 1000).toFixed(1));
  const gerundeterAufstieg = Math.round(aufstieg / 5) * 5;
  const gerundeterAbstieg = Math.round(abstieg / 5) * 5;

  return {
    linie: vereinfache(punkte, VEREINFACHUNG).map(
      (p) => [Number(p.lat.toFixed(5)), Number(p.lng.toFixed(5))] as [number, number],
    ),
    distanzKm,
    aufstieg: gerundeterAufstieg,
    abstieg: gerundeterAbstieg,
    hoeheMin: Math.round(Math.min(...hoehen)),
    hoeheMax: Math.round(Math.max(...hoehen)),
    bounds: [
      [Math.min(...breiten), Math.min(...laengen)],
      [Math.max(...breiten), Math.max(...laengen)],
    ],
    profil,
    dauerMinuten: schaetzeDauer(art, distanzKm, gerundeterAufstieg, gerundeterAbstieg),
    rundtour: abstandMeter(punkte[0], punkte[punkte.length - 1]) < 250,
  };
}

/**
 * Liest eine GPX-Datei aus `public/` und wertet sie aus. Der Pfad ist der
 * öffentliche Pfad aus dem CMS, also z. B. `/gpx/gipfelrunde.gpx`.
 */
export function ladeTourdaten(oeffentlicherPfad: string, art: Tourart): Tourdaten {
  const schluessel = `${art}::${oeffentlicherPfad}`;
  const bekannt = zwischenspeicher.get(schluessel);
  if (bekannt) return bekannt;

  const relativ = oeffentlicherPfad.replace(/^\/+/, '');
  // Beim Build läuft dieser Code gebündelt aus `dist/`, im Dev-Server aus
  // `src/` – deshalb beide Kandidaten probieren.
  const kandidaten = [
    join(process.cwd(), 'public', relativ),
    fileURLToPath(new URL(`../../public/${relativ}`, import.meta.url)),
  ];

  const datei = kandidaten.find((pfad) => existsSync(pfad));
  if (!datei) {
    throw new Error(
      `GPX-Datei „${oeffentlicherPfad}“ wurde nicht gefunden (erwartet unter public${oeffentlicherPfad}).`,
    );
  }

  const xml = readFileSync(datei, 'utf8');

  const daten = werteAus(parseGpx(xml), art);
  zwischenspeicher.set(schluessel, daten);
  return daten;
}
