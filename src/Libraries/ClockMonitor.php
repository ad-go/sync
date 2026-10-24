<?php

declare(strict_types=1);

namespace AdGo\Sync\Libraries;

use CodeIgniter\Database\BaseConnection;
use Throwable;

/**
 * The clock of each peer relative to this node, measured on the signed traffic that flows anyway.
 *
 * A client sample comes from a response that carries the peer's time: offset = peer time - midpoint of the round
 * trip (NTP style), rtt = the round trip. A server sample comes from the signed request timestamp (whole seconds,
 * latency included), so its round trip is given as a coarse 2 s and never replaces a client sample. The sample with
 * the smallest round trip wins; an older one (over ten minutes) is always replaced.
 *
 * With `clockCorrect` a node stamps its changes with real time (clock + NTP offset) and reports that offset in its
 * answers (`clock_ntp_us`). What matters for rows is then the *effective* difference: peer offset + the peer's
 * correction - ours. Files keep comparing raw mtimes, so they ask for the raw difference.
 *
 * `unreliable()` is what the conflict rules ask: is this peer's clock so far from ours that "newest wins" would be
 * a coin toss? Nothing here may break a sync run, so every failure (columns not migrated yet, no table) is swallowed.
 */
final class ClockMonitor
{
    public const STALE_US = 600_000_000;
    /** A measurement older than this says nothing about the clocks now. */
    public const EXPIRE_S = 3600;
    /** Round trip assigned to a sample taken from a request timestamp (second resolution). */
    public const COARSE_RTT_US = 2_000_000;

    /** @var array<int, bool> */
    private static array $ready = [];

    /** @var array<int, bool> */
    private static array $ntpReady = [];

    public function __construct(private readonly BaseConnection $db, private readonly Options $opt)
    {
    }

    /** Peer clock minus ours in microseconds, from a response that carries the peer's time. */
    public function observeResponse(string $peer, float $t0, float $t3, float $peerTime, ?float $peerNtp = null): void
    {
        $this->observe(
            $peer,
            (int) round(($peerTime - ($t0 + $t3) / 2) * 1_000_000),
            (int) round(($t3 - $t0) * 1_000_000),
            null,
            $peerNtp === null ? null : (int) round($peerNtp * 1_000_000),
        );
    }

    /** From the signed timestamp of an incoming request (whole seconds: add half a second for the truncation). */
    public function observeRequest(string $peer, int $requestTs, ?int $nowUs = null): void
    {
        $this->observe($peer, $requestTs * 1_000_000 + 500_000 - ($nowUs ?? Support::nowUs()), self::COARSE_RTT_US, $nowUs);
    }

    public function observe(string $peer, int $offsetUs, int $rttUs, ?int $nowUs = null, ?int $ntpUs = null): void
    {
        try {
            if (! $this->ready()) {
                return;
            }

            $nowUs ??= Support::nowUs();
            $row     = $this->row($peer);

            if ($row !== null && $row['clock_at'] !== null) {
                $age = $nowUs - (int) $row['clock_at'];

                if ($age <= self::STALE_US && $rttUs > (int) $row['clock_rtt_us'] * 1.25) {
                    return; // a worse measurement than the one we have
                }

                $knownNtp = ($row['clock_ntp_us'] ?? null) === null ? null : (int) $row['clock_ntp_us'];

                if ($age < 30_000_000 && abs($offsetUs - (int) $row['clock_offset_us']) < 100_000 && ($ntpUs === $knownNtp || ! $this->ntpReady())) {
                    return; // nothing new worth a write
                }
            }

            $data = ['clock_offset_us' => $offsetUs, 'clock_rtt_us' => $rttUs, 'clock_at' => $nowUs];

            if ($this->ntpReady()) {
                $data['clock_ntp_us'] = $ntpUs;
            }

            if ($row === null) {
                $this->db->table('sync_cursor')->insert($data + ['peer_node' => $peer]);
            } else {
                $this->db->table('sync_cursor')->where('peer_node', $peer)->update($data);
            }
        } catch (Throwable) {
            // measuring the clock must never break a sync run
        }
    }

    /** @return array{offset_s: float, rtt_ms: int, age_s: int, ntp_s: float|null, effective_s: float}|null peer clock minus ours (raw), and corrected on both sides (effective) */
    public function get(string $peer, ?int $nowUs = null): ?array
    {
        try {
            if (! $this->ready()) {
                return null;
            }

            $row = $this->row($peer);
        } catch (Throwable) {
            return null;
        }

        if ($row === null || $row['clock_at'] === null) {
            return null;
        }

        $age = intdiv(($nowUs ?? Support::nowUs()) - (int) $row['clock_at'], 1_000_000);

        if ($age > self::EXPIRE_S) {
            return null;
        }

        $raw = (int) $row['clock_offset_us'] / 1_000_000;
        $ntp = ($row['clock_ntp_us'] ?? null) === null ? null : (int) $row['clock_ntp_us'] / 1_000_000;

        return ['offset_s' => $raw, 'rtt_ms' => intdiv((int) $row['clock_rtt_us'], 1000), 'age_s' => $age, 'ntp_s' => $ntp, 'effective_s' => $raw + ($ntp ?? 0.0) - (Support::correctionS() ?? 0.0)];
    }

    /**
     * True when the peer's measured clock differs from ours by more than `clockMax` (0 disables the guard). `$raw`:
     * the difference of the plain clocks (for file mtimes) instead of the one left after both sides' corrections.
     */
    public function unreliable(string $peer, ?int $nowUs = null, bool $raw = false): bool
    {
        if ($this->opt->clockMax <= 0.0) {
            return false;
        }

        $c = $this->get($peer, $nowUs);

        return $c !== null && abs($raw ? $c['offset_s'] : $c['effective_s']) > $this->opt->clockMax;
    }

    /** @return array<string, mixed>|null */
    private function row(string $peer): ?array
    {
        return $this->db->table('sync_cursor')->where('peer_node', $peer)->get()->getRowArray();
    }

    private function ntpReady(): bool
    {
        return self::$ntpReady[spl_object_id($this->db)] ??= $this->db->fieldExists('clock_ntp_us', 'sync_cursor');
    }

    private function ready(): bool
    {
        return self::$ready[spl_object_id($this->db)] ??= $this->db->fieldExists('clock_offset_us', 'sync_cursor');
    }
}
