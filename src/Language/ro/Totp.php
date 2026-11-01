<?php

declare(strict_types=1);

/**
 * Romanian texts of the two-factor (TOTP) pages shown at login for superadmins.
 * Usage: lang('Totp.setup.title').
 */
return [
    'setup' => [
        'title'        => 'Configurează autentificatorul',
        'heading'      => 'Configurează aplicația de autentificare',
        'intro'        => 'Contul tău face parte din grupul <b>superadmin</b>, care cere un al doilea factor. Scanează acest cod cu Google Authenticator, Authy sau altă aplicație TOTP, apoi introdu codul de 6 cifre pe care îl afișează.',
        'cant_scan'    => 'Nu poți scana? Introdu manual această cheie:',
        'placeholder'  => '000000 sau cod de recuperare',
        'confirm'      => 'Confirmă',
        'codes_title'  => 'Coduri de recuperare',
        'codes_text'   => 'Păstrează-le într-un loc sigur — fiecare funcționează o singură dată, dacă pierzi accesul la aplicația de autentificare. Le poți vedea din nou mai târziu, pe pagina Utilizatori („{0}”).',
    ],

    'verify' => [
        'title'       => 'Introdu codul de autentificare',
        'intro'       => 'Deschide aplicația de autentificare și introdu codul de 6 cifre sau unul dintre codurile de recuperare.',
        'placeholder' => '000000 sau cod de recuperare',
        'confirm'     => 'Confirmă',
    ],
];
