<?php

/**
 * Nimmt die Mitgliedschafts-Anfrage aus dem BecomeMembership-Formular entgegen
 * und versendet sie per E-Mail.
 *
 * Antwortet mit JSON, wenn das Formular per fetch() abgeschickt wurde,
 * sonst mit einer Weiterleitung zurück auf die Kontaktseite (ohne JavaScript).
 */

declare(strict_types=1);

/** Empfängeradresse der Anfragen. */
const EMPFAENGER = 'office@hegerberg.at';

/** Absenderadresse – muss zur Domain gehören, sonst filtern Mailserver die Mail weg. */
const ABSENDER = 'no-reply@hegerberg.at';

const ABSENDER_NAME = 'Schutzhaus am Hegerberg';

/** Seite, auf die ohne JavaScript zurückgeleitet wird. */
const RUECKLEITUNG = '/kontakt/#mitglied-werden';

const MIN_LAENGE = 2;
const MAX_LAENGE = 60;
const MAX_EMAIL_LAENGE = 120;

/**
 * Beendet die Anfrage – als JSON oder als Redirect.
 */
function antworten(int $status, string $ergebnis, string $meldung): never
{
    $willJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

    if ($willJson) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(
            ['ergebnis' => $ergebnis, 'meldung' => $meldung],
            JSON_UNESCAPED_UNICODE,
        );
        exit;
    }

    $ziel = RUECKLEITUNG;
    $ziel = str_contains($ziel, '#')
        ? str_replace('#', '?mitgliedschaft=' . $ergebnis . '#', $ziel)
        : $ziel . '?mitgliedschaft=' . $ergebnis;

    http_response_code(303);
    header('Location: ' . $ziel);
    exit;
}

/**
 * Zeichenlänge – nutzt mbstring, wenn die Erweiterung vorhanden ist.
 */
function laenge(string $wert): int
{
    return function_exists('mb_strlen')
        ? mb_strlen($wert, 'UTF-8')
        : (int) preg_match_all('/./u', $wert);
}

/**
 * Liest ein Namensfeld und prüft es. Zeilenumbrüche werden entfernt,
 * damit keine E-Mail-Header eingeschleust werden können.
 */
function nameFeld(string $feld): string
{
    $wert = (string) ($_POST[$feld] ?? '');
    $wert = str_replace(["\r", "\n", "\0"], ' ', $wert);
    $wert = trim(preg_replace('/\s+/u', ' ', $wert) ?? '');

    if (laenge($wert) < MIN_LAENGE || laenge($wert) > MAX_LAENGE) {
        antworten(422, 'fehler', 'Bitte Vor- und Nachnamen vollständig angeben.');
    }

    return $wert;
}

/**
 * Liest die E-Mail-Adresse und prüft sie.
 */
function emailFeld(): string
{
    $wert = (string) ($_POST['email'] ?? '');
    $wert = trim(str_replace(["\r", "\n", "\0"], '', $wert));

    if (laenge($wert) > MAX_EMAIL_LAENGE || !filter_var($wert, FILTER_VALIDATE_EMAIL)) {
        antworten(422, 'fehler', 'Bitte eine gültige E-Mail-Adresse angeben.');
    }

    return $wert;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    antworten(405, 'fehler', 'Nur POST-Anfragen werden verarbeitet.');
}

// Honeypot: von echten Besucher:innen nie ausgefüllt, von Bots meistens schon.
if (trim((string) ($_POST['webseite'] ?? '')) !== '') {
    antworten(200, 'ok', 'Danke für Ihre Anfrage.');
}

$vorname = nameFeld('vorname');
$nachname = nameFeld('nachname');
$email = emailFeld();

$betreff = sprintf('Mitgliedschaft: %s %s', $vorname, $nachname);
$betreffKodiert = function_exists('mb_encode_mimeheader')
    ? mb_encode_mimeheader($betreff, 'UTF-8', 'B')
    : '=?UTF-8?B?' . base64_encode($betreff) . '?=';

$inhalt = implode("\n", [
    'Neue Anfrage für eine Mitgliedschaft über hegerberg.at',
    '',
    'Vorname:  ' . $vorname,
    'Nachname: ' . $nachname,
    'E-Mail:   ' . $email,
    '',
    'Eingegangen: ' . date('d.m.Y H:i'),
]);

// Antworten gehen direkt an die anfragende Person, der Absender bleibt die
// eigene Domain (sonst scheitern SPF/DMARC).
$headers = implode("\r\n", [
    'From: ' . ABSENDER_NAME . ' <' . ABSENDER . '>',
    'Reply-To: ' . $email,
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    'MIME-Version: 1.0',
    'X-Mailer: hegerberg.at',
]);

$gesendet = mail(
    EMPFAENGER,
    $betreffKodiert,
    $inhalt,
    $headers,
    '-f' . ABSENDER,
);

if (!$gesendet) {
    error_log('Mitgliedschaft: mail() fehlgeschlagen für ' . $vorname . ' ' . $nachname);
    antworten(500, 'fehler', 'Die Anfrage konnte nicht gesendet werden. Bitte rufen Sie uns an.');
}

antworten(200, 'ok', 'Danke! Ihre Anfrage ist bei uns eingegangen.');
