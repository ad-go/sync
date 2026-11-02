<?php

declare(strict_types=1);

/**
 * German texts of the reachability report (Libraries/Reachability.php): the notes of the status page.
 * Placeholders {0}, {1}, ... are filled by Lang::text() with the arguments of each message.
 */
return [
    'relayVia'          => '{0} ist nicht direkt erreichbar, aber Relay {1} erreicht es ({2} ms von {3}); Änderungen laufen weiterhin über {4}',
    'neverContact'      => 'noch nie Kontakt zum Relay gehabt',
    'silentFor'         => 'seit {0} s still',
    'ntpNone'           => 'keine aktuelle NTP-Messung',
    'ntpCorrectOff'     => ': die Korrektur ist aus, bis `sync:ntp` erfolgreich ist',
    'ntpHint'           => ' (`php spark sync:ntp` ausführen oder prüfen, ob UDP-Port 123 offen ist)',
    'clockOff'          => 'diese Uhr geht {0} s gegenüber der realen Zeit falsch{1}',
    'clockCorrected'    => ' (ihre Zeitstempel sind korrigiert, die Uhr trotzdem beheben)',
    'fixSync'           => '; die Zeitsynchronisation beheben',
    'clockAgrees'       => 'Uhr stimmt mit NTP überein',
    'noContact'         => 'kein Kontakt zu {0} in den letzten {1} Minuten',
    'noUrl'             => 'für Peer {0} ist keine URL konfiguriert',
    'cannotReach'       => '{0} ist unter {1} nicht erreichbar: {2}',
    'answeredAs'        => "der Knoten unter {0} hat sich als '{1}' gemeldet, erwartet wurde '{2}' (falsche URL oder falscher Knotenname)",
    'clockReplay'       => 'die Uhr weicht um {0} s ab, mehr als das Replay-Fenster ({1} s): die Peers werden einander ablehnen',
    'clockMax'          => 'die Uhr weicht um {0} s ab (clockMax {1} s): "neuester gewinnt" ist für diesen Peer ausgesetzt, Konflikte werden eskaliert; die Zeitsynchronisation beheben',
    'clockWarn'         => 'die Uhr weicht um {0} s ab (Warnung ab {1} s): "neuester gewinnt" kann die ältere Änderung wählen; die Zeitsynchronisation beheben',
    'slowLink'          => 'langsame Verbindung: {0} ms Rundreisezeit',
    'plainHttp'         => 'der Peer ist über unverschlüsseltes http im Internet: Anfragen sind signiert, aber nicht verschlüsselt',
    'peerCannotReach'   => '{0} kann diesen Knoten nicht erreichen: {1}. Entweder den Port öffnen / die URL korrigieren, die er für diesen Knoten hat, oder den Knoten als passiv deklarieren (sync.passive = true und auf dem Peer als passiv markieren)',
    'observedOutbound'  => 'beobachtet: nur ausgehend (Punch / NAT), obwohl der Knoten als eingehend deklariert ist',
    'natEgress'         => 'ausgehend über NAT; der Peer sieht diesen Knoten als {0} ({1})',
    'selfUrl'           => 'sync.selfUrl ist nicht gesetzt: der Modus wird anhand der Adresse eingestuft, die der Peer sieht',
    'isRelay'           => '{0} ist ein Relay: es leitet weiter, was es anwendet, an seine anderen Peers',
    'selfProbe'         => 'ein Knoten kann nicht über sich selbst geprüft werden',
    'noPeer'            => "{0} hat keinen Peer '{1}'",
    'passive'           => '{0} ist auf {1} als passiv konfiguriert: er kann nur eingehend verbinden',
    'noUrlFor'          => '{0} hat keine URL für {1}',
    'answeredNot'       => "{0} hat sich als '{1}' gemeldet, nicht als '{2}'",
    'relayError'        => 'Relay {0}: {1}',
    'mode' => [
        'internet' => 'Internet',
        'lan'      => 'LAN',
        'punch'    => 'Punch',
        'relay'    => 'Relay',
        'offline'  => 'offline',
        'unknown'  => 'unbekannt',
    ],
];
