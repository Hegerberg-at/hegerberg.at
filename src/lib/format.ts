const priceFormat = new Intl.NumberFormat('de-AT', {
  style: 'currency',
  currency: 'EUR',
});

export function formatPrice(price: number): string {
  return priceFormat.format(price);
}

const dateFormat = new Intl.DateTimeFormat('de-AT', {
  weekday: 'long',
  day: 'numeric',
  month: 'long',
  year: 'numeric',
  timeZone: 'Europe/Vienna',
});

const shortDateFormat = new Intl.DateTimeFormat('de-AT', {
  day: '2-digit',
  month: '2-digit',
  year: 'numeric',
  timeZone: 'Europe/Vienna',
});

export function formatDate(date: Date): string {
  return dateFormat.format(date);
}

export function formatDateShort(date: Date): string {
  return shortDateFormat.format(date);
}

/** ISO date (YYYY-MM-DD) for <time datetime="…"> in the Vienna time zone. */
export function isoDate(date: Date): string {
  return new Intl.DateTimeFormat('en-CA', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    timeZone: 'Europe/Vienna',
  }).format(date);
}
