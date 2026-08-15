<?php

/**
 * Vorlage für die SMTP-Zugangsdaten des Kontaktformulars.
 *
 * Im Normalbetrieb wird config.php beim Deploy automatisch aus den
 * GitHub-Secrets erzeugt (Environment „FTP“, siehe
 * .github/workflows/deploy.yml) – von Hand ist dort nichts zu tun.
 *
 * Diese Vorlage ist nur für lokale Tests gedacht: kopieren nach
 * public/api/config.php, Werte eintragen. Die Kopie ist über .gitignore
 * vom Repository ausgeschlossen.
 *
 * Hintergrund: Ohne SMTP-Anmeldung ersetzt Hostinger den Absender durch die
 * am Server hinterlegte Adresse. Darum meldet sich das Formular am Postfach
 * no-reply@hegerberg.at an und versendet darüber. „benutzer“ und „absender“
 * müssen dieselbe Adresse sein – ein fremder Absender wird vom Mailserver
 * abgewiesen oder wieder überschrieben.
 *
 * Das Postfach wird im hPanel unter „E-Mails → E-Mail-Konten“ angelegt.
 */

declare(strict_types=1);

return [
    // SMTP-Server aus dem hPanel („E-Mail-Konten → Verbindungsdaten“).
    'host' => 'smtp.hostinger.com',

    // 465 mit 'ssl' oder 587 mit 'tls'. ('keine' schaltet die Verschlüsselung
    // ab – ausschließlich für lokale Tests gegen einen Dummy-Server.)
    'port' => 465,
    'verschluesselung' => 'ssl',

    // Postfach, an dem sich das Script anmeldet.
    'benutzer' => 'no-reply@hegerberg.at',
    'passwort' => 'HIER_DAS_POSTFACH_PASSWORT',

    // Absender der Mail – muss zum Postfach oben passen.
    'absender' => 'no-reply@hegerberg.at',
    'absender_name' => 'Schutzhaus am Hegerberg',

    // Empfänger der Anfragen.
    'empfaenger' => 'office@hegerberg.at',
];
