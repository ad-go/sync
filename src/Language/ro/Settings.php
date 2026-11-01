<?php

declare(strict_types=1);

/**
 * Romanian texts of the settings page (Views/settings/index.php, _fields.php and its scripts).
 * Placeholders {0}, {1}, ... are filled with the arguments of lang().
 */
return [
    'title' => 'Setări',
    'card' => [
        'email'    => 'E-mail',
        'telegram' => 'Telegram',
    ],
    'brand' => [
        'logo_title'   => 'Click sau trage o imagine aici',
        'logo_empty'   => 'Logo: click sau trage o imagine',
        'logo_replace' => 'Logo: click sau trage pentru a înlocui',
        'logo_formats' => 'PNG · JPG · SVG · WebP, maximum {0} MiB',
        'site_name'    => 'Numele site-ului',
        'accent'       => 'Culoarea de accent',
        'pick_colour'  => 'Alege o culoare',
        'font'         => 'Font',
        'font_default' => '(implicit)',
        'size'         => 'Mărime (px)',
        'size_aria'    => 'Mărimea fontului (px)',
        'login_field'  => 'Câmpul de autentificare',
        'login_email'  => 'E-mail',
        'login_username' => 'Nume de utilizator',
        'login_both'   => 'E-mail sau nume de utilizator',
        'autosave'     => 'Modificările se salvează automat',
        'remove_logo'  => 'Șterge logo-ul',
    ],
    'node' => [
        'fingerprint'        => 'Amprenta secretului',
        'fp_hint'            => 'Redenumirea unui nod sau rotirea secretului schimbă doar {0} — aplică manual valoarea corespunzătoare pe celălalt nod.',
        'fp_preview'         => 'Previzualizare: amprenta pe care o primește nodul după Salvare (acum: {0})',
        'gen_fp_title'       => 'Generează un secret nou în câmpul de mai jos (salvează pentru a aplica)',
        'generate'           => 'Generează un secret nou',
        'copy_fp_title'      => 'Copiază amprenta',
        'copy'               => 'Copiază',
        'this_node'          => 'Acest nod',
        'peer_node'          => 'Nodul celălalt',
        'new_secret'         => 'Secret partajat nou',
        'secret_placeholder' => 'lasă gol pentru a-l păstra pe cel actual',
        'gen_secret_title'   => 'Generează un secret aleatoriu (salvează pentru a aplica)',
        'gen_secret_aria'    => 'Generează un secret aleatoriu',
        'copy_secret_title'  => 'Copiază secretul nou',
        'secret_hint'        => 'Minimum 32 de caractere alfanumerice. Lasă gol pentru a păstra secretul actual.',
        'secret_generated'   => 'Secret nou generat: amprenta lui apare mai sus; apasă Salvare pentru a-l aplica.',
        'nothing_to_copy'    => 'Nimic de copiat încă: generează sau scrie mai întâi un secret.',
    ],
    'folder' => [
        'pick_title' => 'Alege directorul sincronizat',
        'pick_hint'  => 'Alege un director din directorul sincronizat',
        'pick_aria'  => 'Alege un director',
        'select'     => 'Selectează un director din arbore.',
        'use'        => 'Folosește acest director',
        'cancel'     => 'Anulează',
        'close'      => 'Închide',
    ],
    'btn' => [
        'save'       => 'Salvează',
        'test_email' => 'Testează e-mailul',
        'test_telegram' => 'Testează Telegram',
    ],
    'js' => [
        'saving'    => 'Se salvează…',
        'saved'     => 'Salvat',
        'net_error' => 'Nesalvat: eroare de rețea',
        'bad_response' => 'Nesalvat: răspuns neașteptat ({0})',
    ],
    'field' => [
        'none'       => '(niciunul)',
        'not_set'    => 'nesetat',
        'secret_set' => 'setat — lasă gol pentru a-l păstra',
    ],
];
