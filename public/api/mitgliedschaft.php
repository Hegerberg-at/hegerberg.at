<?php

/**
 * Nimmt die Mitgliedschafts-Anfrage aus dem BecomeMembership-Formular entgegen
 * und versendet sie per E-Mail.
 *
 * Der Versand läuft über PHPMailer mit SMTP-Anmeldung am Postfach
 * no-reply@hegerberg.at. Der Umweg ist nötig, weil Hostinger bei mail() den
 * Absender durch die am Server hinterlegte Adresse ersetzt.
 *
 * PHPMailer liegt auf dem Server unter ../vendor/ und wird nicht bei jedem
 * Deploy mitgeschickt, sondern über den manuellen Workflow
 * .github/workflows/phpmailer.yml hochgeladen.
 *
 * Die Zugangsdaten kommen aus config.php (siehe config.example.php).
 *
 * Antwortet mit JSON, wenn das Formular per fetch() abgeschickt wurde,
 * sonst mit einer Weiterleitung zurück auf die Kontaktseite (ohne JavaScript).
 */

declare(strict_types=1);

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

/** Seite, auf die ohne JavaScript zurückgeleitet wird. */
const RUECKLEITUNG = '/kontakt/#mitglied-werden';

const MIN_LAENGE = 2;
const MAX_LAENGE = 60;
const MAX_EMAIL_LAENGE = 120;

/** Verzeichnis mit der PHPMailer-Installation (ohne abschließenden Slash). */
const VENDOR_VERZEICHNIS = __DIR__ . '/../vendor';

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
 * Bricht mit einer allgemeinen Fehlermeldung ab. Der technische Grund landet
 * nur im Log – Details über die Serverkonfiguration gehören nicht in die
 * Antwort an den Browser.
 */
function abbrechen(string $grund): never
{
    error_log('Mitgliedschaft: ' . $grund);
    antworten(500, 'fehler', 'Die Anfrage konnte nicht gesendet werden. Bitte rufen Sie uns an.');
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

/**
 * Lädt die SMTP-Zugangsdaten und prüft sie auf Vollständigkeit.
 *
 * @return array{host:string,port:int,verschluesselung:string,benutzer:string,passwort:string,absender:string,absender_name:string,empfaenger:string}
 */
function konfiguration(): array
{
    $datei = __DIR__ . '/config.php';
    if (!is_readable($datei)) {
        abbrechen('config.php fehlt oder ist nicht lesbar.');
    }

    $konfig = require $datei;
    if (!is_array($konfig)) {
        abbrechen('config.php liefert kein Array.');
    }

    $absender = (string) ($konfig['absender'] ?? '') ?: (string) ($konfig['benutzer'] ?? '');

    $werte = [
        'host' => (string) ($konfig['host'] ?? ''),
        'port' => (int) ($konfig['port'] ?? 465),
        'verschluesselung' => (string) ($konfig['verschluesselung'] ?? 'ssl'),
        'benutzer' => (string) ($konfig['benutzer'] ?? ''),
        'passwort' => (string) ($konfig['passwort'] ?? ''),
        'absender' => $absender,
        'absender_name' => (string) ($konfig['absender_name'] ?? 'Schutzhaus am Hegerberg'),
        'empfaenger' => (string) ($konfig['empfaenger'] ?? ''),
    ];

    foreach (['host', 'benutzer', 'passwort', 'absender', 'empfaenger'] as $pflicht) {
        if ($werte[$pflicht] === '') {
            abbrechen('config.php: Wert „' . $pflicht . '“ fehlt.');
        }
    }

    return $werte;
}

/**
 * Bindet PHPMailer ein – über den Composer-Autoloader, sonst über die
 * Quelldateien.
 */
function phpMailerLaden(): void
{
    if (class_exists(PHPMailer::class)) {
        return;
    }

    $autoloader = VENDOR_VERZEICHNIS . '/autoload.php';
    if (is_readable($autoloader)) {
        require_once $autoloader;
        return;
    }

    $quellen = VENDOR_VERZEICHNIS . '/phpmailer/phpmailer/src';
    foreach (['Exception.php', 'PHPMailer.php', 'SMTP.php'] as $datei) {
        if (!is_readable($quellen . '/' . $datei)) {
            abbrechen('PHPMailer nicht gefunden – bitte den Workflow „PHPMailer hochladen“ ausführen.');
        }
        require_once $quellen . '/' . $datei;
    }
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

$konfig = konfiguration();
phpMailerLaden();

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = $konfig['host'];
    $mail->Port = $konfig['port'];
    $mail->SMTPAuth = true;
    $mail->Username = $konfig['benutzer'];
    $mail->Password = $konfig['passwort'];
    // 'keine' ist nur für lokale Tests gedacht; im Livebetrieb 'ssl' oder 'tls'.
    $mail->SMTPSecure = match ($konfig['verschluesselung']) {
        'tls' => PHPMailer::ENCRYPTION_STARTTLS,
        'keine' => '',
        default => PHPMailer::ENCRYPTION_SMTPS,
    };
    $mail->SMTPAutoTLS = $konfig['verschluesselung'] !== 'keine';
    $mail->Timeout = 20;
    $mail->CharSet = PHPMailer::CHARSET_UTF8;
    $mail->XMailer = 'hegerberg.at';

    $mail->setFrom($konfig['absender'], $konfig['absender_name']);
    $mail->addAddress($konfig['empfaenger']);
    // Antworten gehen direkt an die anfragende Person.
    $mail->addReplyTo($email, $vorname . ' ' . $nachname);

    $mail->Subject = sprintf('[Web-Anfrage] Mitgliedschaft: %s %s', $vorname, $nachname);
    $mail->Body = implode("\n", [
        'Neue Anfrage für eine Mitgliedschaft über hegerberg.at',
        '',
        'Vorname:  ' . $vorname,
        'Nachname: ' . $nachname,
        'E-Mail:   ' . $email,
        '',
        'Eingegangen: ' . date('d.m.Y H:i'),
    ]);

    $mail->send();
} catch (PHPMailerException $fehler) {
    abbrechen('Versand fehlgeschlagen: ' . $mail->ErrorInfo);
}

antworten(200, 'ok', 'Danke! Ihre Anfrage ist bei uns eingegangen.');
