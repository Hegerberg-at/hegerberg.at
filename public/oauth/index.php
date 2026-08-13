<?php
/**
 * OAuth-Proxy für Decap CMS (GitHub-Backend).
 *
 * Decap läuft komplett im Browser und committet über die GitHub-API. Nur der
 * Login braucht einen Server: GitHub tauscht den Autorisierungs-Code ausschließ-
 * lich gegen das client_secret in ein Token, und dieses Secret darf nicht in den
 * Browser gelangen. Genau diesen einen Tausch erledigt diese Datei.
 *
 * Ablauf:
 *   1. Decap öffnet ein Popup auf /oauth/?provider=github&scope=repo
 *   2. Diese Datei leitet zu GitHub weiter (mit state gegen CSRF)
 *   3. GitHub schickt den Benutzer mit ?code=… hierher zurück
 *   4. Diese Datei tauscht code+secret gegen ein Token
 *   5. Das Token geht per postMessage an das Admin-Fenster, Popup schließt
 *
 * Einrichtung siehe public/oauth/config.example.php.
 */

declare(strict_types=1);

/** Origin der Website – Ziel und einzig akzeptierte Quelle des postMessage. */
const SITE_ORIGIN = 'https://hegerberg.at';
const PROVIDER = 'github';

$konfigDatei = __DIR__ . '/config.php';
$konfig = is_readable($konfigDatei) ? require $konfigDatei : [];

$clientId = (string) ($konfig['client_id'] ?? getenv('GITHUB_CLIENT_ID') ?: '');
$clientSecret = (string) ($konfig['client_secret'] ?? getenv('GITHUB_CLIENT_SECRET') ?: '');

if ($clientId === '' || $clientSecret === '') {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("OAuth ist nicht konfiguriert: config.php fehlt oder ist unvollständig.\n");
}

/**
 * Liefert das Handshake-Skript an das Popup aus.
 *
 * Decap wartet auf "authorizing:<provider>", antwortet darauf und bekommt
 * erst dann das Ergebnis – deshalb die Reihenfolge unten.
 */
function antworten(string $status, array $inhalt)
{
    $nachricht = sprintf(
        'authorization:%s:%s:%s',
        PROVIDER,
        $status,
        json_encode($inhalt, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
    );

    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');

    $nachrichtJs = json_encode($nachricht, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    $originJs = json_encode(SITE_ORIGIN, JSON_THROW_ON_ERROR);
    $handshakeJs = json_encode('authorizing:' . PROVIDER, JSON_THROW_ON_ERROR);

    echo <<<HTML
    <!doctype html>
    <html lang="de">
      <head><meta charset="utf-8"><title>Anmeldung</title></head>
      <body>
        <p>Anmeldung wird abgeschlossen …</p>
        <script>
          (function () {
            var nachricht = {$nachrichtJs};
            var origin = {$originJs};

            if (!window.opener) {
              document.body.textContent =
                'Dieses Fenster wurde nicht vom CMS geöffnet.';
              return;
            }

            function empfangen(e) {
              if (e.origin !== origin) return;
              window.removeEventListener('message', empfangen, false);
              window.opener.postMessage(nachricht, origin);
            }

            window.addEventListener('message', empfangen, false);
            window.opener.postMessage({$handshakeJs}, origin);
          })();
        </script>
      </body>
    </html>
    HTML;
    exit;
}

/**
 * POST an GitHub. Nutzt cURL, fällt aber auf Streams zurück – auf Shared
 * Hosting ist mal die eine, mal die andere Erweiterung abgeschaltet.
 *
 * @return array{0: string|false, 1: string} Antwortkörper und Fehlertext
 */
function http_post(string $url, array $felder): array
{
    $koerper = http_build_query($felder);
    $header = [
        'Accept: application/json',
        'Content-Type: application/x-www-form-urlencoded',
        'User-Agent: hegerberg.at-oauth',
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => $header,
            CURLOPT_POSTFIELDS => $koerper,
        ]);
        $antwort = curl_exec($ch);
        $fehler = curl_error($ch);
        curl_close($ch);

        return [$antwort, $fehler];
    }

    if (!ini_get('allow_url_fopen')) {
        return [false, 'Weder cURL noch allow_url_fopen sind verfügbar.'];
    }

    $antwort = @file_get_contents($url, false, stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $header),
            'content' => $koerper,
            'timeout' => 15,
            'ignore_errors' => true,
        ],
    ]));

    return [$antwort, $antwort === false ? 'Anfrage an GitHub fehlgeschlagen.' : ''];
}

// ---------------------------------------------------------------- Schritt 1
if (!isset($_GET['code'])) {
    $state = bin2hex(random_bytes(16));

    setcookie('decap_oauth_state', $state, [
        'expires' => time() + 600,
        'path' => '/oauth/',
        'secure' => true,
        'httponly' => true,
        // Lax genügt: GitHub schickt den Benutzer per Top-Level-Navigation
        // zurück, dabei wird das Cookie mitgesendet.
        'samesite' => 'Lax',
    ]);

    // Nur erwartete Zeichen durchlassen, damit der Parameter nicht als
    // Einfallstor in die GitHub-URL dient.
    $scope = (string) ($_GET['scope'] ?? 'repo');
    if ($scope === '' || preg_match('/[^a-z_:,]/', $scope) === 1) {
        $scope = 'repo';
    }

    header('Location: https://github.com/login/oauth/authorize?' . http_build_query([
        'client_id' => $clientId,
        'scope' => $scope,
        'state' => $state,
    ]), true, 302);
    exit;
}

// ---------------------------------------------------------------- Schritt 2
$erwartet = (string) ($_COOKIE['decap_oauth_state'] ?? '');
$erhalten = (string) ($_GET['state'] ?? '');

if ($erwartet === '' || !hash_equals($erwartet, $erhalten)) {
    antworten('error', ['message' => 'Ungültiger state – bitte erneut anmelden.']);
}

// Cookie hat seinen Zweck erfüllt.
setcookie('decap_oauth_state', '', [
    'expires' => time() - 3600,
    'path' => '/oauth/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax',
]);

[$antwort, $fehler] = http_post('https://github.com/login/oauth/access_token', [
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'code' => (string) $_GET['code'],
]);

if ($antwort === false) {
    antworten('error', ['message' => 'GitHub nicht erreichbar: ' . $fehler]);
}

$daten = json_decode((string) $antwort, true);

if (!is_array($daten) || !isset($daten['access_token'])) {
    // GitHub liefert je nach Fehler nur "error" ohne Beschreibung.
    antworten('error', [
        'message' => (string) (
            $daten['error_description']
                ?? $daten['error']
                ?? 'Token-Tausch fehlgeschlagen.'
        ),
    ]);
}

antworten('success', [
    'token' => (string) $daten['access_token'],
    'provider' => PROVIDER,
]);
