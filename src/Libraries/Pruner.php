<?php

declare(strict_types=1);

namespace AdGo\Sync\Libraries;

use CodeIgniter\Database\BaseConnection;
use Throwable;

/**
 * Keeps the package tables from growing for ever.
 *
 *  - `sync_changes` (journal): an entry may go once it is older than `keepJournalDays` AND the peer has applied it
 *    (the peer reports how far it got). The newest entry always stays: it keeps the sequence numbers moving
 *    forward even on databases that reuse auto-increment values after the top rows were deleted.
 *  - `sync_conflicts`: decided conflicts older than `keepConflictDays`; pending ones are never touched.
 *
 * If the peer cannot be asked nothing is deleted.
 */
class Pruner
{
    private const DAY_US = 86_400_000_000;

    public function __construct(
        private readonly BaseConnection $db,
        private readonly Options $opt,
        private readonly ?PeerClient $peer = null,
        /** @var array<string, PeerClient> name => client of every reachable peer */
        private readonly array $peers = [],
    ) {
    }

    /**
     * @return array{journal: int, conflicts: int, skipped: ?string}
     */
    public function run(): array
    {
        $now = Support::nowUs();
        $out = ['journal' => 0, 'conflicts' => 0, 'skipped' => null];

        // every reachable peer must have applied an entry before it goes; a passive peer only pulls us, so for it
        // the age limit (keepJournalDays) is the only guard
        $applied = PHP_INT_MAX;

        foreach ($this->peers === [] ? ['' => $this->peer] : $this->peers as $name => $client) {
            try {
                $applied = min($applied, (int) ($client->lockSync($this->opt->node, false)['applied'] ?? 0));
            } catch (Throwable $e) {
                $out['skipped'] = ($name !== '' ? $name . ': ' : '') . 'peer unreachable: ' . $e->getMessage();
                $applied        = 0;
            }
        }

        if ($applied === PHP_INT_MAX) {
            $applied = 0;
        }

        $newest = (int) ($this->db->table('sync_changes')->selectMax('seq')->get()->getRowArray()['seq'] ?? 0);
        $upTo   = min($applied, $newest - 1);

        if ($upTo > 0) {
            $this->db->table('sync_changes')->where('seq <=', $upTo)->where('changed_at <', $now - $this->opt->keepJournalDays * self::DAY_US)->delete();
            $out['journal'] = $this->db->affectedRows();
        }

        $this->db->table('sync_conflicts')->where('status !=', 'pending')->where('detected_at <', $now - $this->opt->keepConflictDays * self::DAY_US)->delete();
        $out['conflicts'] = $this->db->affectedRows();

        if ($this->db->tableExists('sync_nonce')) {
            (new NonceStore($this->db, $this->opt->replayWindow * 2))->purge();
        }

        return $out;
    }
}
