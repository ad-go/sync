<?php

declare(strict_types=1);

namespace AdGo\Sync\Tests;

use AdGo\Sync\Libraries\PeerClient;
use AdGo\Sync\Libraries\Pruner;
use AdGo\Sync\Libraries\Support;

/** A peer that only answers "how far have you applied my journal?". */
final class PrunePeer implements PeerClient
{
    public int $applied = 0;
    public bool $down   = false;

    public function lockSync(string $from, bool $kick): array
    {
        if ($this->down) {
            throw new \RuntimeException('down');
        }

        return ['applied' => $this->applied];
    }

    public function pull(int $afterSeq, int $limit): array { throw new \LogicException(); }
    public function resolveConflict(string $table, string $uuid, ?array $state, int $newRev, int $expectRev, int $ts, string $by): array { throw new \LogicException(); }
    public function lockStatus(string $kind): array { throw new \LogicException(); }
    public function lockAcquire(string $kind, string $requester, bool $confirm, array $meta = []): array { throw new \LogicException(); }
    public function lockHeartbeat(string $kind, string $holder, array $meta = []): array { throw new \LogicException(); }
    public function lockRelease(string $kind, string $holder): array { throw new \LogicException(); }
    public function filesManifest(): array { throw new \LogicException(); }
    public function fileGet(string $path): ?string { throw new \LogicException(); }
    public function bootstrapPage(string $table, int $offset, int $limit): array { throw new \LogicException(); }
}

final class PrunerTest extends TwoNodes
{
    private const DAY_US = 86_400_000_000;

    private PrunePeer $peer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->peer = new PrunePeer();
    }

    private function pruner(): Pruner
    {
        return new Pruner($this->a->db, $this->a->k->opt, $this->peer);
    }

    private function journalSeqs(): array
    {
        return array_map('intval', array_column($this->a->db->table('sync_changes')->select('seq')->orderBy('seq')->get()->getResultArray(), 'seq'));
    }

    public function testOldAppliedEntriesGoButTheNewestAndUnappliedStay(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->a->insert(['name' => "n{$i}"]);
        }

        $this->tick(40 * self::DAY_US); // everything is now 40 days old (retention is 30)
        $this->peer->applied = 3; // the peer has applied seq 1..3

        $r = $this->pruner()->run();
        $this->assertSame(3, $r['journal']);
        $this->assertSame([4, 5], $this->journalSeqs());
    }

    public function testRecentEntriesStayEvenIfApplied(): void
    {
        $this->a->insert(['name' => 'a']);
        $this->a->insert(['name' => 'b']);
        $this->tick(5 * self::DAY_US);
        $this->peer->applied = 2;

        $this->assertSame(0, $this->pruner()->run()['journal']);
        $this->assertSame([1, 2], $this->journalSeqs());
    }

    public function testTheNewestEntryAlwaysStaysSoSequenceNumbersNeverRestart(): void
    {
        $this->a->insert(['name' => 'a']);
        $this->a->insert(['name' => 'b']);
        $this->tick(60 * self::DAY_US);
        $this->peer->applied = 2; // peer applied everything

        $this->pruner()->run();
        $this->assertSame([2], $this->journalSeqs());
    }

    public function testNothingIsDeletedWhenThePeerCannotBeAsked(): void
    {
        $this->a->insert(['name' => 'a']);
        $this->a->insert(['name' => 'b']);
        $this->tick(60 * self::DAY_US);

        $this->peer->down = true;
        $r                = $this->pruner()->run();

        $this->assertSame(0, $r['journal']);
        $this->assertStringContainsString('unreachable', (string) $r['skipped']);
        $this->assertSame([1, 2], $this->journalSeqs());
    }

    public function testDecidedConflictsExpireAndPendingOnesNever(): void
    {
        $now = Support::nowUs();
        $row = ['table_name' => 'items', 'uuid' => 'u', 'local_node' => 'site1', 'remote_node' => 'site2', 'severity' => 'safe'];
        $this->a->db->table('sync_conflicts')->insertBatch([
            $row + ['status' => 'resolved', 'detected_at' => $now - 100 * self::DAY_US],
            $row + ['status' => 'resolved', 'detected_at' => $now - 10 * self::DAY_US],
            $row + ['status' => 'pending', 'detected_at' => $now - 400 * self::DAY_US],
        ]);

        $this->assertSame(1, $this->pruner()->run()['conflicts']);
        $this->assertSame(2, $this->a->db->table('sync_conflicts')->countAllResults());
        $this->assertSame(1, $this->a->db->table('sync_conflicts')->where('status', 'pending')->countAllResults());
    }
}
