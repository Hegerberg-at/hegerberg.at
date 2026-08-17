<?php

/**
 * Shared base of the two push endpoints push.php and push-send.php:
 * configuration, storage of the subscriptions and the JSON response.
 *
 * The subscriptions live as a JSON file in the data directory – by default one
 * level above public_html, so they cannot be reached through the browser. If
 * the directory cannot be created there (some hosts lock down everything
 * outside the web root), the script falls back to api/push-data/ and drops an
 * .htaccess with "Deny from all" in it.
 *
 * Writing always happens under a file lock, so two concurrent subscriptions do
 * not overwrite each other.
 */

declare(strict_types=1);

/** The server accepts no more subscriptions than this – keeps the file sane. */
const PUSH_MAX_SUBSCRIPTIONS = 5000;

/** This many already-sent events are kept around. */
const PUSH_MAX_SENT = 200;

/** File names inside the data directory. */
const PUSH_FILE_SUBSCRIPTIONS = 'subscriptions.json';
const PUSH_FILE_SENT = 'sent.json';

/**
 * Ends the request with a JSON response.
 *
 * @param array<string,mixed> $data
 */
function pushRespond(int $status, array $data): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Aborts with a generic error message. The technical reason only ends up in
 * the log – details about the server configuration have no place in the
 * response to the browser.
 */
function pushFail(string $reason): never
{
    error_log('Push: ' . $reason);
    pushRespond(500, ['result' => 'error', 'message' => 'Die Anfrage konnte nicht verarbeitet werden.']);
}

/**
 * Loads the push configuration and checks it for completeness.
 *
 * @return array{vapid_public:string,vapid_private:string,vapid_subject:string,send_token:string,data_dir:string}
 */
function pushConfig(): array
{
    $file = __DIR__ . '/push-config.php';
    if (!is_readable($file)) {
        pushFail('push-config.php fehlt oder ist nicht lesbar.');
    }

    $config = require $file;
    if (!is_array($config)) {
        pushFail('push-config.php liefert kein Array.');
    }

    $values = [
        'vapid_public' => (string) ($config['vapid_public'] ?? ''),
        'vapid_private' => (string) ($config['vapid_private'] ?? ''),
        'vapid_subject' => (string) ($config['vapid_subject'] ?? 'mailto:office@hegerberg.at'),
        'send_token' => (string) ($config['send_token'] ?? ''),
        'data_dir' => (string) ($config['data_dir'] ?? '') ?: __DIR__ . '/../../push-daten',
    ];

    foreach (['vapid_public', 'vapid_private', 'send_token'] as $required) {
        if ($values[$required] === '') {
            pushFail('push-config.php: Wert „' . $required . '“ fehlt.');
        }
    }

    return $values;
}

/**
 * Returns the writable data directory and creates it when needed.
 *
 * @param array{data_dir:string} $config
 */
function pushDataDir(array $config): string
{
    // The directory name stays "push-daten": it is the live storage location on
    // the server, and renaming it would orphan the existing subscriptions.
    foreach ([$config['data_dir'], __DIR__ . '/push-daten'] as $path) {
        if (!is_dir($path)) {
            // Errors are swallowed on purpose: if creating fails, the next
            // candidate path takes over.
            @mkdir($path, 0770, true);
        }

        if (!is_dir($path) || !is_writable($path)) {
            continue;
        }

        // If the directory sits inside the web root, direct access through the
        // browser has to be blocked. Works on Apache/LiteSpeed.
        $htaccess = $path . '/.htaccess';
        if (!is_file($htaccess)) {
            @file_put_contents($htaccess, "Deny from all\n");
        }

        return $path;
    }

    pushFail('Kein beschreibbares Datenverzeichnis gefunden (versucht: '
        . $config['data_dir'] . ', ' . __DIR__ . '/push-daten).');
}

/**
 * Reads a JSON file from the data directory. If it is missing or damaged, an
 * empty array comes back.
 *
 * @return array<mixed>
 */
function pushRead(string $directory, string $name): array
{
    $content = @file_get_contents($directory . '/' . $name);
    if ($content === false || $content === '') {
        return [];
    }

    $data = json_decode($content, true);

    return is_array($data) ? $data : [];
}

/**
 * Reads a JSON file, hands the content to $modify and writes the result back –
 * all under an exclusive file lock.
 *
 * @param callable(array<mixed>):array<mixed> $modify
 * @return array<mixed> the state that was written
 */
function pushEdit(string $directory, string $name, callable $modify): array
{
    $file = $directory . '/' . $name;

    $handle = @fopen($file, 'c+');
    if ($handle === false) {
        pushFail($name . ' lässt sich nicht öffnen.');
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            pushFail($name . ' lässt sich nicht sperren.');
        }

        $content = stream_get_contents($handle);
        $before = is_string($content) && $content !== '' ? json_decode($content, true) : [];
        $after = $modify(is_array($before) ? $before : []);

        $json = json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false) {
            pushFail($name . ': Daten lassen sich nicht als JSON schreiben.');
        }

        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, $json . "\n");
        fflush($handle);

        return $after;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

/**
 * Key of a subscription in the storage. The endpoint can get very long, hence
 * the hash.
 */
function pushKey(string $endpoint): string
{
    return hash('sha256', $endpoint);
}
