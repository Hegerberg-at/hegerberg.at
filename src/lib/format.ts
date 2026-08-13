const preisFormat = new Intl.NumberFormat('de-AT', {
  style: 'currency',
  currency: 'EUR',
});

export function formatPreis(preis: number): string {
  return preisFormat.format(preis);
}

const datumFormat = new Intl.DateTimeFormat('de-AT', {
  weekday: 'long',
  day: 'numeric',
  month: 'long',
  year: 'numeric',
  timeZone: 'Europe/Vienna',
});

const datumKurzFormat = new Intl.DateTimeFormat('de-AT', {
  day: '2-digit',
  month: '2-digit',
  year: 'numeric',
  timeZone: 'Europe/Vienna',
});

export function formatDatum(datum: Date): string {
  return datumFormat.format(datum);
}

export function formatDatumKurz(datum: Date): string {
  return datumKurzFormat.format(datum);
}

/** ISO-Datum (YYYY-MM-DD) für <time datetime="…"> in Wiener Zeitzone. */
export function isoDatum(datum: Date): string {
  return new Intl.DateTimeFormat('en-CA', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    timeZone: 'Europe/Vienna',
  }).format(datum);
}
