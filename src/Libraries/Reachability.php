<?php

declare(strict_types=1);

namespace AdGo\Sync\Libraries;

use CodeIgniter\Database\BaseConnection;
use Throwable;

/**
 * How reachable is this node, and does that match how it is configured?
 *
 * Link modes:
 *   internet  a public address: peers can dial in directly
 *   lan       private / loopback / link-local / CGNAT address: peers can dial in only from the same network
 *   punch     outbound only (behind NAT): the node dials out, nobody can dial in. This is a passive node
 *             (`sync.passive = true`); it pushes and pulls through its hub.
 *   relay     the peer cannot be reached directly, but a relay hub that can reach it forwards what it applies
 *             (`sync.relay = true` on the hub): the path exists, one hop longer
 *
 * Per peer it measures, all through the signed API (so a pass also proves the secret, the TLS certificate and the
 * node names):
 *   outbound   this -> peer: `ping` (round trip, clock skew, the address the peer sees us from, whether the peer is a
 *              relay and which nodes it holds)
 *   inbound    peer -> this: `probe` makes the peer call the URL IT has configured for us
 *   relayed    the nodes that only a relay hub can reach (and a direct peer that is down, if a relay still reaches it):
 *              `probe` with a peer name makes the relay ping that peer, again only by the URL the relay has configured
 * No URL is ever taken from a request, so a caller cannot aim a node at an arbitrary host.
 */
class Reachability
{
    public const INTERNET = 'internet';
    public const LAN      = 'lan';
    public const PUNCH    = 'punch';
    public const RELAY    = 'relay';
    public const OFFLINE  = 'offline';
    public const UNKNOWN  = 'unknown';

    public const OK   = 'ok';
    public const WARN = 'warn';
    public const FAIL = 'fail';

    /** A round trip slower than this is worth a remark (ms). */
    private const SLOW_MS = 1500;

    /** A passive peer silent for longer than this (s) is reported. */
    private const SILENT_S = 600;

    /** @var callable(string, string, array<string, scalar|null>): array<string, mixed> */
    private $call;

    /** @var callable(string): list<string> */
    private $resolve;

    /** @var array<string, array<string, mixed>> what each peer said in `ping` (relay flag, its peers) */
    private array $info = [];

    /**
     * @param (callable(string, string, array<string, scalar|null>): array<string, mixed>)|null $call    one signed call to a peer (default: SyncClient)
     * @param (callable(string): list<string>)|null                                              $resolve DNS lookup (default: gethostbynamel)
     */
    public function __construct(
        private readonly Options $opt,
        private readonly ?BaseConnection $db = null,
        ?callable $call = null,
        ?callable $resolve = null,
    ) {
        $this->call    = $call ?? static fn (string $peer, string $endpoint, array $params): array => (new SyncClient($opt, $peer, 10))->call($endpoint, $params);
        $this->resolve = $resolve ?? static function (string $host): array {
            $ips = @gethostbynamel($host);

            return is_array($ips) ? array_values($ips) : [];
        };
    }

    /** True for loopback, private (RFC 1918), link-local, CGNAT (100.64/10), unique-local IPv6 and reserved ranges. */
    public static function isPrivateIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $n = ip2long($ip);

            return $n !== false && $n >= ip2long('100.64.0.0') && $n <= ip2long('100.127.255.255');
        }

        $bin = inet_pton($ip);

        return $bin !== false && (($bin[0] & "\xfe") === "\xfc" || ($bin[0] === "\xfe" && ($bin[1] & "\xc0") === "\x80"));
    }

    /** internet | lan | unknown for an address or a host name (every address must be public to count as internet). */
    public function classifyHost(string $host): string
    {
        $host = trim($host, '[]');

        if ($host === '') {
            return self::UNKNOWN;
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : ($this->resolve)($host);

        if ($ips === []) {
            return self::UNKNOWN;
        }

        foreach ($ips as $ip) {
            if (self::isPrivateIp($ip)) {
                return self::LAN;
            }
        }

        return self::INTERNET;
    }

    public function classifyUrl(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) ? $this->classifyHost($host) : self::UNKNOWN;
    }

    /**
     * Checks every peer (or just the named one), then the nodes behind relays.
     *
     * @return array{peers: list<array<string, mixed>>, relayed: list<array<string, mixed>>, ntp: array<string, mixed>|null, verdict: string}
     */
    public function run(?string $only = null): array
    {
        $this->info = [];
        $peers      = [];

        foreach ($this->opt->peerNames() as $name) {
            if ($only === null || $only === $name) {
                $peers[] = $this->check($name);
            }
        }

        // a direct peer that is down but that a relay still reaches
        foreach ($peers as $i => $r) {
            if ($r['outbound'] !== null && ! $r['outbound']['ok']) {
                $via = $this->relayFor($r['peer']);

                if ($via !== null) {
                    $p = $this->viaRelay($via, $r['peer']);

                    if ($p['ok']) {
                        $peers[$i]['via']       = $via;
                        $peers[$i]['observed']  = self::RELAY;
                        $peers[$i]['verdict']   = self::WARN;
                        $peers[$i]['notes'][]   = "{$r['peer']} is not reachable directly, but relay {$via} reaches it ({$p['rtt_ms']} ms from {$via}); changes still travel through {$via}";
                    }
                }
            }
        }

        // the nodes that only a relay can reach
        $relayed = [];
        $direct  = array_merge($this->opt->peerNames(), [$this->opt->node]);

        foreach ($this->info as $relayName => $inf) {
            if (empty($inf['relay'])) {
                continue;
            }

            foreach ((array) ($inf['peers'] ?? []) as $name => $p) {
                $name = (string) $name;

                if (in_array($name, $direct, true) || ($only !== null && $only !== $relayName)) {
                    continue;
                }

                if (! empty($p['passive'])) {
                    $seen      = isset($p['seen']) ? (int) $p['seen'] : null;
                    $relayed[] = [
                        'node' => $name, 'via' => $relayName, 'mode' => self::PUNCH, 'ok' => $seen !== null && $seen <= self::SILENT_S, 'rtt_ms' => 0, 'skew_s' => 0.0, 'seen' => $seen,
                        'error' => $seen === null ? 'never made contact with the relay' : ($seen > self::SILENT_S ? "silent for {$seen} s" : ''),
                    ];

                    continue;
                }

                $relayed[] = ['node' => $name, 'via' => $relayName, 'mode' => self::RELAY, 'seen' => null] + $this->viaRelay($relayName, $name);
            }
        }

        $verdict = self::OK;

        foreach ($peers as $r) {
            $verdict = $this->worst($verdict, $r['verdict']);
        }

        foreach ($relayed as $r) {
            $verdict = $this->worst($verdict, $r['ok'] ? self::OK : self::WARN);
        }

        $ntp = $this->ntpStatus();

        if ($ntp !== null && $ntp['warn']) {
            $verdict = $this->worst($verdict, self::WARN);
        }

        return ['peers' => $peers, 'relayed' => $relayed, 'ntp' => $ntp, 'verdict' => $verdict];
    }

    /**
     * This node's clock against NTP as `sync:ntp` last measured it (no network here); null when NTP is not in use.
     *
     * @return array{offset_s: float|null, age_s: int|null, correct: bool, warn: bool, note: string}|null
     */
    public function ntpStatus(): ?array
    {
        $o = $this->opt;

        if ($o->ntpServers === [] && ! $o->clockCorrect) {
            return null;
        }

        $m = (new NtpClock())->load();

        if ($m === null) {
            return ['offset_s' => null, 'age_s' => null, 'correct' => $o->clockCorrect, 'warn' => true,
                'note' => 'no recent NTP measurement' . ($o->clockCorrect ? ': the correction is off until `sync:ntp` succeeds' : '') . ' (run `php spark sync:ntp`, or check that UDP port 123 is open)'];
        }

        $limit = $o->clockWarn > 0.0 ? $o->clockWarn : $o->clockMax;
        $off   = abs($m['offset_s']) > $limit && $limit > 0.0;

        return ['offset_s' => $m['offset_s'], 'age_s' => $m['age_s'], 'correct' => $o->clockCorrect, 'warn' => $off,
            'note' => $off ? sprintf('this clock is %+.1f s off the real time%s', -$m['offset_s'], $o->clockCorrect ? ' (its stamps are corrected, fix the clock anyway)' : '; fix the time synchronisation') : 'clock agrees with NTP'];
    }

    /**
     * @return array{
     *     node: string, peer: string, peerUrl: string, peerClass: string, declared: string, observed: string,
     *     outbound: array<string, mixed>|null, inbound: array<string, mixed>|null, lastSeen: ?int,
     *     verdict: string, notes: list<string>
     * }
     */
    public function check(string $peer): array
    {
        $o        = $this->opt;
        $peerPass = $o->isPassivePeer($peer);
        $url      = (string) ($o->peers[$peer]['url'] ?? '');
        $self     = $o->selfUrl;
        $notes    = [];
        $fail     = false;
        $warn     = false;

        $declared = $o->passive ? self::PUNCH : ($self !== '' ? $this->classifyUrl($self) : self::UNKNOWN);
        $report   = [
            'node' => $o->node, 'peer' => $peer, 'peerUrl' => $url, 'peerClass' => $peerPass ? self::PUNCH : ($url === '' ? self::UNKNOWN : $this->classifyUrl($url)),
            'declared' => $declared, 'observed' => self::UNKNOWN, 'outbound' => null, 'inbound' => null, 'lastSeen' => null,
            'verdict' => self::OK, 'notes' => [],
        ];

        if ($peerPass) {
            $report['lastSeen'] = $this->lastSeen($peer);
            $notes[]            = "{$peer} is passive (behind NAT): it dials in, so it cannot be probed from here"
                . ($report['lastSeen'] === null ? '; it has not made contact yet' : '; last contact ' . $report['lastSeen'] . ' s ago');

            if ($report['lastSeen'] === null || $report['lastSeen'] > self::SILENT_S) {
                $warn    = true;
                $notes[] = "no contact from {$peer} in the last " . intdiv(self::SILENT_S, 60) . ' minutes';
            }
        } elseif ($url === '') {
            $fail    = true;
            $notes[] = "no URL configured for peer {$peer}";
        } else {
            $out                = $this->ping($peer);
            $report['outbound'] = $out;

            if (! $out['ok']) {
                $fail    = true;
                $notes[] = "cannot reach {$peer} at {$url}: " . $out['error'];
            } else {
                if ($out['node'] !== $peer) {
                    $fail    = true;
                    $notes[] = "the node at {$url} answered as '{$out['node']}', expected '{$peer}' (wrong URL or wrong node name)";
                }

                $skew = abs((float) $out['skew_s']);
                $eff  = abs((float) ($out['eff_s'] ?? $out['skew_s'])); // the replay window counts raw clocks, the rest what the stamps carry

                if ($skew > $o->replayWindow) {
                    $fail    = true;
                    $notes[] = sprintf('clock differs by %.1f s, more than the replay window (%d s): the peers will reject each other', $skew, $o->replayWindow);
                } elseif ($o->clockMax > 0.0 && $eff > $o->clockMax) {
                    $warn    = true;
                    $notes[] = sprintf('clock differs by %.1f s (clockMax %.1f s): "newest wins" is suspended for this peer, conflicts are escalated; fix the time synchronisation', $eff, $o->clockMax);
                } elseif ($eff > ($o->clockWarn > 0.0 ? min($o->clockWarn, $o->replayWindow / 2) : $o->replayWindow / 2)) {
                    $warn    = true;
                    $notes[] = sprintf('clock differs by %.1f s (warning at %.1f s): "newest wins" may pick the older edit; fix the time synchronisation', $eff, $o->clockWarn > 0.0 ? min($o->clockWarn, $o->replayWindow / 2) : $o->replayWindow / 2);
                }

                if ($out['rtt_ms'] > self::SLOW_MS) {
                    $warn    = true;
                    $notes[] = sprintf('slow link: %d ms round trip', $out['rtt_ms']);
                }

                if (str_starts_with(strtolower($url), 'http://') && $report['peerClass'] === self::INTERNET) {
                    $warn    = true;
                    $notes[] = 'the peer is on the internet over plain http: requests are signed but not encrypted';
                }
            }
        }

        $outOk = $report['outbound'] === null || $report['outbound']['ok'];

        if (! $o->passive && ! $peerPass && $url !== '' && $outOk) {
            $in                = $this->probe($peer);
            $report['inbound'] = $in;

            if (! $in['ok']) {
                $fail    = true;
                $notes[] = "{$peer} cannot reach this node: " . $in['error']
                    . '. Either open the port / fix the URL it has for this node, or declare the node passive (sync.passive = true, and mark it passive on the peer)';
            }
        }

        $egress             = $report['outbound']['egress'] ?? '';
        $report['observed'] = match (true) {
            ! $outOk                                                  => self::OFFLINE,
            $o->passive                                               => self::PUNCH,
            $report['inbound'] !== null && ! $report['inbound']['ok'] => self::PUNCH,
            $report['inbound'] !== null                               => $declared !== self::UNKNOWN ? $declared : ($egress !== '' ? $this->classifyHost($egress) : self::UNKNOWN),
            default                                                   => self::UNKNOWN,
        };

        if ($report['observed'] === self::PUNCH && ! $o->passive && $report['inbound'] !== null) {
            $notes[] = 'observed: outbound only (punch / NAT) although the node is declared dial-in';
        }

        if ($o->passive && $report['outbound'] !== null && $report['outbound']['ok'] && $egress !== '') {
            $notes[] = "outbound works through NAT; the peer sees this node as {$egress} (" . $this->classifyHost($egress) . ')';
        }

        if (! $o->passive && $self === '') {
            $notes[] = 'sync.selfUrl is not set: the mode is classified from the address the peer sees';
        }

        if (! empty($report['outbound']['relay'])) {
            $notes[] = "{$peer} is a relay: it forwards what it applies to its other peers";
        }

        $report['notes']   = $notes;
        $report['verdict'] = $fail ? self::FAIL : ($warn ? self::WARN : self::OK);

        return $report;
    }

    /** @return array{ok: bool, rtt_ms: int, skew_s: float, eff_s: float, node: string, egress: string, relay: bool, error: string} */
    private function ping(string $peer): array
    {
        $t0 = microtime(true);

        try {
            $r = ($this->call)($peer, 'ping', []);
        } catch (Throwable $e) {
            return ['ok' => false, 'rtt_ms' => (int) round((microtime(true) - $t0) * 1000), 'skew_s' => 0.0, 'eff_s' => 0.0, 'node' => '', 'egress' => '', 'relay' => false, 'error' => $e->getMessage()];
        }

        $t1                = microtime(true);

        if ($this->db !== null && isset($r['time']) && is_numeric($r['time'])) {
            (new ClockMonitor($this->db, $this->opt))->observeResponse($peer, $t0, $t1, (float) $r['time'], isset($r['ntp']) && is_numeric($r['ntp']) ? (float) $r['ntp'] : null);
        }

        $this->info[$peer] = ['relay' => ! empty($r['relay']), 'peers' => is_array($r['peers'] ?? null) ? $r['peers'] : []];

        return [
            'ok'     => true,
            'rtt_ms' => (int) round(($t1 - $t0) * 1000),
            'skew_s' => (float) ($r['time'] ?? $t1) - ($t0 + $t1) / 2,
            // what is left of the difference once both nodes have applied their NTP correction to their stamps
            'eff_s'  => (float) ($r['time'] ?? $t1) - ($t0 + $t1) / 2 + (isset($r['ntp']) && is_numeric($r['ntp']) ? (float) $r['ntp'] : 0.0) - (Support::correctionS() ?? 0.0),
            'node'   => (string) ($r['node'] ?? ''),
            'egress' => (string) ($r['ip'] ?? ''),
            'relay'  => ! empty($r['relay']),
            'error'  => '',
        ];
    }

    /** @return array{ok: bool, rtt_ms: int, error: string} */
    private function probe(string $peer): array
    {
        try {
            $r = ($this->call)($peer, 'probe', []);
        } catch (Throwable $e) {
            return ['ok' => false, 'rtt_ms' => 0, 'error' => $e->getMessage()];
        }

        return ['ok' => ! empty($r['ok']), 'rtt_ms' => (int) ($r['rtt_ms'] ?? 0), 'error' => (string) ($r['error'] ?? '')];
    }

    /**
     * Asks a relay to ping one of ITS peers for us.
     *
     * @return array{ok: bool, rtt_ms: int, skew_s: float, error: string}
     */
    private function viaRelay(string $relay, string $target): array
    {
        try {
            $r = ($this->call)($relay, 'probe', ['peer' => $target]);
        } catch (Throwable $e) {
            return ['ok' => false, 'rtt_ms' => 0, 'skew_s' => 0.0, 'error' => "relay {$relay}: " . $e->getMessage()];
        }

        return ['ok' => ! empty($r['ok']), 'rtt_ms' => (int) ($r['rtt_ms'] ?? 0), 'skew_s' => (float) ($r['skew_s'] ?? 0.0), 'error' => (string) ($r['error'] ?? '')];
    }

    /** A reachable relay (not the target itself) that holds the target as a peer, or null. */
    private function relayFor(string $target): ?string
    {
        foreach ($this->info as $name => $inf) {
            if ($name !== $target && ! empty($inf['relay']) && isset($inf['peers'][$target])) {
                return (string) $name;
            }
        }

        return null;
    }

    private function worst(string $a, string $b): string
    {
        $rank = [self::OK => 0, self::WARN => 1, self::FAIL => 2];

        return $rank[$b] > $rank[$a] ? $b : $a;
    }

    /** Seconds since the passive peer last advanced its cursor on this node (it pushes/pulls), null if never. */
    private function lastSeen(string $peer): ?int
    {
        if ($this->db === null) {
            return null;
        }

        $row = $this->db->table('sync_cursor')->where('peer_node', $peer)->get()->getRowArray();

        return $row === null || (int) $row['last_ts'] === 0 ? null : max(0, intdiv(Support::nowUs() - (int) $row['last_ts'], 1_000_000));
    }

    /**
     * `ping`: who I am, my clock, the address the caller reached me from, and, if I relay, the peers I hold
     * (passive ones with the seconds since they last made contact).
     *
     * @return array<string, mixed>
     */
    public function pingAnswer(string $callerIp): array
    {
        $peers = [];

        if ($this->opt->relay) {
            foreach ($this->opt->peerNames() as $name) {
                $passive       = $this->opt->isPassivePeer($name);
                $peers[$name] = ['passive' => $passive, 'seen' => $passive ? $this->lastSeen($name) : null];
            }
        }

        return ['node' => $this->opt->node, 'time' => microtime(true), 'passive' => $this->opt->passive, 'ip' => $callerIp, 'relay' => $this->opt->relay, 'peers' => $peers];
    }

    /**
     * `probe`. Without a name: call the caller back at the URL THIS node has configured for it. With a name: ping
     * that peer of mine on the caller's behalf (a relay check). Never a URL from the request.
     *
     * @return array{ok: bool, rtt_ms: int, skew_s?: float, error: string}
     */
    public function probeBack(string $caller, string $target = '', ?callable $call = null): array
    {
        $who = $target !== '' ? $target : $caller;

        if ($target !== '' && ($target === $caller || ! $this->opt->isPeer($target))) {
            return ['ok' => false, 'rtt_ms' => 0, 'error' => $target === $caller ? 'a node cannot be probed through itself' : "{$this->opt->node} has no peer '{$target}'"];
        }

        if ($this->opt->isPassivePeer($who)) {
            return ['ok' => false, 'rtt_ms' => 0, 'error' => "{$who} is configured as passive on {$this->opt->node}: it can only dial in"];
        }

        if ($this->opt->urlOf($who) === '') {
            return ['ok' => false, 'rtt_ms' => 0, 'error' => "{$this->opt->node} has no URL for {$who}"];
        }

        $t0 = microtime(true);

        try {
            $r = ($call ?? $this->call)($who, 'ping', []);
        } catch (Throwable $e) {
            return ['ok' => false, 'rtt_ms' => (int) round((microtime(true) - $t0) * 1000), 'error' => $e->getMessage()];
        }

        $t1  = microtime(true);
        $rtt = (int) round(($t1 - $t0) * 1000);

        if ((string) ($r['node'] ?? '') !== $who) {
            return ['ok' => false, 'rtt_ms' => $rtt, 'error' => "{$this->opt->urlOf($who)} answered as '" . ($r['node'] ?? '?') . "', not '{$who}'"];
        }

        return ['ok' => true, 'rtt_ms' => $rtt, 'skew_s' => (float) ($r['time'] ?? $t1) - ($t0 + $t1) / 2, 'error' => ''];
    }
}
