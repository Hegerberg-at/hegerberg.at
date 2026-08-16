<?php

/**
 * Vorlage für die Zugangsdaten der Push-Benachrichtigungen.
 *
 * Im Normalbetrieb wird push-config.php beim Deploy automatisch aus den
 * GitHub-Secrets erzeugt (Environment „FTP“, siehe
 * .github/workflows/deploy.yml) – von Hand ist dort nichts zu tun.
 *
 * Diese Vorlage ist nur für lokale Tests gedacht: kopieren nach
 * public/api/push-config.php, Werte eintragen. Die Kopie ist über .gitignore
 * vom Repository ausgeschlossen.
 *
 * Die VAPID-Schlüssel identifizieren den Absender gegenüber den Push-Diensten
 * von Google, Mozilla und Apple. Sie werden einmalig erzeugt und danach nicht
 * mehr gewechselt – ein neues Schlüsselpaar macht alle bestehenden
 * Abonnements ungültig. Wie sie erzeugt werden, steht in der README unter
 * „Push-Benachrichtigungen“.
 */

declare(strict_types=1);

return [
    // Öffentlicher VAPID-Schlüssel (base64url, 87 Zeichen). Wird über
    // push.php?aktion=schluessel an den Browser ausgeliefert.
    'vapid_oeffentlich' => 'HIER_DER_OEFFENTLICHE_VAPID_SCHLUESSEL',

    // Privater VAPID-Schlüssel (base64url, 43 Zeichen). Bleibt auf dem Server.
    'vapid_privat' => 'HIER_DER_PRIVATE_VAPID_SCHLUESSEL',

    // Kontaktadresse für die Push-Dienste – „mailto:“ oder eine https-Adresse.
    'vapid_subject' => 'mailto:office@hegerberg.at',

    // Gemeinsames Geheimnis zwischen GitHub Action und push-versand.php.
    // Erzeugen z. B. mit: openssl rand -hex 32
    'versand_token' => 'HIER_DER_VERSAND_TOKEN',

    // Ablage der Abonnements. Standardmäßig eine Ebene über public_html, damit
    // die Datei über den Browser nicht erreichbar ist. Lässt sich das
    // Verzeichnis dort nicht anlegen, weicht das Script auf api/push-daten/
    // aus (mit .htaccess-Sperre).
    'datenverzeichnis' => __DIR__ . '/../../push-daten',
];
