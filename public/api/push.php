<?php

/**
 * Endpunkt für die Browser: VAPID-Schlüssel ausliefern, Push-Abonnements
 * anlegen und wieder entfernen.
 *
 * Aufgerufen wird das Script aus src/components/PushAnmeldung.astro.
 *
 *   GET  ?aktion=schluessel          → öffentlicher VAPID-Schlüssel
 *   POST {"aktion":"anmelden",...}   → Abonnement speichern
 *   POST {"aktion":"abmelden",...}   → Abonnement löschen
 *   POST {"aktion":"pruefen",...}    → ist der Endpunkt hier bekannt?
 *
 * Der eigentliche Versand läuft getrennt über push-versand.php.
 *
 * Die Zugangsdaten kommen aus push-config.php (siehe push-config.example.php),
 * die Ablage der Abonnements steckt in push-speicher.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/push-speicher.php';

/** Ein Push-Endpunkt ist eine URL des jeweiligen Push-Dienstes. */
const PUSH_MAX_ENDPUNKT_LAENGE = 1000;

/** Größe des erlaubten Anfragekörpers. */
const PUSH_MAX_KOERPER = 8192;

/**
 * Liest den JSON-Körper der Anfrage.
 *
 * @return array<string,mixed>
 */
function pushKoerper(): array
{
    $roh = file_get_contents('php://input', false, null, 0, PUSH_MAX_KOERPER + 1);
    if ($roh === false || strlen($roh) > PUSH_MAX_KOERPER) {
        pushAntwort(413, ['ergebnis' => 'fehler', 'meldung' => 'Anfrage zu groß.']);
    }

    $daten = json_decode($roh, true);
    if (!is_array($daten)) {
        pushAntwort(400, ['ergebnis' => 'fehler', 'meldung' => 'Ungültige Anfrage.']);
    }

    return $daten;
}

/**
 * Prüft einen Push-Endpunkt. Auf welchem Push-Dienst er liegt, entscheidet der
 * Browser – deshalb wird der Host nicht eingeschränkt, nur das Schema.
 */
function pushEndpunktPruefen(mixed $wert): string
{
    $endpunkt = is_string($wert) ? trim($wert) : '';

    if (
        $endpunkt === ''
        || strlen($endpunkt) > PUSH_MAX_ENDPUNKT_LAENGE
        || !str_starts_with($endpunkt, 'https://')
        || !filter_var($endpunkt, FILTER_VALIDATE_URL)
    ) {
        pushAntwort(422, ['ergebnis' => 'fehler', 'meldung' => 'Ungültiger Push-Endpunkt.']);
    }

    return $endpunkt;
}

/**
 * Prüft einen der beiden base64url-codierten Schlüssel des Abonnements.
 */
function pushSchluesselPruefen(mixed $wert, int $minimum, int $maximum): string
{
    $schluessel = is_string($wert) ? trim($wert) : '';
    $laenge = strlen($schluessel);

    if ($laenge < $minimum || $laenge > $maximum || preg_match('/^[A-Za-z0-9_-]+=*$/', $schluessel) !== 1) {
        pushAntwort(422, ['ergebnis' => 'fehler', 'meldung' => 'Ungültiger Schlüssel im Abonnement.']);
    }

    return $schluessel;
}

$konfig = pushKonfiguration();
$methode = $_SERVER['REQUEST_METHOD'] ?? '';

// Der öffentliche VAPID-Schlüssel ist – wie der Name sagt – öffentlich. Er
// wird nicht in die Seite gebaut, sondern hier abgeholt: so muss der Build
// nichts über die Schlüssel wissen.
if ($methode === 'GET') {
    if (($_GET['aktion'] ?? '') !== 'schluessel') {
        pushAntwort(400, ['ergebnis' => 'fehler', 'meldung' => 'Unbekannte Aktion.']);
    }

    pushAntwort(200, ['ergebnis' => 'ok', 'schluessel' => $konfig['vapid_oeffentlich']]);
}

if ($methode !== 'POST') {
    header('Allow: GET, POST');
    pushAntwort(405, ['ergebnis' => 'fehler', 'meldung' => 'Nur GET- und POST-Anfragen werden verarbeitet.']);
}

$anfrage = pushKoerper();
$aktion = is_string($anfrage['aktion'] ?? null) ? $anfrage['aktion'] : '';
$verzeichnis = pushDatenverzeichnis($konfig);

if ($aktion === 'anmelden') {
    $abo = is_array($anfrage['abo'] ?? null) ? $anfrage['abo'] : [];
    $keys = is_array($abo['keys'] ?? null) ? $abo['keys'] : [];

    $endpunkt = pushEndpunktPruefen($abo['endpoint'] ?? null);
    // 65 Byte öffentlicher Schlüssel bzw. 16 Byte Authentifizierungs-Geheimnis,
    // base64url-codiert – mit etwas Luft nach oben und unten.
    $p256dh = pushSchluesselPruefen($keys['p256dh'] ?? null, 80, 100);
    $auth = pushSchluesselPruefen($keys['auth'] ?? null, 16, 40);

    $voll = false;
    pushBearbeiten($verzeichnis, PUSH_DATEI_ABOS, function (array $abos) use (
        $endpunkt,
        $p256dh,
        $auth,
        &$voll,
    ): array {
        $schluessel = pushSchluessel($endpunkt);

        if (!isset($abos[$schluessel]) && count($abos) >= PUSH_MAX_ABOS) {
            $voll = true;

            return $abos;
        }

        $abos[$schluessel] = [
            'endpunkt' => $endpunkt,
            'p256dh' => $p256dh,
            'auth' => $auth,
            // Bei einer erneuten Anmeldung bleibt das ursprüngliche Datum stehen.
            'angelegt' => (string) ($abos[$schluessel]['angelegt'] ?? gmdate('c')),
        ];

        return $abos;
    });

    if ($voll) {
        pushAbbrechen('Höchstzahl von ' . PUSH_MAX_ABOS . ' Abonnements erreicht.');
    }

    pushAntwort(200, ['ergebnis' => 'ok', 'meldung' => 'Benachrichtigungen sind aktiviert.']);
}

if ($aktion === 'abmelden') {
    $endpunkt = pushEndpunktPruefen($anfrage['endpunkt'] ?? null);

    pushBearbeiten($verzeichnis, PUSH_DATEI_ABOS, static function (array $abos) use ($endpunkt): array {
        unset($abos[pushSchluessel($endpunkt)]);

        return $abos;
    });

    pushAntwort(200, ['ergebnis' => 'ok', 'meldung' => 'Benachrichtigungen sind abgeschaltet.']);
}

// Der Browser kann ein Abonnement behalten, das auf dem Server längst gelöscht
// wurde (z. B. weil der Push-Dienst es als abgelaufen gemeldet hat). Damit die
// Anzeige stimmt, fragt die Seite den Stand hier ab.
if ($aktion === 'pruefen') {
    $endpunkt = pushEndpunktPruefen($anfrage['endpunkt'] ?? null);
    $abos = pushLesen($verzeichnis, PUSH_DATEI_ABOS);

    pushAntwort(200, [
        'ergebnis' => 'ok',
        'bekannt' => isset($abos[pushSchluessel($endpunkt)]),
    ]);
}

pushAntwort(400, ['ergebnis' => 'fehler', 'meldung' => 'Unbekannte Aktion.']);
