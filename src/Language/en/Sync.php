<?php

declare(strict_types=1);

/**
 * English texts of the sync package (the base language). Usage: lang('Sync.conflicts.title').
 * Placeholders {0}, {1}, ... are filled with the arguments of lang() / AdGo\Sync\Libraries\Lang::line().
 */
return [
    // Admin page switcher (Views/ui/_nav.php)
    'nav' => [
        'dashboard' => 'Dashboard',
        'conflicts' => 'Conflicts',
        'status'    => 'Status',
        'settings'  => 'Settings',
        'users'     => 'Users',
        'main_site' => 'Main site',
    ],

    // Settings page tab labels (Views/settings/index.php)
    'settings' => [
        'tab' => [
            'branding'        => 'Branding',
            'node'            => 'Node',
            'synchronisation' => 'Synchronisation',
            'database'        => 'Database',
            'alerts'          => 'Alerts',
            'users'           => 'Users',
            'conflicts'       => 'Conflicts',
            'application'     => 'Application',
        ],
    ],

    // Dashboard admin page (Views/dashboard/index.php)
    'dashboard' => [
        'title'            => 'Dashboard',
        'stat_changes'     => 'Total changes',
        'stat_pending'     => 'Pending (unsent)',
        'stat_applied'     => 'Applied from peers',
        'stat_conflicts'   => 'Conflicts',
        'stat_files'       => 'Synced files',
        'stat_size'        => 'Files size',
        'peers'            => 'Peers',
        'recent'           => 'Recent changes',
        'no_changes'       => 'No changes recorded yet.',
        'col_peer'         => 'Peer',
        'col_last_seq'     => 'Last seq',
        'col_clock_offset' => 'Clock offset',
        'col_clock_at'     => 'Measured at',
        'col_table'        => 'Table',
        'col_op'           => 'Op',
        'col_uuid'         => 'UUID',
        'col_origin'       => 'Origin',
        'col_seen'         => 'Seen',
        'col_changed_at'   => 'Changed at',
    ],

    // Conflict admin UI (Views/conflicts/index.php and show.php)
    'conflicts' => [
        'title'           => 'Sync conflicts',
        'node'            => 'node {0}',
        'editingActiveOn' => 'User editing is active on {0}.',
        'pending'         => 'Pending ({0})',
        'nothingPending'  => 'Nothing pending.',
        'recentDecisions' => 'Recent decisions',
        'deleteVsEdit'    => '(delete vs edit)',
        'review'          => 'Review',
        'conflict'        => 'Conflict #{0}',
        'allConflicts'    => 'All conflicts',
        'tableRow'        => 'Table {0}, row {1}.',
        'deletedOn'       => 'Deleted on {0}.',
        'rev'             => 'rev {0}',
        'unknown'         => 'unknown',
        'other'           => 'other:',
        'keep'            => 'Keep {0}',
        'manualMerge'     => 'Manual merge (use the choices above)',
        'ignore'          => 'Ignore (accept divergence)',
        'th'              => [
            'id'          => '#',
            'table'       => 'Table',
            'row'         => 'Row',
            'fields'      => 'Fields',
            'detected'    => 'Detected',
            'severity'    => 'Severity',
            'resolvedAs'  => 'Resolved as',
            'by'          => 'By',
            'field'       => 'Field',
            'lastCommon'  => 'Last common',
            'mergeResult' => 'Merge result',
        ],
        // Values of the sync_conflicts columns as shown in the UI
        'status' => [
            'pending'  => 'pending',
            'resolved' => 'resolved',
            'ignored'  => 'ignored',
        ],
        'severity' => [
            'safe'      => 'safe',
            'dangerous' => 'dangerous',
        ],
        'resolution' => [
            'ignore'       => 'ignore',
            'merge'        => 'merge',
            'peerDecision' => 'peer-decision',
        ],
    ],

    // Flash messages of the conflict admin (Controllers/ConflictAdmin.php)
    'flash' => [
        'unknownConflict' => 'Unknown conflict.',
        'couldNotResolve' => 'Could not resolve.',
        'resolved'        => 'Conflict #{0} resolved.',
        'notPropagated'   => 'The other node could not be reached; it will receive the decision on its next pull.',
    ],

    // Telegram alerts, buttons and callback answers (Libraries/TelegramNotifier.php, Controllers/SyncController.php)
    'telegram' => [
        'alert'               => "⚠️ Sync conflict #{0} on {1}\nTable: {2}\nRow: {3}\nDivergent fields: {4}\n\n{5} ({6}): {7}\n{8} ({9}): {10}",
        'rowDeletedOnOneNode' => '(row deleted on one node)',
        'deleted'             => '(deleted)',
        'keep'                => 'Keep {0}',
        'openInAdmin'         => 'Open in admin',
        'kept'                => 'Kept {0}.',
        'failed'              => 'Failed: {0}',
        'resolvedNote'        => 'Resolved: kept {0} (by {1}, {2})',
        'peerUnreachable'     => 'the other node is not reachable',
        'unknownNode'         => 'unknown node',
        'notAllowed'          => 'You are not allowed to decide conflicts.',
        'blocked'             => '🔒 Blocked a user-editing attempt on {0}: {1}',
    ],

    // Auth write-lock / edit lease (Filters/AuthWriteLock.php, Libraries/AuthLock.php)
    'lock' => [
        'message'               => 'User editing is currently active on the other node.',
        'banner'                => '{0} is editing {1}{2} since {3} UTC — read-only here until it is done.',
        'usersGroups'           => 'users/groups',
        'heldBy'                => 'held by {0}{1}',
        'editing'               => '(editing {0})',
        'editingBy'             => '(editing {0} by {1})',
        'refusedByPeer'         => 'refused by peer',
        'peerUnreachableConfirm' => 'peer unreachable: only the primary node takes the lock automatically; confirm explicitly',
        'couldNotCatchUp'       => 'could not catch up with {0}: {1}',
    ],

    // Conflict decisions (Libraries/ConflictResolver.php)
    'resolver' => [
        'unknownConflict' => 'unknown conflict',
        'rowChanged'      => 'the row changed on this node after the conflict was detected; re-check it',
        'invalidChoice'   => 'invalid choice',
    ],
];
