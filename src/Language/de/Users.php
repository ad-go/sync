<?php

declare(strict_types=1);

/**
 * German texts of the Users admin (Views/users/panel.php). Usage: lang('Users.title').
 * Placeholders {0}, {1}, ... are filled with the arguments of lang().
 */
return [
    'title'      => 'Benutzer',
    'page_title' => 'Benutzer',

    'col' => [
        'id'       => 'ID',
        'username' => 'Benutzername',
        'email'    => 'E-Mail',
        'groups'   => 'Gruppen',
        'active'   => 'Aktiv',
    ],

    // Editor fields
    'field' => [
        'username'      => 'Benutzername',
        'email'         => 'E-Mail',
        'password'      => 'Passwort',
        'password_hint' => 'Leer lassen, um es beizubehalten',
        'groups'        => 'Gruppen',
        'active'        => 'Aktiv',
        'yes'           => 'Ja',
        'no'            => 'Nein',
    ],

    'btn' => [
        'new'    => 'Neu',
        'export' => 'Excel exportieren',
        'import' => 'Excel importieren',
    ],

    'delete' => [
        'title'   => 'Benutzer löschen',
        'message' => 'Diese Aktion kann nicht rückgängig gemacht werden.',
        'button'  => 'Löschen',
        'tooltip' => 'Löschen',
    ],

    'twofa' => [
        'tooltip' => '2FA anzeigen',
        'heading' => 'Authenticator (2FA)',
        'loading' => 'Wird geladen …',
        'error'   => 'Konnte nicht geladen werden.',
        'key'     => 'Manueller Schlüssel:',
        'codes'   => 'Wiederherstellungscodes (durchgestrichen = verwendet):',
        'close'   => 'Schließen',
    ],

    'import' => [
        'title'      => 'Benutzer importieren (Excel)',
        'format'     => 'Ein Benutzer pro Zeile, in dieser Reihenfolge: <code>username, email, password, groups, active</code>. Gruppen werden mit <code>|</code> getrennt (zum Beispiel <code>user|beta</code>); active ist <code>1</code> oder <code>0</code>. Eine erste Zeile mit den Spaltennamen wird übersprungen.',
        'model'      => 'Excel-Vorlage herunterladen',
        'drop'       => 'Excel-Datei (.xlsx) auswählen oder hier ablegen',
        'drop_hint'  => '.xlsx · ein Benutzer pro Zeile',
        'cancel'     => 'Abbrechen',
        'run'        => 'Importieren',
        'done'       => 'Fertig.',
        'failed'     => 'Import fehlgeschlagen.',
        'close'      => 'Schließen',
    ],

    // DataTables and DataTables Editor built-in texts
    'dt' => [
        'search'        => 'Suchen:',
        'info'          => '_START_ bis _END_ von _TOTAL_ Benutzern',
        'info_empty'    => '0 bis 0 von 0 Benutzern',
        'previous'      => 'Zurück',
        'next'          => 'Weiter',
        'empty'         => 'Noch keine Benutzer',
        'zero'          => 'Keine passenden Benutzer gefunden',
        'loading'       => 'Wird geladen …',
        'system_error'  => 'Ein Systemfehler ist aufgetreten. Bitte versuchen Sie es erneut.',
        'create_title'  => 'Neuer Benutzer',
        'create_submit' => 'Erstellen',
        'edit_title'    => 'Benutzer bearbeiten',
        'edit_submit'   => 'Speichern',
        'remove_title'  => 'Benutzer löschen',
        'remove_submit' => 'Löschen',
        'remove_confirm'     => 'Sollen %d Benutzer gelöscht werden?',
        'remove_confirm_one' => 'Diesen Benutzer löschen?',
    ],
];
