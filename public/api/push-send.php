<?php

/**
 * Sends a push notification to every stored subscription.
 *
 * The endpoint is not called from the browser but from the workflow
 * .github/workflows/notify-event.yml, as soon as a new file appears under
 * src/content/events/ after a deploy. Authentication runs through a shared
 * token in the Authorization header:
 *
 *   POST /api/push-send.php
 *   Authorization: Bearer <send_token>
 *   {"key":"2026-09-06-bergmesse","title":"…","text":"…","url":"/…"}
 *
 * "key" is the replay guard: an already-sent key is skipped silently. Without
 * a key it always sends – handy for testing by hand.
 *
 * The library minishlink/web-push lives on the server under ../vendor/ and is
 * uploaded through the manual workflow .github/workflows/php-libraries.yml.
 */

declare(strict_types=1);

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

require_once __DIR__ . '/push-storage.php';

/** Directory holding the Composer installation (without trailing slash). */
const PUSH_VENDOR_DIR = __DIR__ . '/../vendor';

const PUSH_MAX_TITLE = 120;
const PUSH_MAX_TEXT = 300;

/**
 * Reads the Authorization header. Depending on the server it sits in different
 * places – with CGI/FastCGI often only in the REDIRECT_ variant.
 */
function pushTokenFromRequest(): string
{
    $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');

    if ($header === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp($name, 'Authorization') === 0) {
                $header = (string) $value;
                break;
            }
        }
    }

    return preg_match('/^Bearer\s+(\S+)$/i', trim($header), $match) === 1 ? $match[1] : '';
}

/**
 * Trims a text to the allowed length and strips control characters.
 */
function pushText(mixed $value, int $maximum): string
{
    $text = is_string($value) ? $value : '';
    $text = trim(preg_replace('/\s+/u', ' ', str_replace(["\r", "\n", "\0"], ' ', $text)) ?? '');

    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $maximum, 'UTF-8');
    }

    return substr($text, 0, $maximum);
}

/**
 * Loads web-push through the Composer autoloader.
 */
function pushLoadLibrary(): void
{
    if (class_exists(WebPush::class)) {
        return;
    }

    $autoloader = PUSH_VENDOR_DIR . '/autoload.php';
    if (!is_readable($autoloader)) {
        pushFail('web-push nicht gefunden – bitte den Workflow „Upload PHP libraries“ ausführen.');
    }

    require_once $autoloader;

    if (!class_exists(WebPush::class)) {
        pushFail('web-push fehlt in vendor/ – bitte den Workflow „Upload PHP libraries“ ausführen.');
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    pushRespond(405, ['result' => 'error', 'message' => 'Nur POST-Anfragen werden verarbeitet.']);
}

$config = pushConfig();

// hash_equals compares in constant time – the token should not be guessable
// from the response time.
if (!hash_equals($config['send_token'], pushTokenFromRequest())) {
    header('WWW-Authenticate: Bearer');
    pushRespond(401, ['result' => 'error', 'message' => 'Nicht berechtigt.']);
}

$raw = file_get_contents('php://input');
$request = is_string($raw) ? json_decode($raw, true) : null;
if (!is_array($request)) {
    pushRespond(400, ['result' => 'error', 'message' => 'Ungültige Anfrage.']);
}

$title = pushText($request['title'] ?? null, PUSH_MAX_TITLE);
$text = pushText($request['text'] ?? null, PUSH_MAX_TEXT);
$key = pushText($request['key'] ?? null, 200);

if ($title === '') {
    pushRespond(422, ['result' => 'error', 'message' => 'Es fehlt ein Titel.']);
}

// Only targets on this site – a foreign URL in the notification would be an
// open redirect.
$target = pushText($request['url'] ?? null, 300);
if ($target === '' || !str_starts_with($target, '/') || str_starts_with($target, '//')) {
    $target = '/veranstaltungen/';
}

$directory = pushDataDir($config);

$subscriptions = pushRead($directory, PUSH_FILE_SUBSCRIPTIONS);
if ($subscriptions === []) {
    pushRespond(200, ['result' => 'ok', 'recipients' => 0, 'delivered' => 0, 'removed' => 0]);
}

// The library is loaded before the replay guard: if it is missing on the
// server, the key must not be recorded as "sent" – otherwise the event would
// stay silent forever once the library is installed.
pushLoadLibrary();

// Replay guard: the workflow runs after every deploy, and a deploy can be
// triggered by hand as well. A key goes out exactly once.
if ($key !== '') {
    $alreadySent = false;

    pushEdit($directory, PUSH_FILE_SENT, static function (array $list) use (
        $key,
        &$alreadySent,
    ): array {
        if (in_array($key, $list, true)) {
            $alreadySent = true;

            return $list;
        }

        $list[] = $key;

        // Keep only the most recent entries, otherwise the file grows forever.
        return array_slice($list, -PUSH_MAX_SENT);
    });

    if ($alreadySent) {
        pushRespond(200, [
            'result' => 'skipped',
            'message' => 'Für „' . $key . '“ wurde bereits benachrichtigt.',
        ]);
    }
}

$webPush = new WebPush([
    'VAPID' => [
        'subject' => $config['vapid_subject'],
        'publicKey' => $config['vapid_public'],
        'privateKey' => $config['vapid_private'],
    ],
]);
// Every notification of this run is signed identically.
$webPush->setReuseVAPIDHeaders(true);

$payload = json_encode([
    'title' => $title,
    'text' => $text,
    'url' => $target,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

foreach ($subscriptions as $subscription) {
    if (!is_array($subscription)) {
        continue;
    }

    // Subscriptions stored before the rename to English carry "endpunkt".
    $endpoint = $subscription['endpoint'] ?? $subscription['endpunkt'] ?? null;
    if (!is_string($endpoint)) {
        continue;
    }

    try {
        $webPush->queueNotification(
            Subscription::create([
                'endpoint' => $endpoint,
                'publicKey' => (string) ($subscription['p256dh'] ?? ''),
                'authToken' => (string) ($subscription['auth'] ?? ''),
                'contentEncoding' => 'aes128gcm',
            ]),
            $payload,
        );
    } catch (Throwable $error) {
        error_log('Push: Abonnement übersprungen (' . $error->getMessage() . ')');
    }
}

$delivered = 0;
$expired = [];

foreach ($webPush->flush() as $report) {
    if ($report->isSuccess()) {
        $delivered++;
        continue;
    }

    // 404/410 from the push service: the browser deleted the subscription.
    // Such entries would otherwise linger forever and cost time on every run.
    if ($report->isSubscriptionExpired()) {
        $expired[] = pushKey($report->getEndpoint());
        continue;
    }

    error_log('Push: Versand fehlgeschlagen (' . $report->getReason() . ')');
}

if ($expired !== []) {
    pushEdit($directory, PUSH_FILE_SUBSCRIPTIONS, static function (array $list) use ($expired): array {
        foreach ($expired as $key) {
            unset($list[$key]);
        }

        return $list;
    });
}

pushRespond(200, [
    'result' => 'ok',
    'recipients' => count($subscriptions),
    'delivered' => $delivered,
    'removed' => count($expired),
]);
