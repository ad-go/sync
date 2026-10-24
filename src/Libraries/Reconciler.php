<?php

declare(strict_types=1);

namespace AdGo\Sync\Libraries;

use CodeIgniter\Database\BaseConnection;

/**
 * Heart of the package: applies ONE change received from the peer.
 *
 * Versioning (two nodes, so a tiny version vector is enough). For every row
 * each node keeps `rev` (its own write counter) and `peer_rev` (the peer's
 * counter it has integrated). A change carries the sender's `rev` (R) and the
 * sender's `peer_rev` as `seen` (S = how much of MY history it had integrated).
 *
 *   remote is new   <=>  R > my peer_rev
 *   I am new        <=>  my rev > S
 *   only remote new  ->  fast-forward (apply)
 *   both new         ->  concurrent: three-way field merge against the last
 *                        agreed snapshot (base_blob), then classify.
 *
 * Auto-resolution is deterministic and symmetric: both nodes reach the same
 * result without exchanging anything more. Dangerous conflicts apply NOTHING
 * and go to quarantine (sync_conflicts, status = pending).
 */
class Reconciler
{
    public const SKIPPED     = 'skipped';
    public const APPLIED     = 'applied';
    public const CONVERGED   = 'converged';
    public const MERGED      = 'merged';
    public const LWW         = 'lww';
    public const QUARANTINED = 'quarantined';
    public const PENDING     = 'pending';
    public const REJECTED    = 'rejected';

    /** @var callable|null fn(array $conflictRow): void  called for newly escalated conflicts */
    private $onEscalated;

    public function __construct(
        private readonly BaseConnection $db,
        private readonly Options $opt,
        private readonly MetaStore $meta,
        private readonly RowGateway $rows,
        ?callable $onEscalated = null,
    ) {
        $this->onEscalated = $onEscalated;
    }

    /**
     * @param array<string, mixed> $c change as produced by MetaStore::changesSince()
     *
     * @return array{outcome: string, conflict_id?: int}
     */
    public function apply(array $c): array
    {
        // A change that this node made itself and that a relaying hub hands back (`origin` = us) is an echo: nothing to apply
        // (applying it would rewrite every row we pushed, firing the triggers of the legacy tables).
        if ((string) ($c['origin'] ?? '') === $this->opt->node) {
            return ['outcome' => self::CONVERGED];
        }

        $res = $this->applyChange($c);

        // A hub forwards what it took from a peer to its other peers (see Options::$relay). Echoes of its own
        // relayed entries come back as CONVERGED and are not relayed again.
        $origin = (string) ($c['origin'] ?? $c['node'] ?? '');

        if ($this->opt->relay && $origin !== '' && $origin !== $this->opt->node && in_array($res['outcome'], [self::APPLIED, self::MERGED, self::LWW], true)) {
            $table = (string) $c['table'];
            $uuid  = (string) $c['uuid'];
            $meta  = $this->meta->findByUuid($table, $uuid);

            if ($meta !== null) {
                $state = $meta['deleted_at'] !== null || $meta['base_blob'] === null ? null : Support::unpack($meta['base_blob']);
                $this->meta->relay($table, $uuid, is_array($state) ? $state : null, $origin);
            }
        }

        return $res;
    }

    /**
     * @param array<string, mixed> $c one journal entry of the peer
     *
     * @return array{outcome: string, conflict_id?: int}
     */
    private function applyChange(array $c): array
    {
        $table = (string) $c['table'];
        $cfg   = $this->opt->table($table);

        if ($cfg === null) {
            return ['outcome' => self::SKIPPED];
        }

        // Single-writer tables (`writers` in the table config): a change whose origin is not a listed writer is refused.
        // This is what keeps the two-counter versioning sound when more than two nodes are connected through a relaying
        // hub: it cannot order concurrent edits made on two different peers, so such tables must have ONE writer node.
        if (! empty($cfg['writers']) && ! in_array((string) ($c['origin'] ?? $c['node'] ?? ''), $cfg['writers'], true)) {
            return ['outcome' => self::REJECTED];
        }

        $uuid = (string) $c['uuid'];
        $meta = $this->meta->findByUuid($table, $uuid);
        $rev  = (int) $c['rev'];
        $auth = ! empty($c['authoritative']);
        $wire = $c['op'] === 'D' ? null : $c['row'];

        // columns this node ignores are not part of its row state either (else the hash recorded here never matches what the scan reads back)
        foreach ($wire === null ? [] : $cfg['ignore_fields'] as $f) {
            unset($wire[$f]);
        }

        if ($meta === null) {
            return Support::tx($this->db, function () use ($table, $uuid, $c, $rev, $wire) {
                $rowId = $wire === null ? null : $this->rows->insert($table, $wire);
                $this->meta->insertState($table, $uuid, [
                    'row_id'      => $rowId,
                    'updated_at'  => (int) $c['changed_at'],
                    'rev'         => 0,
                    'peer_rev'    => $rev,
                    'node_origin' => (string) $c['node'],
                    'deleted_at'  => $wire === null ? (int) $c['changed_at'] : null,
                    'row_hash'    => Support::hashRow($wire),
                    'base_blob'   => $wire === null ? null : Support::pack($wire),
                ]);

                return ['outcome' => self::APPLIED];
            });
        }

        $L = (int) $meta['rev'];
        $P = (int) $meta['peer_rev'];
        $S = (int) $c['seen'];

        if ($auth) {
            // A conflict resolution made on the peer: take it as is.
            $pending = $this->pendingConflict($table, $uuid);

            return Support::tx($this->db, function () use ($table, $uuid, $meta, $c, $rev, $wire, $pending) {
                $this->writeState($table, $meta, $wire);
                $this->settle($table, $uuid, $wire, $rev, (int) $c['changed_at'], (string) $c['node'], $rev);

                if ($pending !== null) {
                    $this->closeConflict((int) $pending['id'], 'remote-resolution', 'peer');
                }

                return ['outcome' => self::APPLIED];
            });
        }

        if ($rev <= $P) {
            return ['outcome' => self::SKIPPED]; // idempotent: already integrated
        }

        $pending = $this->pendingConflict($table, $uuid);

        if ($pending !== null) {
            $this->db->table('sync_conflicts')->where('id', $pending['id'])->update([
                'remote_blob' => Support::pack($wire),
                'remote_rev'  => $rev,
                'remote_ts'   => (int) $c['changed_at'],
            ]);

            return ['outcome' => self::PENDING, 'conflict_id' => (int) $pending['id']];
        }

        $localNew = $L > $S;

        if (! $localNew) {
            return Support::tx($this->db, function () use ($table, $uuid, $meta, $c, $rev, $wire) {
                $this->writeState($table, $meta, $wire);
                $this->settle($table, $uuid, $wire, $rev, (int) $c['changed_at'], (string) $c['node'], null);

                return ['outcome' => self::APPLIED];
            });
        }

        return $this->concurrent($table, $uuid, $meta, $c, $wire, $cfg);
    }

    /**
     * Both sides changed the row since the last agreement.
     *
     * @param array<string, mixed>      $meta
     * @param array<string, mixed>      $c
     * @param array<string, mixed>|null $remote
     * @param array<string, mixed>      $cfg
     *
     * @return array{outcome: string, conflict_id?: int}
     */
    private function concurrent(string $table, string $uuid, array $meta, array $c, ?array $remote, array $cfg): array
    {
        $rev = (int) $c['rev'];

        try {
            $local = $this->currentLocal($table, $meta);
        } catch (\RuntimeException) {
            // the local row points to a parent that is gone (deleted before its children): it cannot be exported any more
            if ($remote === null) {
                return Support::tx($this->db, function () use ($table, $uuid, $meta, $c, $rev) {
                    $this->writeState($table, $meta, null);
                    $this->settle($table, $uuid, null, $rev, (int) $c['changed_at'], (string) $c['node'], null);

                    return ['outcome' => self::APPLIED];
                });
            }

            $local = null;
        }

        $base = $this->baseFor($table, $uuid, (int) $c['seen'], $meta);

        if ($this->sameState($local, $remote, $cfg)) {
            Support::tx($this->db, function () use ($table, $uuid, $remote, $rev) {
                $this->settle($table, $uuid, $remote, $rev, null, null, null);
            });

            return ['outcome' => self::CONVERGED];
        }

        $localTs  = (int) $meta['updated_at'];
        $remoteTs = (int) $c['changed_at'];
        $winner   = $this->newer($localTs, (string) $meta['node_origin'], $remoteTs, (string) $c['node']);
        $lww      = $cfg['policy'] === 'tolerant' && ! $this->clockUntrusted((string) $c['node']);

        if ($local === null || $remote === null) {
            if ($lww) {
                $state = $winner === 'local' ? $local : $remote;

                return $this->autoApply($table, $uuid, $meta, $c, $state, $local, $remote, $base, ['*'], self::LWW, 'auto-lww', $winner);
            }

            return $this->quarantine($table, $uuid, $meta, $c, $local, $remote, $base, ['*']);
        }

        $ignore  = $cfg['ignore_fields'];
        $fields  = array_values(array_diff(array_unique(array_merge(array_keys($local), array_keys($remote))), $ignore));
        $merged  = $local;
        $overlap = [];

        foreach ($fields as $f) {
            $l = $local[$f] ?? null;
            $r = $remote[$f] ?? null;

            if (Support::same($l, $r)) {
                continue;
            }

            $b        = $base !== null && array_key_exists($f, $base) ? $base[$f] : null;
            $hasBase  = $base !== null && array_key_exists($f, $base);
            $changedL = ! $hasBase || ! Support::same($l, $b);
            $changedR = ! $hasBase || ! Support::same($r, $b);

            if ($changedL && $changedR) {
                $overlap[] = $f;
            } elseif ($changedR) {
                $merged[$f] = $r;
            }
        }

        if ($overlap === []) {
            ksort($merged);

            return $this->autoApply($table, $uuid, $meta, $c, $merged, $local, $remote, $base, [], self::MERGED, 'auto-merge', $winner);
        }

        $sensitive = array_intersect($overlap, $cfg['sensitive_fields']);

        if ($lww && $sensitive === []) {
            foreach ($overlap as $f) {
                $merged[$f] = $winner === 'local' ? ($local[$f] ?? null) : ($remote[$f] ?? null);
            }

            ksort($merged);

            return $this->autoApply($table, $uuid, $meta, $c, $merged, $local, $remote, $base, $overlap, self::LWW, 'auto-lww', $winner);
        }

        return $this->quarantine($table, $uuid, $meta, $c, $local, $remote, $base, $overlap);
    }

    /**
     * Last state both nodes are known to have shared. If the sender says it had
     * integrated my revision S, my own journal still holds exactly that state;
     * otherwise fall back to the snapshot saved at the last agreement.
     *
     * @param array<string, mixed> $meta
     *
     * @return array<string, mixed>|null
     */
    private function baseFor(string $table, string $uuid, int $seen, array $meta): ?array
    {
        if ($seen > 0) {
            $j = $this->db->table('sync_changes')
                ->where(['table_name' => $table, 'uuid' => $uuid, 'rev' => $seen, 'node_origin' => $this->opt->node])
                ->where('op !=', 'D')->orderBy('seq', 'DESC')->limit(1)->get()->getRowArray();

            if ($j !== null && $j['payload'] !== null) {
                return Support::unpack($j['payload']);
            }
        }

        return $meta['base_blob'] === null ? null : Support::unpack($meta['base_blob']);
    }

    /**
     * Applies a deterministic auto-resolution. BOTH versions are first written
     * to sync_conflicts so nothing is lost silently.
     *
     * @param array<string, mixed>      $meta
     * @param array<string, mixed>      $c
     * @param array<string, mixed>|null $state
     * @param array<string, mixed>|null $local
     * @param array<string, mixed>|null $remote
     * @param array<string, mixed>|null $base
     * @param list<string>              $fields
     *
     * @return array{outcome: string, conflict_id?: int}
     */
    private function autoApply(string $table, string $uuid, array $meta, array $c, ?array $state, ?array $local, ?array $remote, ?array $base, array $fields, string $outcome, string $resolvedAs, string $winner): array
    {
        $rev = (int) $c['rev'];
        $id  = 0;

        Support::tx($this->db, function () use (&$id, $table, $uuid, $meta, $c, $state, $local, $remote, $base, $fields, $resolvedAs, $winner, $rev) {
            $id = $this->insertConflict($table, $uuid, $meta, $c, $local, $remote, $base, $fields, 'safe', 'resolved', $resolvedAs);

            $this->writeState($table, $meta, $state);

            $ts   = max((int) $meta['updated_at'], (int) $c['changed_at']);
            $node = $winner === 'local' ? (string) $meta['node_origin'] : (string) $c['node'];

            // rev is NOT bumped: the peer reaches the same state on its own.
            $this->settle($table, $uuid, $state, $rev, $ts, $node, null);
        });

        return ['outcome' => $outcome, 'conflict_id' => $id];
    }

    /**
     * @param array<string, mixed>      $meta
     * @param array<string, mixed>      $c
     * @param array<string, mixed>|null $local
     * @param array<string, mixed>|null $remote
     * @param array<string, mixed>|null $base
     * @param list<string>              $fields
     *
     * @return array{outcome: string, conflict_id: int}
     */
    private function quarantine(string $table, string $uuid, array $meta, array $c, ?array $local, ?array $remote, ?array $base, array $fields): array
    {
        $id = $this->insertConflict($table, $uuid, $meta, $c, $local, $remote, $base, $fields, 'dangerous', 'pending', null);

        if ($this->onEscalated !== null) {
            $row = $this->db->table('sync_conflicts')->where('id', $id)->get()->getRowArray();
            ($this->onEscalated)($row);
        }

        return ['outcome' => self::QUARANTINED, 'conflict_id' => $id];
    }

    /**
     * @param array<string, mixed>      $meta
     * @param array<string, mixed>      $c
     * @param array<string, mixed>|null $local
     * @param array<string, mixed>|null $remote
     * @param array<string, mixed>|null $base
     * @param list<string>              $fields
     */
    private function insertConflict(string $table, string $uuid, array $meta, array $c, ?array $local, ?array $remote, ?array $base, array $fields, string $severity, string $status, ?string $resolvedAs): int
    {
        $now = Support::nowUs();
        $this->db->table('sync_conflicts')->insert([
            'table_name'  => $table,
            'uuid'        => $uuid,
            'local_node'  => $this->opt->node,
            'remote_node' => (string) $c['node'],
            'local_blob'  => Support::pack($local),
            'remote_blob' => Support::pack($remote),
            'base_blob'   => $base === null ? null : Support::pack($base),
            'fields'      => substr(implode(',', $fields), 0, 1000),
            'local_rev'   => (int) $meta['rev'],
            'remote_rev'  => (int) $c['rev'],
            'local_ts'    => (int) $meta['updated_at'],
            'remote_ts'   => (int) $c['changed_at'],
            'severity'    => $severity,
            'resolved_as' => $resolvedAs,
            'detected_at' => $now,
            'status'      => $status,
            'resolved_by' => $status === 'resolved' ? 'system' : null,
            'resolved_at' => $status === 'resolved' ? $now : null,
        ]);

        return (int) $this->db->insertID();
    }

    /**
     * Forces a row to an agreed state and sets BOTH counters to $newRev (used
     * by conflict resolution, on both nodes, so the next sync does not
     * resurrect the conflict). With $journal the decision is also written to
     * the change journal as an authoritative entry, which the peer applies
     * if the direct call to its resolve endpoint could not be made.
     *
     * @param array<string, mixed>|null $state
     */
    public function adopt(string $table, string $uuid, ?array $state, int $newRev, int $ts, string $node, bool $journal): void
    {
        Support::tx($this->db, function () use ($table, $uuid, $state, $newRev, $ts, $node, $journal) {
            $meta = $this->meta->findByUuid($table, $uuid);

            if ($meta === null) {
                $this->meta->saveState($table, $uuid, ['updated_at' => $ts, 'node_origin' => $node]);
                $meta = $this->meta->findByUuid($table, $uuid);
            }

            $this->writeState($table, $meta, $state);
            $this->settle($table, $uuid, $state, $newRev, $ts, $node, $newRev);

            if ($journal) {
                $this->meta->journal($table, $uuid, $state === null ? 'D' : 'U', $newRev, $newRev, $ts, $state, true);
            }
        });
    }

    /**
     * Brings the application table to `$state` (null = deleted).
     *
     * @param array<string, mixed>      $meta
     * @param array<string, mixed>|null $state
     */
    private function writeState(string $table, array $meta, ?array $state): void
    {
        $rowId = $meta['row_id'];

        if ($state === null) {
            if ($rowId !== null) {
                $this->rows->delete($table, $rowId);
            }

            return;
        }

        if ($rowId !== null && $this->rows->export($table, $rowId) !== null) {
            $this->rows->update($table, $rowId, $state);

            return;
        }

        $newId = $this->rows->insert($table, $state);
        $this->db->table('sync_row_meta')->where('id', $meta['id'])->update(['row_id' => $newId]);
    }

    /**
     * Records the agreed state in sync_row_meta WITHOUT journaling it.
     *
     * @param array<string, mixed>|null $state
     */
    private function settle(string $table, string $uuid, ?array $state, int $peerRev, ?int $ts, ?string $node, ?int $setRev): void
    {
        // read AFTER writeState(): it may just have inserted the local row
        $meta  = $this->meta->findByUuid($table, $uuid);
        $rowId = $state === null ? null : ($meta['row_id'] ?? null);

        $fields = [
            'row_id'    => $rowId,
            'peer_rev'  => $peerRev,
            'row_hash'  => Support::hashRow($state),
            'base_blob' => $state === null ? null : Support::pack($state),
            'deleted_at' => $state === null ? ($ts ?? Support::nowUs()) : null,
        ];

        if ($ts !== null) {
            $fields['updated_at'] = $ts;
        }

        if ($node !== null) {
            $fields['node_origin'] = $node;
        }

        if ($setRev !== null) {
            $fields['rev'] = $setRev;
        }

        $meta === null ? $this->meta->insertState($table, $uuid, $fields) : $this->meta->updateState((int) $meta['id'], $fields);
    }

    /**
     * @param array<string, mixed> $meta
     *
     * @return array<string, mixed>|null
     */
    private function currentLocal(string $table, array $meta): ?array
    {
        if ($meta['row_id'] === null || $meta['deleted_at'] !== null) {
            return null;
        }

        return $this->rows->export($table, $meta['row_id']);
    }

    /**
     * @param array<string, mixed>|null $a
     * @param array<string, mixed>|null $b
     * @param array<string, mixed>      $cfg
     */
    private function sameState(?array $a, ?array $b, array $cfg): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }

        foreach (array_unique(array_merge(array_keys($a), array_keys($b))) as $f) {
            if (in_array($f, $cfg['ignore_fields'], true)) {
                continue;
            }

            if (! Support::same($a[$f] ?? null, $b[$f] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * "Newest wins" is meaningless when the writer's clock is far off ours: the conflict is then escalated like one
     * of a sensitive table. Unknown clocks (never measured) are trusted.
     */
    private function clockUntrusted(string $node): bool
    {
        $untrusted = (new ClockMonitor($this->db, $this->opt))->unreliable($node);

        if ($untrusted) {
            try {
                log_message('warning', "sync: the clock of '{$node}' differs by more than {$this->opt->clockMax} s: conflict not decided by time");
            } catch (\Throwable) {
                // logging must never break a sync run
            }
        }

        return $untrusted;
    }

    /**
     * Deterministic: the newer timestamp wins; timestamps closer than `clockTolerance` count as simultaneous, and on
     * a tie the node ranked first in `tiebreak` wins. Both nodes see the same two numbers, so both decide alike.
     */
    public function newer(int $localTs, string $localNode, int $remoteTs, string $remoteNode): string
    {
        if ($localTs !== $remoteTs && abs($localTs - $remoteTs) >= (int) round($this->opt->clockTolerance * 1_000_000)) {
            return $localTs > $remoteTs ? 'local' : 'remote';
        }

        return $this->opt->rank($localNode) <= $this->opt->rank($remoteNode) ? 'local' : 'remote';
    }

    /** @return array<string, mixed>|null */
    private function pendingConflict(string $table, string $uuid): ?array
    {
        return $this->db->table('sync_conflicts')
            ->where(['table_name' => $table, 'uuid' => $uuid, 'status' => 'pending'])
            ->orderBy('id', 'DESC')->get()->getRowArray();
    }

    private function closeConflict(int $id, string $as, string $by): void
    {
        $this->db->table('sync_conflicts')->where('id', $id)->update([
            'status'      => 'resolved',
            'resolved_as' => $as,
            'resolved_by' => $by,
            'resolved_at' => Support::nowUs(),
        ]);
    }
}
