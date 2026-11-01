<?php

declare(strict_types=1);

/**
 * Romanian texts of the e-mail sent when an account is added to the superadmin group.
 * Usage: lang('Mail.greeting', [$username]). Placeholders {0}, {1}, ... are filled by lang().
 */
return [
    'greeting'    => 'Salut {0},',
    'added'       => 'Contul tău ({0}) a fost adăugat în grupul <b>superadmin</b> pe {1}, care cere un al doilea factor la autentificare.',
    'scan'        => 'Scanează acest cod cu Google Authenticator, Authy sau altă aplicație TOTP:',
    'cant_scan'   => 'Nu poți scana? Introdu manual această cheie:',
    'codes'       => '<b>Coduri de recuperare</b> — păstrează-le într-un loc sigur. Fiecare funcționează o singură dată, dacă pierzi accesul la aplicația de autentificare:',
    'admin_note'  => 'Un administrator poate vedea din nou acest cod QR și aceste coduri de recuperare, mai târziu, pe pagina Utilizatori („{0}”).',
];
