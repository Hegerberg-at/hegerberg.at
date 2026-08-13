/**
 * Zentrale Stammdaten der Website.
 *
 * TODO: Vor dem Livegang die mit PLATZHALTER markierten Werte durch die
 * echten Kontaktdaten des Schutzhauses ersetzen.
 */
export const site = {
  name: 'Schutzhaus am Hegerberg',
  kurzname: 'Hegerberg',
  claim: 'Gastfreundschaft auf 716 Metern im Wienerwald',
  beschreibung:
    'Das Schutzhaus am Hegerberg bei Stössing – bodenständige Küche, kalte Getränke und ein Panoramablick über den Wienerwald. Ein Ziel für Wanderer, Radfahrer und Familien.',
  domain: 'https://hegerberg.at',

  adresse: {
    strasse: 'Hegerberg 1', // PLATZHALTER
    plz: '3073',
    ort: 'Stössing',
    bezirk: 'St. Pölten Land',
    bundesland: 'Niederösterreich',
    land: 'Österreich',
  },

  /** Für Karten-Embed und Geo-Metadaten (Gipfel Hegerberg, 716 m). */
  geo: {
    lat: 48.1069,
    lng: 15.8611,
    seehoehe: 716,
  },

  telefon: '+43 2744 12345', // PLATZHALTER
  telefonAnzeige: '02744 / 12345', // PLATZHALTER
  email: 'office@hegerberg.at', // PLATZHALTER

  /** Leerer String blendet den jeweiligen Link im Footer aus. */
  social: {
    facebook: '',
    instagram: '',
  },

  /** Impressum – PLATZHALTER, bitte rechtlich prüfen lassen. */
  impressum: {
    inhaber: 'Schutzhaus am Hegerberg',
    unternehmensgegenstand: 'Gastgewerbe',
    behoerde: 'Bezirkshauptmannschaft St. Pölten',
    kammer: 'Wirtschaftskammer Niederösterreich, Fachgruppe Gastronomie',
  },
} as const;

export const navigation = [
  { href: '/', label: 'Home' },
  { href: '/speisekarte/', label: 'Speisekarte' },
  { href: '/galerie/', label: 'Galerie' },
  { href: '/geschichte/', label: 'Geschichte' },
  { href: '/oeffnungszeiten/', label: 'Öffnungszeiten' },
  { href: '/kontakt/', label: 'Kontakt' },
] as const;

export const adresseEinzeilig = `${site.adresse.strasse}, ${site.adresse.plz} ${site.adresse.ort}`;
