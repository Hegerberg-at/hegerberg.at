<?php

/**
 * Versendet eine Push-Benachrichtigung an alle gespeicherten Abonnements.
 *
 * Aufgerufen wird der Endpunkt nicht aus dem Browser, sondern vom Workflow
 * .github/workflows/veranstaltung-melden.yml, sobald nach einem Deploy eine
 * neue Datei unter src/content/events/ liegt. Die Anmeldung läuft über einen
 * gemeinsamen Token im Authorization-Header:
 *
 *   POST /api/push-versand.php
 *   Authorization: Bearer <versand_token>
 *   {"schluessel":"2026-09-06-bergmesse","titel":"…","text":"…","url":"/…"}
 *
 * „schluessel“ ist die Wiederholungssperre: Ein bereits versendeter Schlüssel
 * wird still übersprungen. Ohne Schlüssel wird immer gesendet – praktisch zum
 * Testen von Hand.
 *
 * Die Bibliothek minishlink/web-push liegt auf dem Server unter ../vendor/ und
 * wird über den manuellen Workflow .github/workflows/php-bibliotheken.yml
 * hochgeladen.
 */

declare(strict_types=1);

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

require_once __DIR__ . '/push-speicher.php';

/** Verzeichnis mit der Composer-Installation (ohne abschließenden Slash). */
const PUSH_VENDOR_VERZEICHNIS = __DIR__ . '/../vendor';

const PUSH_MAX_TITEL = 120;
const PUSH_MAX_TEXT = 300;

/**
 * Liest den Authorization-Header. Je nach Server steht er an
 * unterschiedlichen Stellen – bei CGI/FastCGI häufig nur in der
 * REDIRECT_-Variante.
 */
function pushTokenAusAnfrage(): string
{
    $kopf = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');

    if ($kopf === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $wert) {
            if (strcasecmp($name, 'Authorization') === 0) {
                $kopf = (string) $wert;
                break;
            }
        }
    }

    return preg_match('/^Bearer\s+(\S+)$/i', trim($kopf), $treffer) === 1 ? $treffer[1] : '';
}

/**
 * Kürzt einen Text auf die zulässige Länge und entfernt Steuerzeichen.
 */
function pushText(mixed $wert, int $maximum): string
{
    $text = is_string($wert) ? $wert : '';
    $text = trim(preg_replace('/\s+/u', ' ', str_replace(["\r", "\n", "\0"], ' ', $text)) ?? '');

    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $maximum, 'UTF-8');
    }

    return substr($text, 0, $maximum);
}

/**
 * Bindet web-push über den Composer-Autoloader ein.
 */
function pushBibliothekLaden(): void
{
    if (class_exists(WebPush::class)) {
        return;
    }

    $autoloader = PUSH_VENDOR_VERZEICHNIS . '/autoload.php';
    if (!is_readable($autoloader)) {
        pushAbbrechen('web-push nicht gefunden – bitte den Workflow „PHP-Bibliotheken hochladen“ ausführen.');
    }

    require_once $autoloader;

    if (!class_exists(WebPush::class)) {
        pushAbbrechen('web-push fehlt in vendor/ – bitte den Workflow „PHP-Bibliotheken hochladen“ ausführen.');
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    pushAntwort(405, ['ergebnis' => 'fehler', 'meldung' => 'Nur POST-Anfragen werden verarbeitet.']);
}

$konfig = pushKonfiguration();

// hash_equals vergleicht in konstanter Zeit – der Token soll sich nicht über
// die Antwortzeit erraten lassen.
if (!hash_equals($konfig['versand_token'], pushTokenAusAnfrage())) {
    header('WWW-Authenticate: Bearer');
    pushAntwort(401, ['ergebnis' => 'fehler', 'meldung' => 'Nicht berechtigt.']);
}

$roh = file_get_contents('php://input');
$anfrage = is_string($roh) ? json_decode($roh, true) : null;
if (!is_array($anfrage)) {
    pushAntwort(400, ['ergebnis' => 'fehler', 'meldung' => 'Ungültige Anfrage.']);
}

$titel = pushText($anfrage['titel'] ?? null, PUSH_MAX_TITEL);
$text = pushText($anfrage['text'] ?? null, PUSH_MAX_TEXT);
$schluessel = pushText($anfrage['schluessel'] ?? null, 200);

if ($titel === '') {
    pushAntwort(422, ['ergebnis' => 'fehler', 'meldung' => 'Es fehlt ein Titel.']);
}

// Nur seiteneigene Ziele – eine fremde URL in der Benachrichtigung wäre eine
// offene Weiterleitung.
$ziel = pushText($anfrage['url'] ?? null, 300);
if ($ziel === '' || !str_starts_with($ziel, '/') || str_starts_with($ziel, '//')) {
    $ziel = '/veranstaltungen/';
}

$verzeichnis = pushDatenverzeichnis($konfig);

$abos = pushLesen($verzeichnis, PUSH_DATEI_ABOS);
if ($abos === []) {
    pushAntwort(200, ['ergebnis' => 'ok', 'empfaenger' => 0, 'zugestellt' => 0, 'entfernt' => 0]);
}

// Die Bibliothek wird vor der Wiederholungssperre geladen: Fehlt sie auf dem
// Server, soll der Schlüssel nicht als „versendet“ vermerkt sein – sonst
// bliebe die Veranstaltung nach dem Nachinstallieren für immer stumm.
pushBibliothekLaden();

// Wiederholungssperre: Der Workflow läuft nach jedem Deploy, und ein Deploy
// kann auch von Hand angestoßen werden. Ein Schlüssel geht genau einmal raus.
if ($schluessel !== '') {
    $schonVersendet = false;

    pushBearbeiten($verzeichnis, PUSH_DATEI_VERSENDET, static function (array $liste) use (
        $schluessel,
        &$schonVersendet,
    ): array {
        if (in_array($schluessel, $liste, true)) {
            $schonVersendet = true;

            return $liste;
        }

        $liste[] = $schluessel;

        // Nur die jüngsten Einträge behalten, sonst wächst die Datei ewig.
        return array_slice($liste, -PUSH_MAX_VERSENDET);
    });

    if ($schonVersendet) {
        pushAntwort(200, [
            'ergebnis' => 'uebersprungen',
            'meldung' => 'Für „' . $schluessel . '“ wurde bereits benachrichtigt.',
        ]);
    }
}

$webPush = new WebPush([
    'VAPID' => [
        'subject' => $konfig['vapid_subject'],
        'publicKey' => $konfig['vapid_oeffentlich'],
        'privateKey' => $konfig['vapid_privat'],
    ],
]);
// Alle Benachrichtigungen dieses Laufs sind identisch signiert.
$webPush->setReuseVAPIDHeaders(true);

$nutzlast = json_encode([
    'titel' => $titel,
    'text' => $text,
    'url' => $ziel,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

foreach ($abos as $abo) {
    if (!is_array($abo) || !is_string($abo['endpunkt'] ?? null)) {
        continue;
    }

    try {
        $webPush->queueNotification(
            Subscription::create([
                'endpoint' => $abo['endpunkt'],
                'publicKey' => (string) ($abo['p256dh'] ?? ''),
                'authToken' => (string) ($abo['auth'] ?? ''),
                'contentEncoding' => 'aes128gcm',
            ]),
            $nutzlast,
        );
    } catch (Throwable $fehler) {
        error_log('Push: Abonnement übersprungen (' . $fehler->getMessage() . ')');
    }
}

$zugestellt = 0;
$abgelaufen = [];

foreach ($webPush->flush() as $bericht) {
    if ($bericht->isSuccess()) {
        $zugestellt++;
        continue;
    }

    // 404/410 vom Push-Dienst: Der Browser hat das Abonnement gelöscht. Solche
    // Einträge bleiben sonst für immer liegen und kosten bei jedem Versand Zeit.
    if ($bericht->isSubscriptionExpired()) {
        $abgelaufen[] = pushSchluessel($bericht->getEndpoint());
        continue;
    }

    error_log('Push: Versand fehlgeschlagen (' . $bericht->getReason() . ')');
}

if ($abgelaufen !== []) {
    pushBearbeiten($verzeichnis, PUSH_DATEI_ABOS, static function (array $liste) use ($abgelaufen): array {
        foreach ($abgelaufen as $schluessel) {
            unset($liste[$schluessel]);
        }

        return $liste;
    });
}

pushAntwort(200, [
    'ergebnis' => 'ok',
    'empfaenger' => count($abos),
    'zugestellt' => $zugestellt,
    'entfernt' => count($abgelaufen),
]);
