<?php
/**
 * Template for the credentials of the OAuth proxy.
 *
 * In normal operation config.php is generated automatically on deploy from the
 * GitHub secrets OAUTH_CLIENT_ID and OAUTH_CLIENT_SECRET (environment „FTP“,
 * see .github/workflows/deploy.yml) – there is nothing to do by hand.
 *
 * This template is only meant for local tests: copy it to
 * public/oauth/config.php and fill in the values. The copy is excluded from
 * the repository via .gitignore.
 *
 * The matching GitHub OAuth app (Settings → Developer settings → OAuth Apps)
 * needs:
 *   Homepage URL:               https://hegerberg.at
 *   Authorization callback URL: https://hegerberg.at/oauth/
 */

declare(strict_types=1);

return [
    'client_id' => 'HIER_DIE_CLIENT_ID',
    'client_secret' => 'HIER_DAS_CLIENT_SECRET',
];
