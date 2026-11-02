<?php

declare(strict_types=1);

/**
 * English texts of the reachability report (Libraries/Reachability.php): the notes of the status page.
 * Placeholders {0}, {1}, ... are filled by Lang::text() with the arguments of each message.
 */
return [
    'relayVia'          => '{0} is not reachable directly, but relay {1} reaches it ({2} ms from {3}); changes still travel through {4}',
    'neverContact'      => 'never made contact with the relay',
    'silentFor'         => 'silent for {0} s',
    'ntpNone'           => 'no recent NTP measurement',
    'ntpCorrectOff'     => ': the correction is off until `sync:ntp` succeeds',
    'ntpHint'           => ' (run `php spark sync:ntp`, or check that UDP port 123 is open)',
    'clockOff'          => 'this clock is {0} s off the real time{1}',
    'clockCorrected'    => ' (its stamps are corrected, fix the clock anyway)',
    'fixSync'           => '; fix the time synchronisation',
    'clockAgrees'       => 'clock agrees with NTP',
    'noContact'         => 'no contact from {0} in the last {1} minutes',
    'noUrl'             => 'no URL configured for peer {0}',
    'cannotReach'       => 'cannot reach {0} at {1}: {2}',
    'answeredAs'        => "the node at {0} answered as '{1}', expected '{2}' (wrong URL or wrong node name)",
    'clockReplay'       => 'clock differs by {0} s, more than the replay window ({1} s): the peers will reject each other',
    'clockMax'          => 'clock differs by {0} s (clockMax {1} s): "newest wins" is suspended for this peer, conflicts are escalated; fix the time synchronisation',
    'clockWarn'         => 'clock differs by {0} s (warning at {1} s): "newest wins" may pick the older edit; fix the time synchronisation',
    'slowLink'          => 'slow link: {0} ms round trip',
    'plainHttp'         => 'the peer is on the internet over plain http: requests are signed but not encrypted',
    'peerCannotReach'   => '{0} cannot reach this node: {1}. Either open the port / fix the URL it has for this node, or declare the node passive (sync.passive = true, and mark it passive on the peer)',
    'observedOutbound'  => 'observed: outbound only (punch / NAT) although the node is declared dial-in',
    'natEgress'         => 'outbound works through NAT; the peer sees this node as {0} ({1})',
    'selfUrl'           => 'sync.selfUrl is not set: the mode is classified from the address the peer sees',
    'isRelay'           => '{0} is a relay: it forwards what it applies to its other peers',
    'selfProbe'         => 'a node cannot be probed through itself',
    'noPeer'            => "{0} has no peer '{1}'",
    'passive'           => '{0} is configured as passive on {1}: it can only dial in',
    'noUrlFor'          => '{0} has no URL for {1}',
    'answeredNot'       => "{0} answered as '{1}', not '{2}'",
    'relayError'        => 'relay {0}: {1}',
    'mode' => [
        'internet' => 'internet',
        'lan'      => 'LAN',
        'punch'    => 'punch',
        'relay'    => 'relay',
        'offline'  => 'offline',
        'unknown'  => 'unknown',
    ],
];
