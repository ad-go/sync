<?php

declare(strict_types=1);

/**
 * Romanian texts of the messages the controllers and libraries return to the browser (flash, JSON replies).
 * Placeholders {0}, {1}, ... are filled with the arguments of lang('Messages.<key>', [args]).
 */
return [
    'settings' => [
        'dteKeyInvalid' => 'Cheia DTE are un format neașteptat.',
        'localesNone' => 'Bifează cel puțin o limbă.',
        'brandingSaved'         => 'Brandingul a fost salvat.',
        'logoRemoved'           => 'Logo-ul a fost șters.',
        'settingsSaved'         => 'Setările au fost salvate.',
        'nothingToChange'       => 'Nimic de schimbat.',
        'identitySaved'         => 'Identitatea a fost salvată. Aplică și modificarea corespunzătoare pe nodul partener.',
        'identityInvalid'       => 'Valoare invalidă pentru {0}: doar litere mici, cifre, - și _.',
        'secretInvalid'         => 'Secret invalid: 32–128 de litere sau cifre.',
        'siteNameLength'        => 'Numele site-ului trebuie să aibă între 1 și 80 de caractere.',
        'accentColor'           => 'Culoarea de accent trebuie să fie o valoare #rrggbb.',
        'logoTooBig'            => 'Logo-ul trebuie să aibă cel mult {0} MiB.',
        'logoType'              => 'Logo-ul trebuie să fie png, jpg, svg sau webp.',
        'folderOutside'         => 'Nu este un director în directorul sincronizat.',
        'saved'                 => 'Salvat',
        'mailNoTarget'          => 'Salvează mai întâi e-mailul de alertă și serverul SMTP.',
        'mailNotSent'           => 'E-mailul nu a fost trimis: {0}',
        'mailSent'              => 'E-mailul de test a fost trimis către {0}.',
        'telegramNotConfigured' => 'Telegram nu este configurat — setează mai întâi tokenul botului și ID-ul chatului.',
        'telegramSent'          => 'Mesajul de test a fost trimis.',
        'telegramFailed'        => 'Trimiterea a eșuat — verifică tokenul și ID-ul chatului în jurnalul serverului.',
    ],
    'users' => [
        'created'        => 'Utilizator creat.',
        'deleted'        => 'Utilizator șters.',
        'unknown'        => 'Utilizator necunoscut.',
        'unknownAction'  => 'Acțiune necunoscută.',
        'noFile'         => 'Niciun fișier încărcat.',
        'xlsxOnly'       => 'Pot fi importate doar fișiere Excel (.xlsx).',
        'notSuperadmin'  => 'Nu este superadministrator.',
        'notSetUp'       => 'Nu este configurat încă — codul QR apare la prima autentificare a utilizatorului.',
        'usernameLength' => 'Numele de utilizator trebuie să aibă între 1 și 30 de caractere.',
        'invalidEmail'   => 'E-mail invalid.',
        'passwordNeeded' => 'Parola este obligatorie.',
        'noGroup'        => 'Selectează cel puțin un grup.',
        'usernameTaken'  => 'Numele de utilizator este deja folosit.',
        'emailTaken'     => 'Acest e-mail este deja folosit.',
        'ownAccount'     => 'Nu îți poți șterge propriul cont.',
        'lastSuperadmin' => 'Nu se poate șterge ultimul superadministrator.',
        'importRow'      => 'Rândul {0}: {1}',
        'imported'       => 'Au fost importați {0} utilizator(i).',
        'importErrors'   => 'Erori: {0}',
        'totpSubject'    => '{0}: configurează aplicația de autentificare',
    ],
    'login' => [
        'invalid2fa' => 'Cod 2FA invalid.',
    ],
    'lock' => [
        'peerUnreachable' => 'colegul nu poate fi contactat ({0}); blocarea rămâne până expiră',
        'notApplied'      => 'colegul nu a aplicat încă modificările noastre; blocarea rămâne până expiră',
    ],
];
