<?php

declare(strict_types=1);

/**
 * German texts of the settings page (Views/settings/index.php, _fields.php and its scripts).
 * Placeholders {0}, {1}, ... are filled with the arguments of lang().
 */
return [
    'title' => 'Einstellungen',
    'card' => [
        'email'    => 'E-Mail',
        'telegram' => 'Telegram',
    ],
    'brand' => [
        'dte_key'       => 'DTE-Lizenzschlüssel',
        'dte_key_set'   => 'gesetzt — leer lassen, um ihn zu behalten',
        'dte_key_empty' => 'nicht gesetzt',
        'dte_key_hint'  => 'Der DataTables-Editor-Schlüssel (die Datei key.js). Den Schlüssel einfügen; er wird beim Verlassen des Feldes gespeichert.',
        'languages'      => 'Sprachen',
        'languages_hint' => 'Besucher können zwischen allen angehakten Sprachen wechseln. Mindestens eine bleibt angehakt.',
        'logo_title'   => 'Bild hier anklicken oder ablegen',
        'logo_empty'   => 'Logo: Bild anklicken oder ablegen',
        'logo_replace' => 'Logo: anklicken oder ablegen zum Ersetzen',
        'logo_formats' => 'PNG · JPG · SVG · WebP, maximal {0} MiB',
        'site_name'    => 'Name der Website',
        'accent'       => 'Akzentfarbe',
        'pick_colour'  => 'Farbe wählen',
        'font'         => 'Schrift',
        'font_default' => '(Standard)',
        'size'         => 'Größe (px)',
        'size_aria'    => 'Schriftgröße (px)',
        'login_field'  => 'Anmeldefeld',
        'login_email'  => 'E-Mail',
        'login_username' => 'Benutzername',
        'login_both'   => 'E-Mail oder Benutzername',
        'autosave'     => 'Änderungen werden automatisch gespeichert',
        'remove_logo'  => 'Logo entfernen',
    ],
    'node' => [
        'fingerprint'        => 'Fingerabdruck des Geheimnisses',
        'fp_hint'            => 'Das Umbenennen eines Knotens oder das Rotieren des Geheimnisses ändert nur {0} — den passenden Wert auf dem Partner von Hand übernehmen.',
        'fp_preview'         => 'Vorschau: der Fingerabdruck, den der Knoten nach dem Speichern erhält (jetzt: {0})',
        'gen_fp_title'       => 'Neues Geheimnis im Feld unten erzeugen (zum Übernehmen speichern)',
        'generate'           => 'Neues Geheimnis erzeugen',
        'copy_fp_title'      => 'Fingerabdruck kopieren',
        'copy'               => 'Kopieren',
        'this_node'          => 'Dieser Knoten',
        'peer_node'          => 'Partnerknoten',
        'new_secret'         => 'Neues gemeinsames Geheimnis',
        'secret_placeholder' => 'leer lassen, um das aktuelle zu behalten',
        'gen_secret_title'   => 'Zufälliges Geheimnis erzeugen (zum Übernehmen speichern)',
        'gen_secret_aria'    => 'Zufälliges Geheimnis erzeugen',
        'copy_secret_title'  => 'Neues Geheimnis kopieren',
        'secret_hint'        => 'Mindestens 32 alphanumerische Zeichen. Leer lassen, um das aktuelle Geheimnis zu behalten.',
        'secret_generated'   => 'Neues Geheimnis erzeugt: der Fingerabdruck wird oben angezeigt; zum Übernehmen speichern.',
        'nothing_to_copy'    => 'Noch nichts zum Kopieren: zuerst ein Geheimnis erzeugen oder eingeben.',
    ],
    'folder' => [
        'pick_title' => 'Synchronisierten Ordner auswählen',
        'pick_hint'  => 'Einen Ordner im synchronisierten Verzeichnis auswählen',
        'pick_aria'  => 'Ordner auswählen',
        'select'     => 'Einen Ordner im Baum auswählen.',
        'use'        => 'Diesen Ordner verwenden',
        'cancel'     => 'Abbrechen',
        'close'      => 'Schließen',
    ],
    'btn' => [
        'save'       => 'Speichern',
        'test_email' => 'E-Mail testen',
        'test_telegram' => 'Telegram testen',
    ],
    'js' => [
        'saving'    => 'Wird gespeichert…',
        'saved'     => 'Gespeichert',
        'net_error' => 'Nicht gespeichert: Netzwerkfehler',
        'bad_response' => 'Nicht gespeichert: unerwartete Antwort ({0})',
    ],
    'field' => [
        'none'       => '(keine)',
        'not_set'    => 'nicht gesetzt',
        'secret_set' => 'gesetzt — leer lassen, um es zu behalten',
    ],
];
