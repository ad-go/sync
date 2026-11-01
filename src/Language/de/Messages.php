<?php

declare(strict_types=1);

/**
 * German texts of the messages the controllers and libraries return to the browser (flash, JSON replies).
 * Placeholders {0}, {1}, ... are filled with the arguments of lang('Messages.<key>', [args]).
 */
return [
    'settings' => [
        'dteKeyInvalid' => 'Der DTE-Schlüssel hat ein unerwartetes Format.',
        'localesNone' => 'Wähle mindestens eine Sprache aus.',
        'brandingSaved'         => 'Branding gespeichert.',
        'logoRemoved'           => 'Logo entfernt.',
        'settingsSaved'         => 'Einstellungen gespeichert.',
        'nothingToChange'       => 'Nichts zu ändern.',
        'identitySaved'         => 'Identität gespeichert. Die passende Änderung auch auf dem Partnerknoten übernehmen.',
        'identityInvalid'       => 'Ungültiger Wert für {0}: nur Kleinbuchstaben, Ziffern, - und _.',
        'secretInvalid'         => 'Ungültiges Geheimnis: 32–128 Buchstaben/Ziffern.',
        'siteNameLength'        => 'Der Seitenname muss 1–80 Zeichen lang sein.',
        'accentColor'           => 'Die Akzentfarbe muss ein Wert #rrggbb sein.',
        'logoTooBig'            => 'Das Logo darf höchstens {0} MiB groß sein.',
        'logoType'              => 'Das Logo muss png, jpg, svg oder webp sein.',
        'folderOutside'         => 'Kein Ordner im synchronisierten Verzeichnis.',
        'saved'                 => 'Gespeichert',
        'mailNoTarget'          => 'Zuerst Alarm-E-Mail und SMTP-Host speichern.',
        'mailNotSent'           => 'Die E-Mail wurde nicht gesendet: {0}',
        'mailSent'              => 'Test-E-Mail an {0} gesendet.',
        'telegramNotConfigured' => 'Telegram ist nicht eingerichtet — zuerst Bot-Token und Chat-ID setzen.',
        'telegramSent'          => 'Testnachricht gesendet.',
        'telegramFailed'        => 'Senden fehlgeschlagen — Token und Chat-ID im Server-Log prüfen.',
    ],
    'users' => [
        'created'        => 'Benutzer angelegt.',
        'deleted'        => 'Benutzer gelöscht.',
        'unknown'        => 'Unbekannter Benutzer.',
        'unknownAction'  => 'Unbekannte Aktion.',
        'noFile'         => 'Keine Datei hochgeladen.',
        'xlsxOnly'       => 'Nur Excel-Dateien (.xlsx) können importiert werden.',
        'notSuperadmin'  => 'Kein Superadmin.',
        'notSetUp'       => 'Noch nicht eingerichtet — der QR-Code erscheint, wenn sich der Benutzer das erste Mal anmeldet.',
        'usernameLength' => 'Der Benutzername muss 1–30 Zeichen lang sein.',
        'invalidEmail'   => 'Ungültige E-Mail.',
        'passwordNeeded' => 'Ein Passwort ist erforderlich.',
        'noGroup'        => 'Wählen Sie mindestens eine Gruppe.',
        'usernameTaken'  => 'Dieser Benutzername ist bereits vergeben.',
        'emailTaken'     => 'Diese E-Mail ist bereits vergeben.',
        'ownAccount'     => 'Das eigene Konto kann nicht gelöscht werden.',
        'lastSuperadmin' => 'Der letzte Superadmin kann nicht gelöscht werden.',
        'importRow'      => 'Zeile {0}: {1}',
        'imported'       => '{0} Benutzer importiert.',
        'importErrors'   => 'Fehler: {0}',
        'totpSubject'    => '{0}: Authenticator einrichten',
    ],
    'login' => [
        'invalid2fa' => 'Ungültiger 2FA-Code.',
    ],
    'lock' => [
        'peerUnreachable' => 'Partner nicht erreichbar ({0}); die Sperre bleibt bis zum Ablauf bestehen',
        'notApplied'      => 'der Partner hat unsere Änderungen noch nicht übernommen; die Sperre bleibt bis zum Ablauf bestehen',
    ],
];
