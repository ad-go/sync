<?php

declare(strict_types=1);

/**
 * Romanian texts of the Users admin (Views/users/panel.php). Usage: lang('Users.title').
 * Placeholders {0}, {1}, ... are filled with the arguments of lang().
 */
return [
    'title'      => 'Utilizatori',
    'page_title' => 'Utilizatori',

    'col' => [
        'id'       => 'ID',
        'username' => 'Nume utilizator',
        'email'    => 'E-mail',
        'groups'   => 'Grupuri',
        'active'   => 'Activ',
    ],

    // Editor fields
    'field' => [
        'username'      => 'Nume utilizator',
        'email'         => 'E-mail',
        'password'      => 'Parolă',
        'password_hint' => 'Lasă gol ca să rămână neschimbată',
        'groups'        => 'Grupuri',
        'active'        => 'Activ',
        'yes'           => 'Da',
        'no'            => 'Nu',
    ],

    'btn' => [
        'new'    => 'Nou',
        'export' => 'Exportă Excel',
        'import' => 'Importă Excel',
    ],

    'delete' => [
        'title'   => 'Șterge utilizatorul',
        'message' => 'Această acțiune nu poate fi anulată.',
        'button'  => 'Șterge',
        'tooltip' => 'Șterge',
    ],

    'twofa' => [
        'tooltip' => 'Vezi 2FA',
        'heading' => 'Autentificator (2FA)',
        'loading' => 'Se încarcă…',
        'error'   => 'Nu s-a putut încărca.',
        'key'     => 'Cheie manuală:',
        'codes'   => 'Coduri de recuperare (tăiate = folosite):',
        'close'   => 'Închide',
    ],

    'import' => [
        'title'      => 'Importă utilizatori (Excel)',
        'format'     => 'Câte un utilizator pe rând, în această ordine: <code>username, email, password, groups, active</code>. Grupurile se separă cu <code>|</code> (de exemplu <code>user|beta</code>); active este <code>1</code> sau <code>0</code>. Un prim rând cu numele coloanelor este ignorat.',
        'model'      => 'Descarcă modelul Excel',
        'drop'       => 'Alege un fișier Excel (.xlsx) sau trage-l aici',
        'drop_hint'  => '.xlsx · câte un utilizator pe rând',
        'cancel'     => 'Renunță',
        'run'        => 'Importă',
        'done'       => 'Gata.',
        'failed'     => 'Importul a eșuat.',
        'close'      => 'Închide',
    ],

    // DataTables and DataTables Editor built-in texts
    'dt' => [
        'search'        => 'Caută:',
        'info'          => 'Se afișează _START_ până la _END_ din _TOTAL_ utilizatori',
        'info_empty'    => 'Se afișează 0 până la 0 din 0 utilizatori',
        'previous'      => 'Înapoi',
        'next'          => 'Înainte',
        'empty'         => 'Încă nu există utilizatori',
        'zero'          => 'Nu s-au găsit utilizatori potriviți',
        'loading'       => 'Se încarcă…',
        'system_error'  => 'A apărut o eroare de sistem. Încearcă din nou.',
        'create_title'  => 'Utilizator nou',
        'create_submit' => 'Creează',
        'edit_title'    => 'Editează utilizatorul',
        'edit_submit'   => 'Salvează',
        'remove_title'  => 'Șterge utilizatorul',
        'remove_submit' => 'Șterge',
        'remove_confirm'     => 'Ștergi %d utilizatori?',
        'remove_confirm_one' => 'Ștergi acest utilizator?',
    ],
];
