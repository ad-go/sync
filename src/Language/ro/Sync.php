<?php

declare(strict_types=1);

/**
 * Romanian texts of the sync package. Same key structure as Language/en/Sync.php.
 * Placeholders {0}, {1}, ... are filled with the arguments of lang() / AdGo\Sync\Libraries\Lang::line().
 */
return [
    // Admin page switcher (Views/ui/_nav.php)
    'nav' => [
        'dashboard' => 'Panou',
        'conflicts' => 'Conflicte',
        'status'    => 'Stare',
        'settings'  => 'Setări',
        'users'     => 'Utilizatori',
        'main_site' => 'Site principal',
    ],

    // Settings page tab labels (Views/settings/index.php)
    'settings' => [
        'tab' => [
            'branding'        => 'Branding',
            'node'            => 'Nod',
            'synchronisation' => 'Sincronizare',
            'database'        => 'Bază de date',
            'alerts'          => 'Alerte',
            'users'           => 'Utilizatori',
            'conflicts'       => 'Conflicte',
            'application'     => 'Aplicație',
        ],
    ],

    // Dashboard admin page (Views/dashboard/index.php)
    'dashboard' => [
        'title'            => 'Panou',
        'stat_changes'     => 'Modificări totale',
        'stat_pending'     => 'În așteptare (netrimise)',
        'stat_applied'     => 'Aplicate de la colegi',
        'stat_conflicts'   => 'Conflicte',
        'stat_files'       => 'Fișiere sincronizate',
        'stat_size'        => 'Dimensiune fișiere',
        'peers'            => 'Colegi',
        'recent'           => 'Modificări recente',
        'no_changes'       => 'Nu există încă modificări înregistrate.',
        'col_peer'         => 'Coleg',
        'col_last_seq'     => 'Ultima secv.',
        'col_clock_offset' => 'Decalaj ceas',
        'col_clock_at'     => 'Măsurat la',
        'col_table'        => 'Tabel',
        'col_op'           => 'Operație',
        'col_uuid'         => 'UUID',
        'col_origin'       => 'Origine',
        'col_seen'         => 'Văzut',
        'col_changed_at'   => 'Modificat la',
    ],

    // Conflict admin UI (Views/conflicts/index.php and show.php)
    'conflicts' => [
        'title'           => 'Conflicte de sincronizare',
        'node'            => 'nodul {0}',
        'editingActiveOn' => 'Editarea utilizatorilor este activă pe {0}.',
        'pending'         => 'În așteptare ({0})',
        'nothingPending'  => 'Nimic în așteptare.',
        'recentDecisions' => 'Decizii recente',
        'deleteVsEdit'    => '(ștergere vs. editare)',
        'review'          => 'Revizuiește',
        'conflict'        => 'Conflict #{0}',
        'allConflicts'    => 'Toate conflictele',
        'tableRow'        => 'Tabel {0}, rândul {1}.',
        'deletedOn'       => 'Șters pe {0}.',
        'rev'             => 'rev. {0}',
        'unknown'         => 'necunoscut',
        'other'           => 'altul:',
        'keep'            => 'Păstrează {0}',
        'manualMerge'     => 'Îmbinare manuală (folosește alegerile de mai sus)',
        'ignore'          => 'Ignoră (acceptă divergența)',
        'th'              => [
            'id'          => '#',
            'table'       => 'Tabel',
            'row'         => 'Rând',
            'fields'      => 'Câmpuri',
            'detected'    => 'Detectat',
            'severity'    => 'Gravitate',
            'resolvedAs'  => 'Rezolvat ca',
            'by'          => 'De către',
            'field'       => 'Câmp',
            'lastCommon'  => 'Ultima comună',
            'mergeResult' => 'Rezultatul îmbinării',
        ],
        // Values of the sync_conflicts columns as shown in the UI
        'status' => [
            'pending'  => 'în așteptare',
            'resolved' => 'rezolvat',
            'ignored'  => 'ignorat',
        ],
        'severity' => [
            'safe'      => 'sigur',
            'dangerous' => 'periculos',
        ],
        'resolution' => [
            'ignore'       => 'ignoră',
            'merge'        => 'îmbină',
            'peerDecision' => 'decizia-colegului',
        ],
    ],

    // Flash messages of the conflict admin (Controllers/ConflictAdmin.php)
    'flash' => [
        'unknownConflict' => 'Conflict necunoscut.',
        'couldNotResolve' => 'Conflictul nu a putut fi rezolvat.',
        'resolved'        => 'Conflictul #{0} a fost rezolvat.',
        'notPropagated'   => 'Celălalt nod nu a putut fi contactat; va primi decizia la următoarea sincronizare.',
    ],

    // Telegram alerts, buttons and callback answers (Libraries/TelegramNotifier.php, Controllers/SyncController.php)
    'telegram' => [
        'alert'               => "⚠️ Conflict de sincronizare #{0} pe {1}\nTabel: {2}\nRând: {3}\nCâmpuri divergente: {4}\n\n{5} ({6}): {7}\n{8} ({9}): {10}",
        'rowDeletedOnOneNode' => '(rând șters pe un nod)',
        'deleted'             => '(șters)',
        'keep'                => 'Păstrează {0}',
        'openInAdmin'         => 'Deschide în administrare',
        'kept'                => 'Păstrat {0}.',
        'failed'              => 'Eșec: {0}',
        'resolvedNote'        => 'Rezolvat: păstrat {0} (de {1}, {2})',
        'peerUnreachable'     => 'celălalt nod nu poate fi contactat',
        'unknownNode'         => 'nod necunoscut',
        'notAllowed'          => 'Nu ai voie să decizi conflictele.',
        'blocked'             => '🔒 Am blocat o încercare de editare de utilizatori pe {0}: {1}',
    ],

    // Auth write-lock / edit lease (Filters/AuthWriteLock.php, Libraries/AuthLock.php)
    'lock' => [
        'message'                => 'Editarea utilizatorilor este activă momentan pe celălalt nod.',
        'banner'                 => '{0} editează {1}{2} din {3} UTC — aici doar în citire până se termină.',
        'usersGroups'            => 'utilizatori/grupuri',
        'heldBy'                 => 'deținut de {0}{1}',
        'editing'                => '(editează {0})',
        'editingBy'              => '(editează {0} de {1})',
        'refusedByPeer'          => 'refuzat de coleg',
        'peerUnreachableConfirm' => 'colegul nu poate fi contactat: doar nodul principal preia blocarea automat; confirmă explicit',
        'couldNotCatchUp'        => 'nu s-a putut sincroniza cu {0}: {1}',
    ],

    // Conflict decisions (Libraries/ConflictResolver.php)
    'resolver' => [
        'unknownConflict' => 'conflict necunoscut',
        'rowChanged'      => 'rândul s-a modificat pe acest nod după detectarea conflictului; verifică-l din nou',
        'invalidChoice'   => 'alegere invalidă',
    ],
];
