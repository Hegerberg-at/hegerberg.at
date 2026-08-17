<?php
/**
 * OAuth proxy for Decap CMS (GitHub backend).
 *
 * Decap runs entirely in the browser and commits through the GitHub API. Only
 * the login needs a server: GitHub exchanges the authorization code for a
 * token against the client_secret only, and that secret must never reach the
 * browser. This file performs exactly that one exchange.
 *
 * Flow:
 *   1. Decap opens a popup on /oauth/?provider=github&scope=repo
 *   2. this file redirects to GitHub (with state against CSRF)
 *   3. GitHub sends the user back here with ?code=…
 *   4. this file exchanges code+secret for a token
 *   5. the token goes to the admin window via postMessage, the popup closes
 *
 * The credentials come from config.php. That file is deliberately not in the
 * repository – it is generated from the GitHub secrets on every deploy (see
 * .github/workflows/deploy.yml). For local tests see config.example.php.
 */

declare(strict_types=1);

/** Origin of the website – target and only accepted source of the postMessage. */
const SITE_ORIGIN = 'https://hegerberg.at';
const PROVIDER = 'github';

$configFile = __DIR__ . '/config.php';
$config = is_readable($configFile) ? require $configFile : [];

$clientId = (string) ($config['client_id'] ?? getenv('GITHUB_CLIENT_ID') ?: '');
$clientSecret = (string) ($config['client_secret'] ?? getenv('GITHUB_CLIENT_SECRET') ?: '');

if ($clientId === '' || $clientSecret === '') {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("OAuth ist nicht konfiguriert: config.php fehlt oder ist unvollständig.\n");
}

/**
 * Delivers the handshake script to the popup.
 *
 * Decap waits for "authorizing:<provider>", answers it and only then receives
 * the result – hence the order below.
 */
function respond(string $status, array $content)
{
    $message = sprintf(
        'authorization:%s:%s:%s',
        PROVIDER,
        $status,
        json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
    );

    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');

    $messageJs = json_encode($message, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
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
            var message = {$messageJs};
            var origin = {$originJs};

            if (!window.opener) {
              document.body.textContent =
                'Dieses Fenster wurde nicht vom CMS geöffnet.';
              return;
            }

            function receive(e) {
              if (e.origin !== origin) return;
              window.removeEventListener('message', receive, false);
              window.opener.postMessage(message, origin);
            }

            window.addEventListener('message', receive, false);
            window.opener.postMessage({$handshakeJs}, origin);
          })();
        </script>
      </body>
    </html>
    HTML;
    exit;
}

/**
 * POST to GitHub. Uses cURL but falls back to streams – on shared hosting
 * sometimes one extension is disabled, sometimes the other.
 *
 * @return array{0: string|false, 1: string} response body and error text
 */
function http_post(string $url, array $fields): array
{
    $body = http_build_query($fields);
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
            CURLOPT_POSTFIELDS => $body,
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        return [$response, $error];
    }

    if (!ini_get('allow_url_fopen')) {
        return [false, 'Weder cURL noch allow_url_fopen sind verfügbar.'];
    }

    $response = @file_get_contents($url, false, stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $header),
            'content' => $body,
            'timeout' => 15,
            'ignore_errors' => true,
        ],
    ]));

    return [$response, $response === false ? 'Anfrage an GitHub fehlgeschlagen.' : ''];
}

// -------------------------------------------------------------------- Step 1
if (!isset($_GET['code'])) {
    $state = bin2hex(random_bytes(16));

    setcookie('decap_oauth_state', $state, [
        'expires' => time() + 600,
        'path' => '/oauth/',
        'secure' => true,
        'httponly' => true,
        // Lax is enough: GitHub sends the user back through a top-level
        // navigation, which carries the cookie along.
        'samesite' => 'Lax',
    ]);

    // Only let expected characters through, so the parameter cannot serve as a
    // way into the GitHub URL.
    $scope = (string) ($_GET['scope'] ?? 'public_repo');
    if ($scope === '' || preg_match('/[^a-z_:,]/', $scope) === 1) {
        $scope = 'public_repo';
    }

    header('Location: https://github.com/login/oauth/authorize?' . http_build_query([
        'client_id' => $clientId,
        'scope' => $scope,
        'state' => $state,
    ]), true, 302);
    exit;
}

// -------------------------------------------------------------------- Step 2
$expected = (string) ($_COOKIE['decap_oauth_state'] ?? '');
$received = (string) ($_GET['state'] ?? '');

if ($expected === '' || !hash_equals($expected, $received)) {
    respond('error', ['message' => 'Ungültiger state – bitte erneut anmelden.']);
}

// The cookie has served its purpose.
setcookie('decap_oauth_state', '', [
    'expires' => time() - 3600,
    'path' => '/oauth/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax',
]);

[$response, $error] = http_post('https://github.com/login/oauth/access_token', [
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'code' => (string) $_GET['code'],
]);

if ($response === false) {
    respond('error', ['message' => 'GitHub nicht erreichbar: ' . $error]);
}

$data = json_decode((string) $response, true);

if (!is_array($data) || !isset($data['access_token'])) {
    // Depending on the failure GitHub only returns "error" without a
    // description.
    respond('error', [
        'message' => (string) (
            $data['error_description']
                ?? $data['error']
                ?? 'Token-Tausch fehlgeschlagen.'
        ),
    ]);
}

respond('success', [
    'token' => (string) $data['access_token'],
    'provider' => PROVIDER,
]);
