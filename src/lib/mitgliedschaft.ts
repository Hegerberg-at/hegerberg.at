import { z } from 'zod';

/**
 * Validierung der Mitgliedschafts-Anfrage im Browser.
 *
 * Die Meldungen werden direkt im Formular angezeigt, darum sind sie auf
 * Deutsch formuliert. Serverseitig prüft /api/mitgliedschaft.php dieselben
 * Regeln noch einmal – auf Eingaben aus dem Browser ist kein Verlass.
 */

const NAME_MIN = 2;
const NAME_MAX = 60;
const EMAIL_MAX = 120;

/** @param akkusativ z. B. „Vornamen“, @param nominativ z. B. „Vorname“ */
const name = (akkusativ: string, nominativ: string) =>
  z
    .string({ error: `Bitte geben Sie Ihren ${akkusativ} an.` })
    .trim()
    .min(NAME_MIN, {
      error: `Bitte geben Sie Ihren ${akkusativ} an (mindestens ${NAME_MIN} Zeichen).`,
    })
    .max(NAME_MAX, {
      error: `Der ${nominativ} darf höchstens ${NAME_MAX} Zeichen lang sein.`,
    });

export const mitgliedschaftSchema = z.object({
  vorname: name('Vornamen', 'Vorname'),
  nachname: name('Nachnamen', 'Nachname'),
  email: z
    .string({ error: 'Bitte geben Sie Ihre E-Mail-Adresse an.' })
    .trim()
    .min(1, { error: 'Bitte geben Sie Ihre E-Mail-Adresse an.' })
    .max(EMAIL_MAX, { error: `Die E-Mail-Adresse darf höchstens ${EMAIL_MAX} Zeichen lang sein.` })
    .pipe(z.email({ error: 'Bitte geben Sie eine gültige E-Mail-Adresse an.' })),
});

export type MitgliedschaftDaten = z.infer<typeof mitgliedschaftSchema>;

/** Feldnamen des Formulars – auch als Schlüssel für die Fehleranzeige. */
export type MitgliedschaftFeld = keyof MitgliedschaftDaten;

export const MITGLIEDSCHAFT_FELDER = [
  'vorname',
  'nachname',
  'email',
] as const satisfies readonly MitgliedschaftFeld[];
