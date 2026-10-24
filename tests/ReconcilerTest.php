<?php

declare(strict_types=1);

namespace AdGo\Sync\Tests;

use AdGo\Sync\Libraries\Reconciler;
use AdGo\Sync\Libraries\Support;

final class ReconcilerTest extends TwoNodes
{
    public function testInsertOnOneNodeAppearsOnTheOther(): void
    {
        $id = $this->a->insert(['name' => 'apple', 'price' => 10, 'note' => 'x']);
        $this->sync();

        $bid = $this->b->idByUuid($this->a->uuidOf($id));
        $this->assertNotNull($bid);
        $this->assertSame('apple', $this->b->row($bid)['name']);
        $this->assertSame([], $this->a->conflicts());
        $this->assertSame([], $this->b->conflicts());
    }

    public function testNoConflictFastForwardUpdate(): void
    {
        $id = $this->a->insert(['name' => 'apple', 'price' => 10]);
        $this->sync();

        $this->tick();
        $this->a->update($id, ['price' => 11]);
        $this->sync();

        $bid = $this->b->idByUuid($this->a->uuidOf($id));
        $this->assertSame('11', (string) $this->b->row($bid)['price']);
        $this->assertSame([], $this->b->conflicts());
    }

    public function testIdempotentApply(): void
    {
        $id = $this->a->insert(['name' => 'apple', 'price' => 10]);
        $this->b->pullFrom($this->a);
        $before = $this->b->all();

        // Replay the very same journal from the start.
        $rec = $this->b->k->reconciler();

        foreach ($this->a->k->meta->changesSince(0, 100) as $change) {
            $this->assertSame(Reconciler::SKIPPED, $rec->apply($change)['outcome']);
        }

        $this->assertSame($before, $this->b->all());
    }

    public function testDisjointFieldsAreMergedAutomaticallyOnBothNodes(): void
    {
        $id = $this->a->insert(['name' => 'apple', 'price' => 10, 'note' => 'n0']);
        $this->sync();
        $uuid = $this->a->uuidOf($id);
        $bid  = $this->b->idByUuid($uuid);

        $this->tick();
        $this->a->update($id, ['name' => 'apple-A']);
        $this->tick();
        $this->b->update($bid, ['note' => 'note-B']);
        $this->sync();

        foreach ([[$this->a, $id], [$this->b, $bid]] as [$n, $rid]) {
            $r = $n->row($rid);
            $this->assertSame('apple-A', $r['name']);
            $this->assertSame('note-B', $r['note']);
            $this->assertSame('10', (string) $r['price']);
        }

        $this->assertSame([], $this->a->conflicts('pending'));
        $this->assertSame([], $this->b->escalated);
        // Both versions were still logged (nothing lost silently).
        $this->assertCount(1, $this->a->conflicts('resolved'));
        $this->assertSame('auto-merge', $this->a->conflicts('resolved')[0]['resolved_as']);
        $this->assertSame('safe', $this->a->conflicts('resolved')[0]['severity']);
    }

    public function testTolerantTableResolvesSameFieldByLastWriterWinsIdenticallyOnBothNodes(): void
    {
        $id = $this->a->insert(['name' => 'apple', 'note' => 'n0']);
        $this->sync();
        $bid = $this->b->idByUuid($this->a->uuidOf($id));

        $this->tick();
        $this->a->update($id, ['note' => 'from-A']);
        $this->tick(5000);
        $this->b->update($bid, ['note' => 'from-B']); // later => wins

        $this->sync();

        $this->assertSame('from-B', $this->a->row($id)['note']);
        $this->assertSame('from-B', $this->b->row($bid)['note']);
        $this->assertSame([], $this->a->conflicts('pending'));
    }

    public function testTieBreakerIsDeterministic(): void
    {
        $id = $this->a->insert(['name' => 'apple', 'note' => 'n0']);
        $this->sync();
        $bid = $this->b->idByUuid($this->a->uuidOf($id));

        $this->tick();
        $this->a->update($id, ['note' => 'from-A']);
        $this->b->update($bid, ['note' => 'from-B']); // same microsecond

        $this->sync();

        // site1 is first in tiebreak => its value wins on both nodes
        $this->assertSame('from-A', $this->a->row($id)['note']);
        $this->assertSame('from-A', $this->b->row($bid)['note']);
    }

    public function testSensitiveFieldOverlapIsQuarantinedAndNothingIsApplied(): void
    {
        $id = $this->a->insert(['name' => 'apple', 'price' => 10]);
        $this->sync();
        $bid = $this->b->idByUuid($this->a->uuidOf($id));

        $this->tick();
        $this->a->update($id, ['price' => 20]);
        $this->tick();
        $this->b->update($bid, ['price' => 30]);

        $this->sync();

        $this->assertSame('20', (string) $this->a->row($id)['price'], 'local value must stay untouched');
        $this->assertSame('30', (string) $this->b->row($bid)['price']);

        $pending = $this->a->conflicts('pending');
        $this->assertCount(1, $pending);
        $this->assertSame('dangerous', $pending[0]['severity']);
        $this->assertSame('price', $pending[0]['fields']);
        $this->assertCount(1, $this->a->escalated);
        $this->assertCount(1, $this->b->conflicts('pending'));
    }

    public function testSensitiveTablePolicyEscalatesAnyOverlap(): void
    {
        $this->tearDown();
        $strict = fn (): array => ['items' => ['policy' => 'sensitive']];
        Support::freezeNow(1_000_000);
        $a = new Node('site1', 'site2', $strict());
        $b = new Node('site2', 'site1', $strict());

        $id = $a->insert(['name' => 'apple', 'note' => 'n0']);
        $b->pullFrom($a);
        $bid = $b->idByUuid($a->uuidOf($id));

        $this->tick();
        $a->update($id, ['note' => 'A']);
        $b->update($bid, ['note' => 'B']);
        $b->pullFrom($a);

        $this->assertCount(1, $b->conflicts('pending'));
        $this->assertSame('B', $b->row($bid)['note']);
    }

    public function testFurtherRemoteChangesWhilePendingOnlyRefreshTheQuarantine(): void
    {
        $id = $this->a->insert(['name' => 'apple', 'price' => 10]);
        $this->sync();
        $bid = $this->b->idByUuid($this->a->uuidOf($id));

        $this->tick();
        $this->a->update($id, ['price' => 20]);
        $this->b->update($bid, ['price' => 30]);
        $this->a->pullFrom($this->b);

        $this->tick();
        $this->b->update($bid, ['price' => 31]);
        $res = $this->a->pullFrom($this->b);

        $this->assertSame(Reconciler::PENDING, $res[0]['outcome']);
        $pending = $this->a->conflicts('pending');
        $this->assertCount(1, $pending);
        $this->assertSame('31', (string) \AdGo\Sync\Libraries\Support::unpack($pending[0]['remote_blob'])['price']);
    }

    public function testSoftDeletePropagatesAsTombstone(): void
    {
        $id = $this->a->insert(['name' => 'apple']);
        $this->sync();
        $uuid = $this->a->uuidOf($id);
        $bid  = $this->b->idByUuid($uuid);

        $this->tick();
        $this->a->delete($id);
        $this->sync();

        $this->assertNull($this->b->row($bid));
        $meta = $this->b->k->meta->findByUuid('items', $uuid);
        $this->assertNotNull($meta, 'tombstone kept in meta');
        $this->assertNotNull($meta['deleted_at']);
        $this->assertNull($meta['row_id']);
    }

    public function testDeleteVersusUpdateOnTolerantTableFollowsLastWriter(): void
    {
        $id = $this->a->insert(['name' => 'apple', 'note' => 'n0']);
        $this->sync();
        $bid = $this->b->idByUuid($this->a->uuidOf($id));

        $this->tick();
        $this->a->update($id, ['note' => 'edited']);
        $this->tick(5000);
        $this->b->delete($bid); // later => the delete wins

        $this->sync();

        $this->assertNull($this->a->row($id));
        $this->assertNull($this->b->row($bid));
    }

    public function testDeleteVersusUpdateOnSensitiveTableIsQuarantined(): void
    {
        $this->tearDown();
        Support::freezeNow(1_000_000);
        $strict = fn (): array => ['items' => ['policy' => 'sensitive']];
        $a      = new Node('site1', 'site2', $strict());
        $b      = new Node('site2', 'site1', $strict());

        $id = $a->insert(['name' => 'apple', 'note' => 'n0']);
        $b->pullFrom($a);
        $bid = $b->idByUuid($a->uuidOf($id));

        $this->tick();
        $a->update($id, ['note' => 'edited']);
        $b->delete($bid);
        $a->pullFrom($b);

        $this->assertCount(1, $a->conflicts('pending'));
        $this->assertSame('edited', $a->row($id)['note']);
    }

    public function testConvergedWhenBothSidesMadeTheSameChange(): void
    {
        $id = $this->a->insert(['name' => 'apple', 'price' => 10]);
        $this->sync();
        $bid = $this->b->idByUuid($this->a->uuidOf($id));

        $this->tick();
        $this->a->update($id, ['price' => 15]);
        $this->b->update($bid, ['price' => 15]);
        $res = $this->a->pullFrom($this->b);

        $this->assertSame(Reconciler::CONVERGED, $res[0]['outcome']);
        $this->assertSame([], $this->a->conflicts());
    }

    public function testSeededRowsShareUuidsAndSyncWithoutTraffic(): void
    {
        $this->tearDown();
        Support::freezeNow(1_000_000);
        $a = new Node('site1', 'site2', $this->tables());
        $b = new Node('site2', 'site1', $this->tables());
        $a->db->table('items')->insert(['id' => 1, 'name' => 'pre']);
        $b->db->table('items')->insert(['id' => 7, 'name' => 'pre']);
        $a->k->scanner()->seed();
        $b->k->scanner()->seed();

        // ids differ between nodes only if the data was loaded independently; uuid is derived from the pk,
        // so seeding requires the same pks. Use the same pk here:
        $this->assertNotNull($a->k->meta->findByRow('items', 1));
        $this->assertSame(
            $a->k->meta->findByRow('items', 1)['uuid'],
            \AdGo\Sync\Libraries\Support::seedUuid('items', 1),
        );
        $this->assertSame([], $a->k->meta->changesSince(0, 10), 'seeding journals nothing');
    }
}
