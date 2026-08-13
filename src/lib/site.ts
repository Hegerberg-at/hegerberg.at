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
    'Das Schutzhaus am Hegerberg bei Stössing',
  domain: 'https://hegerberg.at',

  adresse: {
    strasse: 'Hochstraß 27', // PLATZHALTER
    plz: '3073',
    ort: 'Stössing',
    bezirk: 'St. Pölten Land',
    bundesland: 'Niederösterreich',
    land: 'Österreich',
  },

  /** Für Karten-Embed und Geo-Metadaten (Gipfel Hegerberg, 716 m). */
  geo: {
    lat: 48.13216189669226,
    lng: 15.779537411108286,
    seehoehe: 655,
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
  // { href: '/speisekarte/', label: 'Speisekarte' }, // speisekarte derzeit nicht online
  { href: '/galerie/', label: 'Galerie' },
  { href: '/veranstaltungen/', label: 'Veranstaltungen' },
  { href: '/geschichte/', label: 'Geschichte' },
  { href: '/oeffnungszeiten/', label: 'Öffnungszeiten' },
  { href: '/kontakt/', label: 'Kontakt' },
] as const;

export const adresseEinzeilig = `${site.adresse.strasse}, ${site.adresse.plz} ${site.adresse.ort}`;
