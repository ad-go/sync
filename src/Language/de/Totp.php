<?php

declare(strict_types=1);

/**
 * German texts of the two-factor (TOTP) pages shown at login for superadmins.
 * Usage: lang('Totp.setup.title').
 */
return [
    'setup' => [
        'title'        => 'Authenticator einrichten',
        'heading'      => 'Authenticator-App einrichten',
        'intro'        => 'Ihr Konto gehört zur Gruppe <b>superadmin</b>, die einen zweiten Faktor verlangt. Scannen Sie diesen Code mit Google Authenticator, Authy oder einer anderen TOTP-App und geben Sie dann den 6-stelligen Code ein, den sie anzeigt.',
        'cant_scan'    => 'Scannen funktioniert nicht? Geben Sie diesen Schlüssel manuell ein:',
        'placeholder'  => '000000 oder Wiederherstellungscode',
        'confirm'      => 'Bestätigen',
        'codes_title'  => 'Wiederherstellungscodes',
        'codes_text'   => 'Bewahren Sie diese an einem sicheren Ort auf. Jeder funktioniert nur einmal, falls Sie keinen Zugriff mehr auf Ihre Authenticator-App haben. Sie können sie später auf der Benutzerseite erneut ansehen („{0}“).',
    ],

    'verify' => [
        'title'       => 'Authenticator-Code eingeben',
        'intro'       => 'Öffnen Sie Ihre Authenticator-App und geben Sie den 6-stelligen Code ein oder einen Ihrer Wiederherstellungscodes.',
        'placeholder' => '000000 oder Wiederherstellungscode',
        'confirm'     => 'Bestätigen',
    ],
];
