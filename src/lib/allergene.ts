/**
 * Allergen-Kennzeichnung nach österreichischer Allergeninformationsverordnung
 * (Codex-Kapitel B 33, LMIV Anhang II).
 */
export const ALLERGENE: Record<string, string> = {
  A: 'Glutenhaltiges Getreide',
  B: 'Krebstiere',
  C: 'Eier',
  D: 'Fisch',
  E: 'Erdnüsse',
  F: 'Soja',
  G: 'Milch und Laktose',
  H: 'Schalenfrüchte (Nüsse)',
  L: 'Sellerie',
  M: 'Senf',
  N: 'Sesam',
  O: 'Sulfite',
  P: 'Lupinen',
  R: 'Weichtiere',
};

export function allergenName(code: string): string {
  return ALLERGENE[code] ?? code;
}
