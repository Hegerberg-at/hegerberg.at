/**
 * Central master data of the website.
 *
 * TODO: Before going live, replace the values marked PLATZHALTER with the real
 * contact details of the Schutzhaus.
 */
export const site = {
  name: 'Schutzhaus am Hegerberg',
  shortName: 'Hegerberg',
  /** Registered name of the non-profit association running the Schutzhaus. */
  legalName: 'Touristenverein Hegerberg',
  claim: 'Das Schutzhaus am Hegerberg bei Stössing',
  description:
    'Das Schutzhaus am Hegerberg bei Stössing',
  domain: 'https://hegerberg.at',

  address: {
    street: 'Hochstraß 27',
    postalCode: '3073',
    city: 'Stössing',
    district: 'St. Pölten Land',
    state: 'Niederösterreich',
    country: 'Österreich',
  },

  /** For the map embed and geo metadata (Hegerberg summit, 655 m). */
  geo: {
    lat: 48.13216189669226,
    lng: 15.779537411108286,
    elevation: 655,
  },

  phone: '+436801287645',
  phoneDisplay: '+43 680 1287645',
  email: 'office@hegerberg.at',

  /** An empty string hides the respective link in the footer. */
  social: {
    facebook: '',
    instagram: '',
  },

  /** Legal notice – PLATZHALTER, please have this reviewed legally. */
  legal: {
    owner: 'Schutzhaus am Hegerberg',
    /**
     * Register number of the association (Vereinsregister). Required on the
     * publications of an Austrian association – PLATZHALTER, still to be
     * filled in with the real number.
     */
    zvr: 'PLATZHALTER',
    businessPurpose: 'Gastgewerbe',
    authority: 'Bezirkshauptmannschaft St. Pölten',
    chamber: 'Wirtschaftskammer Niederösterreich, Fachgruppe Gastronomie',
  },
} as const;

export const navigation = [
  { href: '/', label: 'Home' },
  // { href: '/speisekarte/', label: 'Speisekarte' }, // menu currently offline
  { href: '/galerie/', label: 'Galerie' },
  { href: '/veranstaltungen/', label: 'Veranstaltungen' },
  { href: '/aktivitaeten/', label: 'Aktivitäten' },
  { href: '/geschichte/', label: 'Geschichte' },
  { href: '/oeffnungszeiten/', label: 'Öffnungszeiten' },
  { href: '/kontakt/', label: 'Kontakt' },
] as const;

export const addressOneLine = `${site.address.street}, ${site.address.postalCode} ${site.address.city}`;
