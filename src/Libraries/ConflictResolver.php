<?php

declare(strict_types=1);

namespace AdGo\Sync\Libraries;

use CodeIgniter\Database\BaseConnection;
use Throwable;

/**
 * Applies a human decision (admin UI or Telegram) to a quarantined conflict
 * on BOTH nodes, idempotently, and bumps the revision above both versions so
 * the next sync does not resurrect the conflict.
 */
class ConflictResolver
{
    public function __construct(
        private readonly BaseConnection $db,
        private readonly Options $opt,
        private readonly MetaStore $meta,
        private readonly Reconciler $reconciler,
        private readonly ?PeerClient $peer = null,
    ) {
    }

    /**
     * @param string                    $choice 'ignore', 'merge', or the NAME of the node whose version to keep
     * @param array<string, mixed>|null $merged field => value, required for 'merge'
     *
     * @return array{ok: bool, error?: string, propagated?: bool}
     */
    public function resolve(int $conflictId, string $choice, ?array $merged, string $by): array
    {
        $c = $this->db->table('sync_conflicts')->where('id', $conflictId)->get()->getRowArray();

        if ($c === null) {
            return ['ok' => false, 'error' => Lang::line('resolver.unknownConflict')];
        }

        if ($c['status'] !== 'pending') {
            return ['ok' => true, 'propagated' => true]; // idempotent: already decided
        }

        $table = (string) $c['table_name'];
        $uuid  = (string) $c['uuid'];
        $meta  = $this->meta->findByUuid($table, $uuid);
        $now   = Support::nowUs();

        if ($choice === 'ignore') {
            $this->db->table('sync_conflicts')->where('id', $conflictId)->update([
                'status' => 'ignored', 'resolved_as' => 'ignore', 'resolved_by' => $by, 'resolved_at' => $now,
            ]);

            // Accept the divergence: stop treating the remote version as new.
            if ($meta !== null) {
                $this->meta->saveState($table, $uuid, ['peer_rev' => (int) $c['remote_rev']]);
            }

            return ['ok' => true];
        }

        if ($meta !== null && (int) $meta['rev'] !== (int) $c['local_rev']) {
            return ['ok' => false, 'error' => Lang::line('resolver.rowChanged')];
        }

        $state = match (true) {
            $choice === $c['local_node']  => Support::unpack($c['local_blob']),
            $choice === $c['remote_node'] => Support::unpack($c['remote_blob']),
            $choice === 'merge'           => $merged,
            default                       => false,
        };

        if ($state === false || ($choice === 'merge' && $merged === null)) {
            return ['ok' => false, 'error' => Lang::line('resolver.invalidChoice')];
        }

        if (is_array($state)) {
            ksort($state);
        }

        $newRev = max((int) ($meta['rev'] ?? 0), (int) $c['remote_rev']) + 1;

        Support::tx($this->db, function () use ($table, $uuid, $state, $newRev, $now, $conflictId, $choice, $by) {
            $this->reconciler->adopt($table, $uuid, $state, $newRev, $now, $this->opt->node, true);
            $this->db->table('sync_conflicts')->where('id', $conflictId)->update([
                'status' => 'resolved', 'resolved_as' => $choice, 'resolved_by' => $by, 'resolved_at' => $now,
            ]);
        });

        $propagated = false;

        if ($this->peer !== null) {
            try {
                $r          = $this->peer->resolveConflict($table, $uuid, $state, $newRev, (int) $c['remote_rev'], $now, $by);
                $propagated = (bool) ($r['ok'] ?? false);
            } catch (Throwable) {
                $propagated = false; // the authoritative journal entry will carry it
            }
        }

        return ['ok' => true, 'propagated' => $propagated];
    }

    /**
     * Peer side of resolve(): same decision, applied here. Refuses if this
     * node touched the row after the conflict was detected.
     *
     * @param array<string, mixed>|null $state
     *
     * @return array{ok: bool, error?: string}
     */
    public function applyPropagated(string $table, string $uuid, ?array $state, int $newRev, int $expectRev, int $ts, string $by, string $originNode): array
    {
        if ($this->opt->table($table) === null) {
            return ['ok' => false, 'error' => 'unknown table'];
        }

        $meta = $this->meta->findByUuid($table, $uuid);

        if ($meta !== null && (int) $meta['rev'] !== $expectRev) {
            return ['ok' => false, 'error' => 'stale'];
        }

        Support::tx($this->db, function () use ($table, $uuid, $state, $newRev, $ts, $by, $originNode) {
            $this->reconciler->adopt($table, $uuid, $state, $newRev, $ts, $originNode, false);
            $this->db->table('sync_conflicts')
                ->where(['table_name' => $table, 'uuid' => $uuid, 'status' => 'pending'])
                ->update(['status' => 'resolved', 'resolved_as' => 'peer-decision', 'resolved_by' => $by, 'resolved_at' => Support::nowUs()]);
        });

        return ['ok' => true];
    }
}
