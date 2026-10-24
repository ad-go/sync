<?php

declare(strict_types=1);

namespace AdGo\Sync\Libraries;

use Closure;
use CodeIgniter\Database\BaseConnection;
use Throwable;

/**
 * Distributed write-lock for two nodes (`auth`: editing users, `cron`: running
 * the scheduler). Exactly one holder at a time.
 *
 * Two modes:
 *  - `manual`: taken explicitly (`sync:auth-heartbeat --acquire`, the status page); the holder renews a heartbeat
 *    (`sync:auth-heartbeat`, from cron) and the peer mirrors it. TTL `lockTtl`.
 *  - `lease`: taken automatically by `begin()` when somebody starts editing users/groups. It publishes the
 *    editing position (`scope`, `edited_by`) to the peer, which shows it as a read-only banner. Only edit
 *    activity refreshes it (the cron heartbeat does not), so an abandoned edit frees itself after `leaseTtl`.
 *    `handover()` releases it only after the peer confirmed that it applied our journal.
 *
 * If the heartbeat is older than the TTL the lock is abandoned and can be taken over.
 *
 * Anti split-brain rules:
 *  - a live lock is never stolen (the peer must be asked first);
 *  - a node first records its own claim, then asks the peer; if both claim at
 *    the same moment the node ranked first in `tiebreak` (site1) wins;
 *  - if the peer cannot be reached, ONLY the first-ranked node takes an
 *    abandoned lock automatically. The other one needs `$confirm = true`
 *    (an explicit human decision);
 *  - after taking a lock from a reachable peer the new holder first catches up with the peer's journal, so it
 *    never edits on top of stale data.
 *
 * Residual risk (inherent to two nodes + asynchronous HTTP, no third vote): a
 * network partition combined with a confirmed takeover can leave both nodes
 * writing. auth rows carry node_origin so it can be reconciled manually.
 */
class AuthLock
{
    private const RACE_WINDOW_US = 10_000_000;

    /**
     * @param (Closure(): void)|null $catchUp pulls the peer's journal (called after a lock was taken from a reachable peer)
     * @param (Closure(): void)|null $publish records local out-of-band changes in the journal (called before a handover)
     */
    public function __construct(
        private readonly BaseConnection $db,
        private readonly Options $opt,
        private readonly ?PeerClient $peer = null,
        private readonly ?Closure $catchUp = null,
        private readonly ?Closure $publish = null,
    ) {
    }

    public function isHeld(string $kind = 'auth'): bool
    {
        $r = $this->row($kind);

        return $r !== null && $r['holder'] === $this->opt->node && $this->fresh($r);
    }

    /** Live holder (fresh heartbeat) or null. */
    public function holder(string $kind = 'auth'): ?string
    {
        $r = $this->row($kind);

        return $r !== null && $r['holder'] !== null && $this->fresh($r) ? (string) $r['holder'] : null;
    }

    /**
     * Who holds the lock and what they are doing, for banners and the status page. Null when free.
     *
     * @return array{holder: string, mode: string, scope: string, by: string, since: int, expires_in: int}|null
     */
    public function describe(string $kind = 'auth'): ?array
    {
        $r = $this->row($kind);

        if ($r === null || $r['holder'] === null || ! $this->fresh($r)) {
            return null;
        }

        $ttl = (int) ($r['ttl_seconds'] ?: $this->opt->lockTtl);

        return [
            'holder'     => (string) $r['holder'],
            'mode'       => (string) ($r['mode'] ?: 'manual'),
            'scope'      => (string) ($r['scope'] ?? ''),
            'by'         => (string) ($r['edited_by'] ?? ''),
            'since'      => intdiv((int) $r['acquired_at'], 1_000_000),
            'expires_in' => max(0, $ttl - intdiv(Support::nowUs() - (int) $r['heartbeat_at'], 1_000_000)),
        ];
    }

    /**
     * @return array{ok: bool, holder: ?string, reason?: string}
     */
    public function acquire(string $kind = 'auth', bool $confirm = false): array
    {
        return $this->claim($kind, $confirm, ['mode' => 'manual']);
    }

    /**
     * An editor starts (or continues) editing: take or refresh the `lease` and publish the editing position.
     * The peer shows "holder edits <scope> (<by>)" and refuses its own edits meanwhile.
     *
     * @return array{ok: bool, holder: ?string, reason?: string}
     */
    public function begin(string $kind = 'auth', string $scope = '', string $by = '', bool $confirm = false): array
    {
        $meta = ['mode' => 'lease', 'scope' => $scope, 'by' => $by];

        $r = $this->row($kind);

        if ($r !== null && $r['holder'] === $this->opt->node && $this->fresh($r)) {
            // A lock taken explicitly by a human stays as it is; only leases follow the edit activity.
            if (($r['mode'] ?: 'manual') === 'lease') {
                $this->touch($kind, $meta);
            }

            return ['ok' => true, 'holder' => $this->opt->node];
        }

        return $this->claim($kind, $confirm, $meta);
    }

    /**
     * Ends an editing session: makes sure the peer has applied our journal, then releases the lock.
     * Not holding the lock is not an error. If the peer cannot confirm, the lock stays (the lease expires on its own).
     *
     * @return array{ok: bool, reason?: string}
     */
    public function handover(string $kind = 'auth', int $waitSeconds = 10): array
    {
        if (! $this->isHeld($kind)) {
            return ['ok' => true];
        }

        if ($this->peer === null) {
            $this->release($kind);

            return ['ok' => true];
        }

        try {
            if ($this->publish !== null) {
                ($this->publish)();
            }

            $head     = (int) ($this->db->table('sync_changes')->selectMax('seq')->get()->getRowArray()['seq'] ?? 0);
            $deadline = microtime(true) + $waitSeconds;
            $kick     = true;

            do {
                $applied = (int) ($this->peer->lockSync($this->opt->node, $kick)['applied'] ?? 0);
                $kick    = false;

                if ($applied >= $head) {
                    $this->release($kind);

                    return ['ok' => true];
                }

                usleep(400_000);
            } while (microtime(true) < $deadline);
        } catch (Throwable $e) {
            return ['ok' => false, 'reason' => 'peer unreachable (' . $e->getMessage() . '); the lease stays until it expires'];
        }

        return ['ok' => false, 'reason' => 'the peer has not applied our changes yet; the lease stays until it expires'];
    }

    /**
     * @return array{ok: bool}
     */
    public function heartbeat(string $kind = 'auth'): array
    {
        if (! $this->isHeld($kind)) {
            return ['ok' => false];
        }

        $now = Support::nowUs();
        $this->db->table('sync_write_lock')->where('lock_kind', $kind)->update(['heartbeat_at' => $now]);

        if ($this->peer !== null) {
            try {
                $this->peer->lockHeartbeat($kind, $this->opt->node);
            } catch (Throwable) {
                // best effort: the peer will see the TTL expire only if we really stop
            }
        }

        return ['ok' => true];
    }

    /**
     * What the scheduler calls: renews MANUAL locks only. A lease lives on edit activity, not on the clock.
     *
     * @return array{ok: bool}
     */
    public function renewManual(string $kind = 'auth'): array
    {
        $r = $this->row($kind);

        if ($r === null || ($r['mode'] ?: 'manual') !== 'manual') {
            return ['ok' => false];
        }

        return $this->heartbeat($kind);
    }

    public function release(string $kind = 'auth'): void
    {
        if ($this->putIfHolder($kind, $this->opt->node, null) && $this->peer !== null) {
            try {
                $this->peer->lockRelease($kind, $this->opt->node);
            } catch (Throwable) {
            }
        }
    }

    /** @return array{holder: ?string, heartbeat_at: int, acquired_at: int, ttl: int, now: int} */
    public function handleStatus(string $kind): array
    {
        $r = $this->row($kind);

        return [
            'holder'       => $r !== null && $this->fresh($r) ? $r['holder'] : null,
            'heartbeat_at' => (int) ($r['heartbeat_at'] ?? 0),
            'acquired_at'  => (int) ($r['acquired_at'] ?? 0),
            'ttl'          => $this->opt->lockTtl,
            'now'          => Support::nowUs(),
        ];
    }

    /**
     * @param array<string, string> $meta mode / scope / by published by the requester
     *
     * @return array{ok: bool, holder: ?string, reason?: string}
     */
    public function handleAcquire(string $kind, string $requester, array $meta = []): array
    {
        $me   = $this->opt->node;
        $live = $this->holder($kind);
        $now  = Support::nowUs();

        if ($live === $me) {
            $r = $this->row($kind);
            // Simultaneous claim: the higher-ranked node wins.
            if ($now - (int) $r['acquired_at'] < self::RACE_WINDOW_US && $this->opt->rank($requester) < $this->opt->rank($me)) {
                $this->put($kind, $requester, $now, $now, $meta);

                return ['ok' => true, 'holder' => $requester];
            }

            return ['ok' => false, 'holder' => $me, 'reason' => Lang::line('lock.heldBy', [$me, $this->suffix($r)])];
        }

        $this->put($kind, $requester, $now, $now, $meta); // free, abandoned, or already the requester

        return ['ok' => true, 'holder' => $requester];
    }

    /**
     * @param array<string, string> $meta
     *
     * @return array{ok: bool}
     */
    public function handleHeartbeat(string $kind, string $holder, array $meta = []): array
    {
        if ($holder === $this->opt->node) {
            return ['ok' => false];
        }

        $now = Support::nowUs();
        $r   = $this->row($kind);

        if ($r === null || $r['holder'] !== $holder) {
            $this->put($kind, $holder, $now, $now, $meta);
        } else {
            $upd = ['heartbeat_at' => $now];

            if ($meta !== []) {
                $upd += $this->metaColumns($meta);
            }

            $this->db->table('sync_write_lock')->where('lock_kind', $kind)->update($upd);
        }

        return ['ok' => true];
    }

    /** @return array{ok: bool} */
    public function handleRelease(string $kind, string $holder): array
    {
        $this->putIfHolder($kind, $holder, null);

        return ['ok' => true];
    }

    /** How far this node has applied the journal of `$from` (the requester asks it before releasing a lease). */
    public function handleApplied(string $from): int
    {
        $r = $this->db->table('sync_cursor')->where('peer_node', $from)->get()->getRowArray();

        return (int) ($r['last_seq'] ?? 0);
    }

    /**
     * @param array<string, string> $meta
     *
     * @return array{ok: bool, holder: ?string, reason?: string}
     */
    private function claim(string $kind, bool $confirm, array $meta): array
    {
        $me   = $this->opt->node;
        $now  = Support::nowUs();
        $live = $this->holder($kind);

        if ($live === $me) {
            $this->touch($kind, $meta);

            return ['ok' => true, 'holder' => $me];
        }

        if ($live !== null) {
            return ['ok' => false, 'holder' => $live, 'reason' => Lang::line('lock.heldBy', [$live, $this->suffix($this->row($kind))])];
        }

        $this->put($kind, $me, $now, $now, $meta);

        $peerReached = false;

        if ($this->peer !== null) {
            try {
                $answer      = $this->peer->lockAcquire($kind, $me, $confirm, $meta);
                $peerReached = true;

                if (! ($answer['ok'] ?? false)) {
                    $this->putIfHolder($kind, $me, null);

                    return ['ok' => false, 'holder' => $answer['holder'] ?? null, 'reason' => $answer['reason'] ?? Lang::line('lock.refusedByPeer')];
                }
            } catch (Throwable) {
                $peerReached = false;
            }
        }

        if (! $peerReached && $this->opt->rank($me) !== 0 && ! $confirm) {
            $this->putIfHolder($kind, $me, null);

            return ['ok' => false, 'holder' => null, 'reason' => Lang::line('lock.peerUnreachableConfirm')];
        }

        // Never edit on top of stale data: bring in whatever the previous holder has written.
        if ($peerReached && $this->catchUp !== null) {
            try {
                ($this->catchUp)();
            } catch (Throwable $e) {
                $this->release($kind);

                return ['ok' => false, 'holder' => null, 'reason' => Lang::line('lock.couldNotCatchUp', [$this->opt->peerNode, $e->getMessage()])];
            }
        }

        return ['ok' => true, 'holder' => $me];
    }

    /**
     * Refreshes a lock this node holds and republishes the editing position.
     *
     * @param array<string, string> $meta
     */
    private function touch(string $kind, array $meta): void
    {
        $this->db->table('sync_write_lock')->where('lock_kind', $kind)->update(['heartbeat_at' => Support::nowUs()] + $this->metaColumns($meta));

        if ($this->peer !== null) {
            try {
                $this->peer->lockHeartbeat($kind, $this->opt->node, $meta);
            } catch (Throwable) {
            }
        }
    }

    /**
     * " (editing users:12 by admin)" for the reason of a refusal, '' when nothing is published.
     *
     * @param array<string, mixed>|null $r
     */
    private function suffix(?array $r): string
    {
        if ($r === null || (string) ($r['scope'] ?? '') === '') {
            return '';
        }

        $by = (string) ($r['edited_by'] ?? '');

        return ' ' . ($by !== '' ? Lang::line('lock.editingBy', [$r['scope'], $by]) : Lang::line('lock.editing', [$r['scope']]));
    }

    /** @return array<string, mixed>|null */
    private function row(string $kind): ?array
    {
        return $this->db->table('sync_write_lock')->where('lock_kind', $kind)->get()->getRowArray();
    }

    /** @param array<string, mixed> $r */
    private function fresh(array $r): bool
    {
        $ttl = (int) ($r['ttl_seconds'] ?: $this->opt->lockTtl);

        return Support::nowUs() - (int) $r['heartbeat_at'] <= $ttl * 1_000_000;
    }

    /**
     * @param array<string, string> $meta
     *
     * @return array<string, mixed>
     */
    private function metaColumns(array $meta): array
    {
        $mode = ($meta['mode'] ?? 'manual') === 'lease' ? 'lease' : 'manual';

        return [
            'mode'        => $mode,
            'scope'       => mb_substr((string) ($meta['scope'] ?? ''), 0, 120) ?: null,
            'edited_by'   => mb_substr((string) ($meta['by'] ?? ''), 0, 60) ?: null,
            'ttl_seconds' => $mode === 'lease' ? $this->opt->leaseTtl : $this->opt->lockTtl,
        ];
    }

    /** @param array<string, string> $meta */
    private function put(string $kind, ?string $holder, int $acquired, int $heartbeat, array $meta = []): void
    {
        $data = ['holder' => $holder, 'acquired_at' => $acquired, 'heartbeat_at' => $heartbeat] + $this->metaColumns($meta);

        if ($this->row($kind) === null) {
            $this->db->table('sync_write_lock')->insert($data + ['lock_kind' => $kind]);
        } else {
            $this->db->table('sync_write_lock')->where('lock_kind', $kind)->update($data);
        }
    }

    private function putIfHolder(string $kind, string $expected, ?string $new): bool
    {
        $r = $this->row($kind);

        if ($r === null || $r['holder'] !== $expected) {
            return false;
        }

        $this->db->table('sync_write_lock')->where('lock_kind', $kind)->update(['holder' => $new]);

        return true;
    }
}
