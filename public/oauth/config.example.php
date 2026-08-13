<?php
/**
 * Vorlage für die Zugangsdaten des OAuth-Proxys.
 *
 * Im Normalbetrieb wird config.php beim Deploy automatisch aus den
 * GitHub-Secrets OAUTH_CLIENT_ID und OAUTH_CLIENT_SECRET erzeugt
 * (Environment „FTP“, siehe .github/workflows/deploy.yml) – von Hand ist
 * dort nichts zu tun.
 *
 * Diese Vorlage ist nur für lokale Tests gedacht: kopieren nach
 * public/oauth/config.php, Werte eintragen. Die Kopie ist über .gitignore
 * vom Repository ausgeschlossen.
 *
 * Die zugehörige GitHub-OAuth-App (Settings → Developer settings →
 * OAuth Apps) braucht:
 *   Homepage URL:               https://hegerberg.at
 *   Authorization callback URL: https://hegerberg.at/oauth/
 */

declare(strict_types=1);

return [
    'client_id' => 'HIER_DIE_CLIENT_ID',
    'client_secret' => 'HIER_DAS_CLIENT_SECRET',
];
