<?php

/**
 * Template for the SMTP credentials of the contact form.
 *
 * In normal operation config.php is generated automatically on deploy from the
 * GitHub secrets (environment „FTP“, see .github/workflows/deploy.yml) – there
 * is nothing to do by hand.
 *
 * This template is only meant for local tests: copy it to
 * public/api/config.php and fill in the values. The copy is excluded from the
 * repository via .gitignore.
 *
 * Background: without SMTP authentication Hostinger replaces the sender with
 * the address configured on the server. That is why the form authenticates
 * against the mailbox no-reply@hegerberg.at and sends through it. "username"
 * and "from" have to be the same address – a foreign sender is rejected by the
 * mail server or overwritten again.
 *
 * The mailbox is created in the hPanel under „E-Mails → E-Mail-Konten“.
 */

declare(strict_types=1);

return [
    // SMTP server from the hPanel („E-Mail-Konten → Verbindungsdaten“).
    'host' => 'smtp.hostinger.com',

    // 465 with 'ssl' or 587 with 'tls'. ('none' turns encryption off – for
    // local tests against a dummy server only.)
    'port' => 465,
    'encryption' => 'ssl',

    // Mailbox the script authenticates against.
    'username' => 'no-reply@hegerberg.at',
    'password' => 'HIER_DAS_POSTFACH_PASSWORT',

    // Sender of the mail – has to match the mailbox above.
    'from' => 'no-reply@hegerberg.at',
    'from_name' => 'Schutzhaus am Hegerberg',

    // Recipient of the requests.
    'recipient' => 'office@hegerberg.at',
];
