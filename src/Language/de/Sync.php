<?php

declare(strict_types=1);

/**
 * Deutsche Texte des Sync-Pakets. Gleiche Schlüsselstruktur wie Language/en/Sync.php.
 */
return [
    // Konflikt-Verwaltung (Views/conflicts/index.php und show.php)
    'conflicts' => [
        'title'           => 'Sync-Konflikte',
        'node'            => 'Knoten {0}',
        'editingActiveOn' => 'Benutzerbearbeitung ist aktiv auf {0}.',
        'pending'         => 'Ausstehend ({0})',
        'nothingPending'  => 'Nichts ausstehend.',
        'recentDecisions' => 'Letzte Entscheidungen',
        'deleteVsEdit'    => '(Löschung vs. Bearbeitung)',
        'review'          => 'Prüfen',
        'conflict'        => 'Konflikt #{0}',
        'allConflicts'    => 'Alle Konflikte',
        'tableRow'        => 'Tabelle {0}, Zeile {1}.',
        'deletedOn'       => 'Auf {0} gelöscht.',
        'rev'             => 'Rev. {0}',
        'unknown'         => 'unbekannt',
        'other'           => 'anderer Wert:',
        'keep'            => '{0} behalten',
        'manualMerge'     => 'Manuell zusammenführen (Auswahl oben verwenden)',
        'ignore'          => 'Ignorieren (Abweichung akzeptieren)',
        'th'              => [
            'id'          => '#',
            'table'       => 'Tabelle',
            'row'         => 'Zeile',
            'fields'      => 'Felder',
            'detected'    => 'Erkannt',
            'severity'    => 'Schwere',
            'resolvedAs'  => 'Entschieden als',
            'by'          => 'Von',
            'field'       => 'Feld',
            'lastCommon'  => 'Letzter gemeinsamer Stand',
            'mergeResult' => 'Ergebnis der Zusammenführung',
        ],
        // Werte der sync_conflicts-Spalten, wie sie in der Oberfläche angezeigt werden
        'status' => [
            'pending'  => 'ausstehend',
            'resolved' => 'entschieden',
            'ignored'  => 'ignoriert',
        ],
        'severity' => [
            'safe'      => 'unkritisch',
            'dangerous' => 'kritisch',
        ],
        'resolution' => [
            'ignore'       => 'ignoriert',
            'merge'        => 'zusammengeführt',
            'peerDecision' => 'Entscheidung des anderen Knotens',
        ],
    ],

    // Flash-Meldungen der Konflikt-Verwaltung (Controllers/ConflictAdmin.php)
    'flash' => [
        'unknownConflict' => 'Unbekannter Konflikt.',
        'couldNotResolve' => 'Der Konflikt konnte nicht entschieden werden.',
        'resolved'        => 'Konflikt #{0} entschieden.',
        'notPropagated'   => 'Der andere Knoten war nicht erreichbar; er erhält die Entscheidung bei seinem nächsten Abruf.',
    ],

    // Telegram-Meldungen, Schaltflächen und Rückmeldungen (Libraries/TelegramNotifier.php, Controllers/SyncController.php)
    'telegram' => [
        'alert'               => "⚠️ Sync-Konflikt #{0} auf {1}\nTabelle: {2}\nZeile: {3}\nAbweichende Felder: {4}\n\n{5} ({6}): {7}\n{8} ({9}): {10}",
        'rowDeletedOnOneNode' => '(Zeile auf einem Knoten gelöscht)',
        'deleted'             => '(gelöscht)',
        'keep'                => '{0} behalten',
        'openInAdmin'         => 'In der Verwaltung öffnen',
        'kept'                => '{0} behalten.',
        'failed'              => 'Fehlgeschlagen: {0}',
        'resolvedNote'        => 'Entschieden: {0} behalten (von {1}, {2})',
        'peerUnreachable'     => 'der andere Knoten ist nicht erreichbar',
        'notAllowed'          => 'Du darfst keine Konflikte entscheiden.',
        'unknownNode'         => 'unbekannter Knoten',
        'blocked'             => '🔒 Benutzerbearbeitung auf {0} blockiert: {1}',
    ],

    // Schreibsperre für Benutzerbearbeitung (Filters/AuthWriteLock.php, Libraries/AuthLock.php)
    'lock' => [
        'message'               => 'Die Benutzerbearbeitung ist derzeit auf dem anderen Knoten aktiv.',
        'banner'                => '{0} bearbeitet {1}{2} seit {3} UTC — hier nur lesend, bis die Bearbeitung abgeschlossen ist.',
        'usersGroups'           => 'Benutzer/Gruppen',
        'heldBy'                => 'gehalten von {0}{1}',
        'editing'               => '(bearbeitet {0})',
        'editingBy'             => '({1} bearbeitet {0})',
        'refusedByPeer'         => 'vom anderen Knoten abgelehnt',
        'peerUnreachableConfirm' => 'anderer Knoten nicht erreichbar: nur der primäre Knoten übernimmt die Sperre automatisch; bitte bestätigen Sie ausdrücklich',
        'couldNotCatchUp'       => 'Änderungen von {0} konnten nicht nachgezogen werden: {1}',
    ],

    // Konfliktentscheidungen (Libraries/ConflictResolver.php)
    'resolver' => [
        'unknownConflict' => 'unbekannter Konflikt',
        'rowChanged'      => 'die Zeile wurde auf diesem Knoten nach dem Erkennen des Konflikts geändert; bitte prüfen Sie sie erneut',
        'invalidChoice'   => 'ungültige Auswahl',
    ],
];
