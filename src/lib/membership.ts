import { z } from 'zod';

/**
 * Validation of the membership request in the browser.
 *
 * The messages are shown directly in the form, which is why they are written
 * in German. Server side, /api/membership.php checks the same rules again –
 * input from the browser can never be trusted.
 */

const NAME_MIN = 2;
const NAME_MAX = 60;
const EMAIL_MAX = 120;

/** @param accusative e.g. „Vornamen“, @param nominative e.g. „Vorname“ */
const name = (accusative: string, nominative: string) =>
  z
    .string({ error: `Bitte geben Sie Ihren ${accusative} an.` })
    .trim()
    .min(NAME_MIN, {
      error: `Bitte geben Sie Ihren ${accusative} an (mindestens ${NAME_MIN} Zeichen).`,
    })
    .max(NAME_MAX, {
      error: `Der ${nominative} darf höchstens ${NAME_MAX} Zeichen lang sein.`,
    });

export const membershipSchema = z.object({
  firstName: name('Vornamen', 'Vorname'),
  lastName: name('Nachnamen', 'Nachname'),
  email: z
    .string({ error: 'Bitte geben Sie Ihre E-Mail-Adresse an.' })
    .trim()
    .min(1, { error: 'Bitte geben Sie Ihre E-Mail-Adresse an.' })
    .max(EMAIL_MAX, { error: `Die E-Mail-Adresse darf höchstens ${EMAIL_MAX} Zeichen lang sein.` })
    .pipe(z.email({ error: 'Bitte geben Sie eine gültige E-Mail-Adresse an.' })),
});

export type MembershipData = z.infer<typeof membershipSchema>;

/** Field names of the form – also the keys for the error display. */
export type MembershipField = keyof MembershipData;

export const MEMBERSHIP_FIELDS = [
  'firstName',
  'lastName',
  'email',
] as const satisfies readonly MembershipField[];
