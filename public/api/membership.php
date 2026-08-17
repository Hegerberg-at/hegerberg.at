<?php

/**
 * Takes the membership request from the BecomeMembership form and sends two
 * emails: the request to the Schutzhaus and a confirmation to the address
 * given in the form.
 *
 * Sending runs through PHPMailer with SMTP authentication against the mailbox
 * no-reply@hegerberg.at. The detour is necessary because Hostinger replaces
 * the sender of mail() with the address configured on the server.
 *
 * PHPMailer lives on the server under ../vendor/ and is not shipped with every
 * deploy but uploaded through the manual workflow
 * .github/workflows/php-libraries.yml.
 *
 * The credentials come from config.php (see config.example.php).
 *
 * Responds with JSON when the form was submitted via fetch(), otherwise with a
 * redirect back to the contact page (the no-JavaScript path).
 */

declare(strict_types=1);

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

/** Page the no-JavaScript path is redirected back to. */
const REDIRECT_TARGET = '/kontakt/#mitglied-werden';

const MIN_LENGTH = 2;
const MAX_LENGTH = 60;
const MAX_EMAIL_LENGTH = 120;

/** Directory holding the PHPMailer installation (without trailing slash). */
const VENDOR_DIR = __DIR__ . '/../vendor';

/**
 * Ends the request – as JSON or as a redirect.
 */
function respond(int $status, string $result, string $message): never
{
    $wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

    if ($wantsJson) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(
            ['result' => $result, 'message' => $message],
            JSON_UNESCAPED_UNICODE,
        );
        exit;
    }

    $target = REDIRECT_TARGET;
    $target = str_contains($target, '#')
        ? str_replace('#', '?membership=' . $result . '#', $target)
        : $target . '?membership=' . $result;

    http_response_code(303);
    header('Location: ' . $target);
    exit;
}

/**
 * Aborts with a generic error message. The technical reason only ends up in
 * the log – details about the server configuration have no place in the
 * response to the browser.
 */
function fail(string $reason): never
{
    error_log('Mitgliedschaft: ' . $reason);
    respond(500, 'error', 'Die Anfrage konnte nicht gesendet werden. Bitte rufen Sie uns an.');
}

/**
 * Character length – uses mbstring when the extension is available.
 */
function characterLength(string $value): int
{
    return function_exists('mb_strlen')
        ? mb_strlen($value, 'UTF-8')
        : (int) preg_match_all('/./u', $value);
}

/**
 * Reads a name field and validates it. Line breaks are removed so no email
 * headers can be injected.
 */
function nameField(string $field): string
{
    $value = (string) ($_POST[$field] ?? '');
    $value = str_replace(["\r", "\n", "\0"], ' ', $value);
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

    if (characterLength($value) < MIN_LENGTH || characterLength($value) > MAX_LENGTH) {
        respond(422, 'error', 'Bitte Vor- und Nachnamen vollständig angeben.');
    }

    return $value;
}

/**
 * Reads the email address and validates it.
 */
function emailField(): string
{
    $value = (string) ($_POST['email'] ?? '');
    $value = trim(str_replace(["\r", "\n", "\0"], '', $value));

    if (characterLength($value) > MAX_EMAIL_LENGTH || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
        respond(422, 'error', 'Bitte eine gültige E-Mail-Adresse angeben.');
    }

    return $value;
}

/**
 * Loads the SMTP credentials and checks them for completeness.
 *
 * @return array{host:string,port:int,encryption:string,username:string,password:string,from:string,from_name:string,recipient:string}
 */
function configuration(): array
{
    $file = __DIR__ . '/config.php';
    if (!is_readable($file)) {
        fail('config.php fehlt oder ist nicht lesbar.');
    }

    $config = require $file;
    if (!is_array($config)) {
        fail('config.php liefert kein Array.');
    }

    $from = (string) ($config['from'] ?? '') ?: (string) ($config['username'] ?? '');

    $values = [
        'host' => (string) ($config['host'] ?? ''),
        'port' => (int) ($config['port'] ?? 465),
        'encryption' => (string) ($config['encryption'] ?? 'ssl'),
        'username' => (string) ($config['username'] ?? ''),
        'password' => (string) ($config['password'] ?? ''),
        'from' => $from,
        'from_name' => (string) ($config['from_name'] ?? 'Schutzhaus am Hegerberg'),
        'recipient' => (string) ($config['recipient'] ?? ''),
    ];

    foreach (['host', 'username', 'password', 'from', 'recipient'] as $required) {
        if ($values[$required] === '') {
            fail('config.php: Wert „' . $required . '“ fehlt.');
        }
    }

    return $values;
}

/**
 * Loads PHPMailer – through the Composer autoloader, otherwise from the source
 * files.
 */
function loadPhpMailer(): void
{
    if (class_exists(PHPMailer::class)) {
        return;
    }

    $autoloader = VENDOR_DIR . '/autoload.php';
    if (is_readable($autoloader)) {
        require_once $autoloader;
        return;
    }

    $sources = VENDOR_DIR . '/phpmailer/phpmailer/src';
    foreach (['Exception.php', 'PHPMailer.php', 'SMTP.php'] as $file) {
        if (!is_readable($sources . '/' . $file)) {
            fail('PHPMailer nicht gefunden – bitte den Workflow „Upload PHP libraries“ ausführen.');
        }
        require_once $sources . '/' . $file;
    }
}

/**
 * Creates the fully configured mailer.
 *
 * The connection stays open (SMTPKeepAlive) so request and confirmation run
 * over the same login – smtpClose() closes it at the end.
 *
 * @param array{host:string,port:int,encryption:string,username:string,password:string,from:string,from_name:string,recipient:string} $config
 */
function createMailer(array $config): PHPMailer
{
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host = $config['host'];
    $mail->Port = $config['port'];
    $mail->SMTPAuth = true;
    $mail->Username = $config['username'];
    $mail->Password = $config['password'];
    // 'none' is meant for local tests only; in production 'ssl' or 'tls'.
    $mail->SMTPSecure = match ($config['encryption']) {
        'tls' => PHPMailer::ENCRYPTION_STARTTLS,
        'none' => '',
        default => PHPMailer::ENCRYPTION_SMTPS,
    };
    $mail->SMTPAutoTLS = $config['encryption'] !== 'none';
    $mail->SMTPKeepAlive = true;
    $mail->Timeout = 20;
    $mail->CharSet = PHPMailer::CHARSET_UTF8;
    $mail->XMailer = 'hegerberg.at';

    return $mail;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(405, 'error', 'Nur POST-Anfragen werden verarbeitet.');
}

// Honeypot: never filled in by real visitors, usually filled in by bots.
if (trim((string) ($_POST['website'] ?? '')) !== '') {
    respond(200, 'ok', 'Danke für Ihre Anfrage.');
}

$firstName = nameField('firstName');
$lastName = nameField('lastName');
$email = emailField();

$config = configuration();
loadPhpMailer();

$mail = createMailer($config);

// 1. Request to the Schutzhaus. If it fails, the request is lost – so respond
//    with an error.
try {
    $mail->setFrom($config['from'], $config['from_name']);
    $mail->addAddress($config['recipient']);
    // Replies go straight to the person who asked.
    $mail->addReplyTo($email, $firstName . ' ' . $lastName);

    $mail->Subject = sprintf('Mitgliedschaft: %s %s', $firstName, $lastName);
    $mail->Body = implode("\n", [
        'Neue Anfrage für eine Mitgliedschaft über hegerberg.at',
        '',
        'Vorname:  ' . $firstName,
        'Nachname: ' . $lastName,
        'E-Mail:   ' . $email,
        '',
    ]);

    $mail->send();
} catch (PHPMailerException $error) {
    fail('Versand fehlgeschlagen: ' . $mail->ErrorInfo);
}

// 2. Confirmation to the person who asked. At this point the request is
//    already in the mailbox of the Schutzhaus – an error here is therefore
//    only logged and does not change the response to the form.
$confirmed = false;

try {
    $mail->clearAllRecipients();
    $mail->clearReplyTos();

    $mail->addAddress($email, $firstName . ' ' . $lastName);
    // Replies to the confirmation should land in the Schutzhaus mailbox.
    $mail->addReplyTo($config['recipient'], $config['from_name']);

    $mail->Subject = 'Ihre Anfrage für eine Mitgliedschaft';
    $mail->Body = implode("\n", [
        'Guten Tag ' . $firstName . ' ' . $lastName . ',',
        '',
        'vielen Dank für Ihr Interesse an einer Mitgliedschaft im Schutzhaus',
        'am Hegerberg. Ihre Anfrage ist bei uns eingegangen.',
        '',
        'Ihre Angaben:',
        'Vorname:  ' . $firstName,
        'Nachname: ' . $lastName,
        'E-Mail:   ' . $email,
        '',
        'Sollten die Angaben nicht stimmen oder haben Sie Fragen, antworten Sie',
        'einfach auf diese E-Mail (' . $config['recipient'] . ').',
        '',
        'Freundliche Grüße',
        $config['from_name'],
        'https://hegerberg.at',
    ]);

    $mail->send();
    $confirmed = true;
} catch (PHPMailerException $error) {
    error_log('Mitgliedschaft: Bestätigung an ' . $email . ' fehlgeschlagen: ' . $mail->ErrorInfo);
}

$mail->smtpClose();

// Only announce the confirmation when it actually went out.
respond(200, 'ok', $confirmed
    ? 'Danke! Ihre Anfrage ist bei uns eingegangen. Sie erhalten gleich eine Bestätigung per E-Mail.'
    : 'Danke! Ihre Anfrage ist bei uns eingegangen.');
