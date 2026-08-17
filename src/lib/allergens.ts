/**
 * Allergen labelling under the Austrian allergen information regulation
 * (Codex chapter B 33, FIC annex II). The names are shown to guests, so they
 * stay in German.
 */
export const ALLERGENS: Record<string, string> = {
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
  return ALLERGENS[code] ?? code;
}
