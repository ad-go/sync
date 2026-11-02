<?php

declare(strict_types=1);

/**
 * Romanian texts of the reachability report (Libraries/Reachability.php): the notes of the status page.
 * Placeholders {0}, {1}, ... are filled by Lang::text() with the arguments of each message.
 */
return [
    'relayVia'          => '{0} nu este accesibil direct, dar releul {1} îl ajunge ({2} ms de la {3}); modificările trec în continuare prin {4}',
    'neverContact'      => 'nu a avut niciodată contact cu releul',
    'silentFor'         => 'tăcut de {0} s',
    'ntpNone'           => 'nicio măsurătoare NTP recentă',
    'ntpCorrectOff'     => ': corecția este oprită până când `sync:ntp` reușește',
    'ntpHint'           => ' (rulează `php spark sync:ntp` sau verifică dacă portul UDP 123 este deschis)',
    'clockOff'          => 'acest ceas are o abatere de {0} s față de timpul real{1}',
    'clockCorrected'    => ' (marcajele lui sunt corectate, dar ceasul trebuie reparat oricum)',
    'fixSync'           => '; repară sincronizarea orei',
    'clockAgrees'       => 'ceasul este de acord cu NTP',
    'noContact'         => 'niciun contact cu {0} în ultimele {1} minute',
    'noUrl'             => 'nu este configurat un URL pentru peer-ul {0}',
    'cannotReach'       => 'nu se poate ajunge la {0} la {1}: {2}',
    'answeredAs'        => "nodul de la {0} s-a prezentat ca '{1}', dar era așteptat '{2}' (URL greșit sau nume de nod greșit)",
    'clockReplay'       => 'ceasul diferă cu {0} s, mai mult decât fereastra de replay ({1} s): peer-ii se vor respinge reciproc',
    'clockMax'          => 'ceasul diferă cu {0} s (clockMax {1} s): „cel mai nou câștigă” este suspendat pentru acest peer, conflictele sunt escaladate; repară sincronizarea orei',
    'clockWarn'         => 'ceasul diferă cu {0} s (avertisment de la {1} s): „cel mai nou câștigă” poate alege modificarea mai veche; repară sincronizarea orei',
    'slowLink'          => 'conexiune lentă: {0} ms dus-întors',
    'plainHttp'         => 'peer-ul este pe internet prin http simplu: cererile sunt semnate, dar nu criptate',
    'peerCannotReach'   => '{0} nu poate ajunge la acest nod: {1}. Fie deschide portul / corectează URL-ul pe care îl are pentru acest nod, fie declară nodul pasiv (sync.passive = true și marchează-l pasiv pe peer)',
    'observedOutbound'  => 'observat: doar ieșire (punch / NAT), deși nodul este declarat cu conexiune de intrare',
    'natEgress'         => 'ieșirea trece prin NAT; peer-ul vede acest nod ca {0} ({1})',
    'selfUrl'           => 'sync.selfUrl nu este setat: modul se stabilește după adresa pe care o vede peer-ul',
    'isRelay'           => '{0} este releu: transmite mai departe ce aplică celorlalți peer-i',
    'selfProbe'         => 'un nod nu poate fi testat prin el însuși',
    'noPeer'            => "{0} nu are peer-ul '{1}'",
    'passive'           => '{0} este configurat pasiv pe {1}: poate doar să se conecteze',
    'noUrlFor'          => '{0} nu are URL pentru {1}',
    'answeredNot'       => "{0} s-a prezentat ca '{1}', nu ca '{2}'",
    'relayError'        => 'releul {0}: {1}',
    'mode' => [
        'internet' => 'internet',
        'lan'      => 'LAN',
        'punch'    => 'punch',
        'relay'    => 'releu',
        'offline'  => 'offline',
        'unknown'  => 'necunoscut',
    ],
];
