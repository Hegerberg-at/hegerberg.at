<?php

/**
 * Gemeinsame Grundlage der beiden Push-Endpunkte push.php und
 * push-versand.php: Konfiguration, Ablage der Abonnements und die
 * JSON-Antwort.
 *
 * Die Abonnements liegen als JSON-Datei im Datenverzeichnis – standardmäßig
 * eine Ebene über public_html, damit sie über den Browser nicht erreichbar
 * sind. Lässt sich das Verzeichnis dort nicht anlegen (manche Hoster sperren
 * alles außerhalb des Webroots), weicht das Script auf api/push-daten/ aus
 * und legt dort ein .htaccess mit „Deny from all“ ab.
 *
 * Geschrieben wird immer unter einer Dateisperre, damit sich zwei gleichzeitige
 * Anmeldungen nicht gegenseitig überschreiben.
 */

declare(strict_types=1);

/** Mehr Abonnements nimmt der Server nicht an – Schutz vor Müll in der Datei. */
const PUSH_MAX_ABOS = 5000;

/** So viele bereits versendete Veranstaltungen werden vorgehalten. */
const PUSH_MAX_VERSENDET = 200;

/** Dateinamen im Datenverzeichnis. */
const PUSH_DATEI_ABOS = 'abos.json';
const PUSH_DATEI_VERSENDET = 'versendet.json';

/**
 * Beendet die Anfrage mit einer JSON-Antwort.
 *
 * @param array<string,mixed> $daten
 */
function pushAntwort(int $status, array $daten): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($daten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Bricht mit einer allgemeinen Fehlermeldung ab. Der technische Grund landet
 * nur im Log – Details über die Serverkonfiguration gehören nicht in die
 * Antwort an den Browser.
 */
function pushAbbrechen(string $grund): never
{
    error_log('Push: ' . $grund);
    pushAntwort(500, ['ergebnis' => 'fehler', 'meldung' => 'Die Anfrage konnte nicht verarbeitet werden.']);
}

/**
 * Lädt die Push-Konfiguration und prüft sie auf Vollständigkeit.
 *
 * @return array{vapid_oeffentlich:string,vapid_privat:string,vapid_subject:string,versand_token:string,datenverzeichnis:string}
 */
function pushKonfiguration(): array
{
    $datei = __DIR__ . '/push-config.php';
    if (!is_readable($datei)) {
        pushAbbrechen('push-config.php fehlt oder ist nicht lesbar.');
    }

    $konfig = require $datei;
    if (!is_array($konfig)) {
        pushAbbrechen('push-config.php liefert kein Array.');
    }

    $werte = [
        'vapid_oeffentlich' => (string) ($konfig['vapid_oeffentlich'] ?? ''),
        'vapid_privat' => (string) ($konfig['vapid_privat'] ?? ''),
        'vapid_subject' => (string) ($konfig['vapid_subject'] ?? 'mailto:office@hegerberg.at'),
        'versand_token' => (string) ($konfig['versand_token'] ?? ''),
        'datenverzeichnis' => (string) ($konfig['datenverzeichnis'] ?? '') ?: __DIR__ . '/../../push-daten',
    ];

    foreach (['vapid_oeffentlich', 'vapid_privat', 'versand_token'] as $pflicht) {
        if ($werte[$pflicht] === '') {
            pushAbbrechen('push-config.php: Wert „' . $pflicht . '“ fehlt.');
        }
    }

    return $werte;
}

/**
 * Liefert das beschreibbare Datenverzeichnis und legt es bei Bedarf an.
 *
 * @param array{datenverzeichnis:string} $konfig
 */
function pushDatenverzeichnis(array $konfig): string
{
    foreach ([$konfig['datenverzeichnis'], __DIR__ . '/push-daten'] as $pfad) {
        if (!is_dir($pfad)) {
            // Fehler werden bewusst geschluckt: schlägt das Anlegen fehl,
            // greift der zweite Pfad.
            @mkdir($pfad, 0770, true);
        }

        if (!is_dir($pfad) || !is_writable($pfad)) {
            continue;
        }

        // Liegt das Verzeichnis im Webroot, muss der direkte Zugriff über den
        // Browser gesperrt sein. Greift auf Apache/LiteSpeed.
        $htaccess = $pfad . '/.htaccess';
        if (!is_file($htaccess)) {
            @file_put_contents($htaccess, "Deny from all\n");
        }

        return $pfad;
    }

    pushAbbrechen('Kein beschreibbares Datenverzeichnis gefunden (versucht: '
        . $konfig['datenverzeichnis'] . ', ' . __DIR__ . '/push-daten).');
}

/**
 * Liest eine JSON-Datei aus dem Datenverzeichnis. Fehlt sie oder ist sie
 * beschädigt, kommt ein leeres Array zurück.
 *
 * @return array<mixed>
 */
function pushLesen(string $verzeichnis, string $name): array
{
    $inhalt = @file_get_contents($verzeichnis . '/' . $name);
    if ($inhalt === false || $inhalt === '') {
        return [];
    }

    $daten = json_decode($inhalt, true);

    return is_array($daten) ? $daten : [];
}

/**
 * Liest eine JSON-Datei, übergibt den Inhalt an $aendern und schreibt das
 * Ergebnis zurück – alles unter einer exklusiven Dateisperre.
 *
 * @param callable(array<mixed>):array<mixed> $aendern
 * @return array<mixed> der geschriebene Stand
 */
function pushBearbeiten(string $verzeichnis, string $name, callable $aendern): array
{
    $datei = $verzeichnis . '/' . $name;

    $zeiger = @fopen($datei, 'c+');
    if ($zeiger === false) {
        pushAbbrechen($name . ' lässt sich nicht öffnen.');
    }

    try {
        if (!flock($zeiger, LOCK_EX)) {
            pushAbbrechen($name . ' lässt sich nicht sperren.');
        }

        $inhalt = stream_get_contents($zeiger);
        $vorher = is_string($inhalt) && $inhalt !== '' ? json_decode($inhalt, true) : [];
        $nachher = $aendern(is_array($vorher) ? $vorher : []);

        $json = json_encode($nachher, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false) {
            pushAbbrechen($name . ': Daten lassen sich nicht als JSON schreiben.');
        }

        rewind($zeiger);
        ftruncate($zeiger, 0);
        fwrite($zeiger, $json . "\n");
        fflush($zeiger);

        return $nachher;
    } finally {
        flock($zeiger, LOCK_UN);
        fclose($zeiger);
    }
}

/**
 * Schlüssel eines Abonnements in der Ablage. Der Endpunkt kann sehr lang
 * werden, darum der Hash.
 */
function pushSchluessel(string $endpunkt): string
{
    return hash('sha256', $endpunkt);
}
