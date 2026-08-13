<?php
/**
 * Vorlage für die Zugangsdaten des OAuth-Proxys.
 *
 * Diese Datei gehört NICHT ins Git-Repository, sobald echte Werte drinstehen –
 * deshalb liegt sie hier nur als Beispiel. Vorgehen:
 *
 *   1. Auf GitHub unter Settings → Developer settings → OAuth Apps eine neue
 *      App anlegen:
 *        Homepage URL:               https://hegerberg.at
 *        Authorization callback URL: https://hegerberg.at/oauth/
 *   2. Diese Datei kopieren, Client-ID und Secret eintragen.
 *   3. Die Kopie einmalig per FTP nach /public_html/oauth/config.php laden.
 *
 * Der Deploy überschreibt sie nicht: die FTP-Action löscht nur Dateien, die
 * sie selbst hochgeladen hat.
 */

declare(strict_types=1);

return [
    'client_id' => 'HIER_DIE_CLIENT_ID',
    'client_secret' => 'HIER_DAS_CLIENT_SECRET',
];
