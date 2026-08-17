import { existsSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

/**
 * Evaluation of the GPX files in `public/gpx/`.
 *
 * The files are read at build time only – distance, elevation gain and the
 * profile end up pre-calculated in the HTML. In the browser only the
 * simplified line is needed for the map, not the whole GPX file.
 */

export interface Point {
  lat: number;
  lng: number;
  /** Elevation in metres. */
  ele: number;
}

export interface ProfilePoint {
  /** Distance covered from the start, in kilometres. */
  km: number;
  ele: number;
}

export interface TourData {
  /** Simplified polyline for the map: [lat, lng]. */
  line: [number, number][];
  distanceKm: number;
  ascent: number;
  descent: number;
  elevationMin: number;
  elevationMax: number;
  /** South-west and north-east corner for fitting the map. */
  bounds: [[number, number], [number, number]];
  profile: ProfilePoint[];
  /** Estimated duration in minutes (see `estimateDuration`). */
  durationMinutes: number;
  /** True when start and finish practically coincide. */
  roundTrip: boolean;
}

export type TourType = 'Mountainbike' | 'Wanderung';

const EARTH_RADIUS = 6_371_000;

/** Elevation noise below this threshold does not count as ascent/descent. */
const ELEVATION_THRESHOLD = 4;

/** Tolerance of the line simplification for the map, in metres. */
const SIMPLIFICATION = 6;

/** Sample points of the elevation profile. */
const PROFILE_POINTS = 140;

const cache = new Map<string, TourData>();

export function distanceMeters(a: Point, b: Point): number {
  const toRadians = Math.PI / 180;
  const dLat = (b.lat - a.lat) * toRadians;
  const dLng = (b.lng - a.lng) * toRadians;
  const middle = (a.lat + b.lat) / 2 * toRadians;
  const x = dLng * Math.cos(middle);
  return Math.hypot(x, dLat) * EARTH_RADIUS;
}

/**
 * Minimal parser for GPX 1.0/1.1. Track points are read, route points as a
 * fallback – none of the files used here needs more.
 */
export function parseGpx(xml: string): Point[] {
  const tag = /<(trkpt|rtept)\b[^>]*\blat="([-\d.]+)"[^>]*\blon="([-\d.]+)"[^>]*?(\/?)>/gi;
  const trackPoints: Point[] = [];
  const routePoints: Point[] = [];

  let match: RegExpExecArray | null;
  while ((match = tag.exec(xml)) !== null) {
    const [full, kind, lat, lng, selfClosing] = match;
    let ele = 0;
    if (!selfClosing) {
      // The elevation sits in the body of the point, i.e. before the closing tag.
      const body = xml.slice(
        match.index + full.length,
        match.index + full.length + 400,
      );
      const elevation = /<ele>\s*([-\d.]+)\s*<\/ele>/i.exec(body);
      if (elevation) ele = Number(elevation[1]);
    }

    const point = { lat: Number(lat), lng: Number(lng), ele };
    if (!Number.isFinite(point.lat) || !Number.isFinite(point.lng)) continue;
    (kind.toLowerCase() === 'trkpt' ? trackPoints : routePoints).push(point);
  }

  return trackPoints.length > 0 ? trackPoints : routePoints;
}

/** Ramer-Douglas-Peucker, so the map need not load thousands of points. */
function simplify(points: Point[], tolerance: number): Point[] {
  if (points.length < 3) return points;

  const keep = new Uint8Array(points.length);
  keep[0] = 1;
  keep[points.length - 1] = 1;

  const stack: [number, number][] = [[0, points.length - 1]];
  while (stack.length > 0) {
    const [start, end] = stack.pop()!;
    if (end - start < 2) continue;

    const a = points[start];
    const b = points[end];
    const length = distanceMeters(a, b);

    let maxDistance = -1;
    let maxIndex = start;
    for (let i = start + 1; i < end; i++) {
      const p = points[i];
      let distance: number;
      if (length === 0) {
        distance = distanceMeters(a, p);
      } else {
        // Distance point-to-segment in a locally flat approximation.
        const toMetersY = 111_320;
        const toMetersX = 111_320 * Math.cos((a.lat * Math.PI) / 180);
        const ax = 0;
        const ay = 0;
        const bx = (b.lng - a.lng) * toMetersX;
        const by = (b.lat - a.lat) * toMetersY;
        const px = (p.lng - a.lng) * toMetersX;
        const py = (p.lat - a.lat) * toMetersY;
        const t = Math.max(
          0,
          Math.min(1, ((px - ax) * (bx - ax) + (py - ay) * (by - ay)) / (bx * bx + by * by)),
        );
        distance = Math.hypot(px - t * bx, py - t * by);
      }
      if (distance > maxDistance) {
        maxDistance = distance;
        maxIndex = i;
      }
    }

    if (maxDistance > tolerance) {
      keep[maxIndex] = 1;
      stack.push([start, maxIndex], [maxIndex, end]);
    }
  }

  return points.filter((_, i) => keep[i] === 1);
}

/**
 * Walking time following the Alpenverein rule (ascent and descent times are
 * added at half weight); for the bike a simple sum of rolling and climbing.
 */
export function estimateDuration(
  type: TourType,
  distanceKm: number,
  ascent: number,
  descent: number,
): number {
  if (type === 'Mountainbike') {
    const rolling = (distanceKm / 13) * 60;
    const climbing = (ascent / 500) * 60;
    return Math.round((rolling + climbing) / 5) * 5;
  }

  const horizontal = (distanceKm / 4) * 60;
  const vertical = (ascent / 400 + descent / 800) * 60;
  const minutes = Math.max(horizontal, vertical) + Math.min(horizontal, vertical) / 2;
  return Math.round(minutes / 5) * 5;
}

export function formatDuration(minutes: number): string {
  const hours = Math.floor(minutes / 60);
  const rest = minutes % 60;
  if (hours === 0) return `${rest} min`;
  return `${hours}:${String(rest).padStart(2, '0')} h`;
}

export function formatDistance(km: number): string {
  return `${km.toLocaleString('de-AT', {
    minimumFractionDigits: 1,
    maximumFractionDigits: 1,
  })} km`;
}

function evaluate(points: Point[], type: TourType): TourData {
  if (points.length < 2) {
    throw new Error('GPX-Datei enthält weniger als zwei Punkte.');
  }

  // Distance and running chainage
  const chainage: number[] = [0];
  let travelled = 0;
  for (let i = 1; i < points.length; i++) {
    travelled += distanceMeters(points[i - 1], points[i]);
    chainage.push(travelled);
  }

  // Elevation gain with a threshold so GPS noise is not counted.
  let ascent = 0;
  let descent = 0;
  let reference = points[0].ele;
  for (const point of points) {
    const difference = point.ele - reference;
    if (difference > ELEVATION_THRESHOLD) {
      ascent += difference;
      reference = point.ele;
    } else if (difference < -ELEVATION_THRESHOLD) {
      descent -= difference;
      reference = point.ele;
    }
  }

  const elevations = points.map((p) => p.ele);
  const latitudes = points.map((p) => p.lat);
  const longitudes = points.map((p) => p.lng);

  // Profile: sample points spread evenly across the route.
  const profile: ProfilePoint[] = [];
  const step = travelled / (PROFILE_POINTS - 1);
  let cursor = 0;
  for (let i = 0; i < PROFILE_POINTS; i++) {
    const target = i * step;
    while (cursor < chainage.length - 2 && chainage[cursor + 1] < target) {
      cursor++;
    }
    const a = points[cursor];
    const b = points[Math.min(cursor + 1, points.length - 1)];
    const span = chainage[cursor + 1] - chainage[cursor] || 1;
    const share = Math.max(0, Math.min(1, (target - chainage[cursor]) / span));
    profile.push({
      km: Number((target / 1000).toFixed(3)),
      ele: Math.round(a.ele + (b.ele - a.ele) * share),
    });
  }

  const distanceKm = Number((travelled / 1000).toFixed(1));
  const roundedAscent = Math.round(ascent / 5) * 5;
  const roundedDescent = Math.round(descent / 5) * 5;

  return {
    line: simplify(points, SIMPLIFICATION).map(
      (p) => [Number(p.lat.toFixed(5)), Number(p.lng.toFixed(5))] as [number, number],
    ),
    distanceKm,
    ascent: roundedAscent,
    descent: roundedDescent,
    elevationMin: Math.round(Math.min(...elevations)),
    elevationMax: Math.round(Math.max(...elevations)),
    bounds: [
      [Math.min(...latitudes), Math.min(...longitudes)],
      [Math.max(...latitudes), Math.max(...longitudes)],
    ],
    profile,
    durationMinutes: estimateDuration(type, distanceKm, roundedAscent, roundedDescent),
    roundTrip: distanceMeters(points[0], points[points.length - 1]) < 250,
  };
}

/**
 * Reads a GPX file from `public/` and evaluates it. The path is the public
 * path coming from the CMS, e.g. `/gpx/gipfelrunde.gpx`.
 */
export function loadTourData(publicPath: string, type: TourType): TourData {
  const key = `${type}::${publicPath}`;
  const known = cache.get(key);
  if (known) return known;

  const relative = publicPath.replace(/^\/+/, '');
  // At build time this code runs bundled from `dist/`, in the dev server from
  // `src/` – so try both candidates.
  const candidates = [
    join(process.cwd(), 'public', relative),
    fileURLToPath(new URL(`../../public/${relative}`, import.meta.url)),
  ];

  const file = candidates.find((path) => existsSync(path));
  if (!file) {
    throw new Error(
      `GPX-Datei „${publicPath}“ wurde nicht gefunden (erwartet unter public${publicPath}).`,
    );
  }

  const xml = readFileSync(file, 'utf8');

  const data = evaluate(parseGpx(xml), type);
  cache.set(key, data);
  return data;
}
