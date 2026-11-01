<?php

declare(strict_types=1);

/**
 * German texts of the e-mail sent when an account is added to the superadmin group.
 * Usage: lang('Mail.greeting', [$username]). Placeholders {0}, {1}, ... are filled by lang().
 */
return [
    'greeting'    => 'Hallo {0},',
    'added'       => 'Ihr Konto ({0}) wurde am {1} der Gruppe <b>superadmin</b> hinzugefügt, die beim Anmelden einen zweiten Faktor verlangt.',
    'scan'        => 'Scannen Sie diesen Code mit Google Authenticator, Authy oder einer anderen TOTP-App:',
    'cant_scan'   => 'Scannen funktioniert nicht? Geben Sie diesen Schlüssel manuell ein:',
    'codes'       => '<b>Wiederherstellungscodes</b> — bewahren Sie diese an einem sicheren Ort auf. Jeder funktioniert nur einmal, falls Sie keinen Zugriff mehr auf Ihre Authenticator-App haben:',
    'admin_note'  => 'Ein Administrator kann diesen QR-Code und diese Wiederherstellungscodes später auf der Benutzerseite erneut ansehen („{0}“).',
];
