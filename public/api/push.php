<?php

/**
 * Endpoint for the browser: deliver the VAPID key, create push subscriptions
 * and remove them again.
 *
 * The script is called from src/components/PushSignup.astro.
 *
 *   GET  ?action=key                  → public VAPID key
 *   POST {"action":"subscribe",...}   → store subscription
 *   POST {"action":"unsubscribe",...} → delete subscription
 *   POST {"action":"check",...}       → is the endpoint known here?
 *
 * The actual sending runs separately through push-send.php.
 *
 * The credentials come from push-config.php (see push-config.example.php),
 * the storage of the subscriptions lives in push-storage.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/push-storage.php';

/** A push endpoint is a URL of the respective push service. */
const PUSH_MAX_ENDPOINT_LENGTH = 1000;

/** Size of the accepted request body. */
const PUSH_MAX_BODY = 8192;

/**
 * Reads the JSON body of the request.
 *
 * @return array<string,mixed>
 */
function pushBody(): array
{
    $raw = file_get_contents('php://input', false, null, 0, PUSH_MAX_BODY + 1);
    if ($raw === false || strlen($raw) > PUSH_MAX_BODY) {
        pushRespond(413, ['result' => 'error', 'message' => 'Anfrage zu groß.']);
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        pushRespond(400, ['result' => 'error', 'message' => 'Ungültige Anfrage.']);
    }

    return $data;
}

/**
 * Validates a push endpoint. Which push service it belongs to is up to the
 * browser – so the host is not restricted, only the scheme.
 */
function pushValidateEndpoint(mixed $value): string
{
    $endpoint = is_string($value) ? trim($value) : '';

    if (
        $endpoint === ''
        || strlen($endpoint) > PUSH_MAX_ENDPOINT_LENGTH
        || !str_starts_with($endpoint, 'https://')
        || !filter_var($endpoint, FILTER_VALIDATE_URL)
    ) {
        pushRespond(422, ['result' => 'error', 'message' => 'Ungültiger Push-Endpunkt.']);
    }

    return $endpoint;
}

/**
 * Validates one of the two base64url encoded keys of the subscription.
 */
function pushValidateKey(mixed $value, int $minimum, int $maximum): string
{
    $key = is_string($value) ? trim($value) : '';
    $length = strlen($key);

    if ($length < $minimum || $length > $maximum || preg_match('/^[A-Za-z0-9_-]+=*$/', $key) !== 1) {
        pushRespond(422, ['result' => 'error', 'message' => 'Ungültiger Schlüssel im Abonnement.']);
    }

    return $key;
}

$config = pushConfig();
$method = $_SERVER['REQUEST_METHOD'] ?? '';

// The public VAPID key is – as the name says – public. It is not baked into
// the page but fetched here: that way the build needs to know nothing about
// the keys.
if ($method === 'GET') {
    if (($_GET['action'] ?? '') !== 'key') {
        pushRespond(400, ['result' => 'error', 'message' => 'Unbekannte Aktion.']);
    }

    pushRespond(200, ['result' => 'ok', 'key' => $config['vapid_public']]);
}

if ($method !== 'POST') {
    header('Allow: GET, POST');
    pushRespond(405, ['result' => 'error', 'message' => 'Nur GET- und POST-Anfragen werden verarbeitet.']);
}

$request = pushBody();
$action = is_string($request['action'] ?? null) ? $request['action'] : '';
$directory = pushDataDir($config);

if ($action === 'subscribe') {
    $subscription = is_array($request['subscription'] ?? null) ? $request['subscription'] : [];
    $keys = is_array($subscription['keys'] ?? null) ? $subscription['keys'] : [];

    $endpoint = pushValidateEndpoint($subscription['endpoint'] ?? null);
    // 65 byte public key resp. 16 byte authentication secret, base64url
    // encoded – with some headroom in both directions.
    $p256dh = pushValidateKey($keys['p256dh'] ?? null, 80, 100);
    $auth = pushValidateKey($keys['auth'] ?? null, 16, 40);

    $full = false;
    pushEdit($directory, PUSH_FILE_SUBSCRIPTIONS, function (array $subscriptions) use (
        $endpoint,
        $p256dh,
        $auth,
        &$full,
    ): array {
        $key = pushKey($endpoint);

        if (!isset($subscriptions[$key]) && count($subscriptions) >= PUSH_MAX_SUBSCRIPTIONS) {
            $full = true;

            return $subscriptions;
        }

        $subscriptions[$key] = [
            'endpoint' => $endpoint,
            'p256dh' => $p256dh,
            'auth' => $auth,
            // On a repeated subscription the original date stays in place.
            'created' => (string) ($subscriptions[$key]['created'] ?? $subscriptions[$key]['angelegt'] ?? gmdate('c')),
        ];

        return $subscriptions;
    });

    if ($full) {
        pushFail('Höchstzahl von ' . PUSH_MAX_SUBSCRIPTIONS . ' Abonnements erreicht.');
    }

    pushRespond(200, ['result' => 'ok', 'message' => 'Benachrichtigungen sind aktiviert.']);
}

if ($action === 'unsubscribe') {
    $endpoint = pushValidateEndpoint($request['endpoint'] ?? null);

    pushEdit($directory, PUSH_FILE_SUBSCRIPTIONS, static function (array $subscriptions) use ($endpoint): array {
        unset($subscriptions[pushKey($endpoint)]);

        return $subscriptions;
    });

    pushRespond(200, ['result' => 'ok', 'message' => 'Benachrichtigungen sind abgeschaltet.']);
}

// The browser can keep a subscription that was deleted on the server long ago
// (e.g. because the push service reported it as expired). So the display stays
// correct, the page asks for the current state here.
if ($action === 'check') {
    $endpoint = pushValidateEndpoint($request['endpoint'] ?? null);
    $subscriptions = pushRead($directory, PUSH_FILE_SUBSCRIPTIONS);

    pushRespond(200, [
        'result' => 'ok',
        'known' => isset($subscriptions[pushKey($endpoint)]),
    ]);
}

pushRespond(400, ['result' => 'error', 'message' => 'Unbekannte Aktion.']);
