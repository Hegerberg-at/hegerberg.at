<?php

/**
 * Template for the credentials of the push notifications.
 *
 * In normal operation push-config.php is generated automatically on deploy
 * from the GitHub secrets (environment „FTP“, see
 * .github/workflows/deploy.yml) – there is nothing to do by hand.
 *
 * This template is only meant for local tests: copy it to
 * public/api/push-config.php and fill in the values. The copy is excluded from
 * the repository via .gitignore.
 *
 * The VAPID keys identify the sender towards the push services of Google,
 * Mozilla and Apple. They are generated once and not rotated afterwards – a
 * new key pair invalidates every existing subscription. How to generate them
 * is described in the README under „Push-Benachrichtigungen“.
 */

declare(strict_types=1);

return [
    // Public VAPID key (base64url, 87 characters). Delivered to the browser
    // through push.php?action=key.
    'vapid_public' => 'HIER_DER_OEFFENTLICHE_VAPID_SCHLUESSEL',

    // Private VAPID key (base64url, 43 characters). Stays on the server.
    'vapid_private' => 'HIER_DER_PRIVATE_VAPID_SCHLUESSEL',

    // Contact address for the push services – „mailto:“ or an https address.
    'vapid_subject' => 'mailto:office@hegerberg.at',

    // Shared secret between the GitHub Action and push-send.php.
    // Generate e.g. with: openssl rand -hex 32
    'send_token' => 'HIER_DER_VERSAND_TOKEN',

    // Storage of the subscriptions. By default one level above public_html, so
    // the file cannot be reached through the browser. If the directory cannot
    // be created there, the script falls back to api/push-daten/ (with an
    // .htaccess lock). The directory name stays as it is – it holds live data.
    'data_dir' => __DIR__ . '/../../push-daten',
];
