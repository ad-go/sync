<?php

declare(strict_types=1);

namespace AdGo\Sync\Tests;

use AdGo\Sync\Libraries\PeerClient;
use AdGo\Sync\Libraries\Reconciler;

/** Peer that forwards resolve calls straight to the other node (no HTTP). */
final class DirectPeer implements PeerClient
{
    public bool $down = false;

    public function __construct(private readonly Node $target, private readonly string $origin)
    {
    }

    public function resolveConflict(string $table, string $uuid, ?array $state, int $newRev, int $expectRev, int $ts, string $by): array
    {
        if ($this->down) {
            throw new \RuntimeException('peer unreachable');
        }

        return $this->target->resolver()->applyPropagated($table, $uuid, $state, $newRev, $expectRev, $ts, $by, $this->origin);
    }

    public function pull(int $afterSeq, int $limit): array { throw new \LogicException(); }
    public function lockStatus(string $kind): array { throw new \LogicException(); }
    public function lockAcquire(string $kind, string $requester, bool $confirm, array $meta = []): array { throw new \LogicException(); }
    public function lockHeartbeat(string $kind, string $holder, array $meta = []): array { throw new \LogicException(); }
    public function lockSync(string $from, bool $kick): array { throw new \LogicException(); }
    public function lockRelease(string $kind, string $holder): array { throw new \LogicException(); }
    public function filesManifest(): array { throw new \LogicException(); }
    public function fileGet(string $path): ?string { throw new \LogicException(); }
    public function bootstrapPage(string $table, int $offset, int $limit): array { throw new \LogicException(); }
}

final class ConflictResolverTest extends TwoNodes
{
    /** @return array{0:int,1:int,2:string} */
    private function priceConflict(): array
    {
        $id = $this->a->insert(['name' => 'apple', 'price' => 10]);
        $this->sync();
        $uuid = $this->a->uuidOf($id);
        $bid  = $this->b->idByUuid($uuid);

        $this->tick();
        $this->a->update($id, ['price' => 20]);
        $this->tick();
        $this->b->update($bid, ['price' => 30]);
        $this->sync();

        return [$id, $bid, $uuid];
    }

    public function testKeepOneSideAppliesOnBothNodesAndBumpsRevAboveBoth(): void
    {
        [$id, $bid] = $this->priceConflict();
        $ca = $this->a->conflicts('pending')[0];
        $cb = $this->b->conflicts('pending')[0];

        $peer = new DirectPeer($this->b, 'site1');
        $r    = $this->a->resolver($peer)->resolve((int) $ca['id'], 'site2', null, 'admin');

        $this->assertTrue($r['ok']);
        $this->assertTrue($r['propagated']);
        $this->assertSame('30', (string) $this->a->row($id)['price']);
        $this->assertSame('30', (string) $this->b->row($bid)['price']);
        $this->assertSame([], $this->a->conflicts('pending'));
        $this->assertSame([], $this->b->conflicts('pending'));

        $ma = $this->a->k->meta->findByRow('items', $id);
        $mb = $this->b->k->meta->findByRow('items', $bid);
        $this->assertGreaterThan(max((int) $ca['local_rev'], (int) $ca['remote_rev']), (int) $ma['rev']);
        $this->assertSame((int) $ma['rev'], (int) $mb['rev']);
        $this->assertSame((int) $ma['rev'], (int) $mb['peer_rev']);
        $this->assertSame('resolved', $this->b->conflicts()[0]['status'] ?? 'resolved');

        // Re-syncing must not resurrect the conflict.
        $this->sync();
        $this->assertSame([], $this->a->conflicts('pending'));
        $this->assertSame([], $this->b->conflicts('pending'));
        $this->assertSame('30', (string) $this->a->row($id)['price']);
        $this->assertNotNull($cb);
    }

    public function testResolutionIsIdempotent(): void
    {
        [$id] = $this->priceConflict();
        $ca   = $this->a->conflicts('pending')[0];
        $res  = $this->a->resolver(new DirectPeer($this->b, 'site1'));

        $res->resolve((int) $ca['id'], 'site1', null, 'admin');
        $rev = (int) $this->a->k->meta->findByRow('items', $id)['rev'];
        $res->resolve((int) $ca['id'], 'site1', null, 'admin');

        $this->assertSame($rev, (int) $this->a->k->meta->findByRow('items', $id)['rev']);
        $this->assertSame('20', (string) $this->a->row($id)['price']);
    }

    public function testManualMerge(): void
    {
        [$id, $bid] = $this->priceConflict();
        $ca         = $this->a->conflicts('pending')[0];
        $merged     = ['name' => 'apple', 'note' => null, 'price' => 25];

        $this->a->resolver(new DirectPeer($this->b, 'site1'))->resolve((int) $ca['id'], 'merge', $merged, 'admin');

        $this->assertSame('25', (string) $this->a->row($id)['price']);
        $this->assertSame('25', (string) $this->b->row($bid)['price']);
    }

    public function testWhenPeerIsDownTheAuthoritativeJournalEntryCarriesTheDecision(): void
    {
        [$id, $bid] = $this->priceConflict();
        $ca         = $this->a->conflicts('pending')[0];
        $peer       = new DirectPeer($this->b, 'site1');
        $peer->down = true;

        $r = $this->a->resolver($peer)->resolve((int) $ca['id'], 'site1', null, 'admin');
        $this->assertFalse($r['propagated']);

        // B still has its own pending conflict until it pulls A's journal.
        $this->assertCount(1, $this->b->conflicts('pending'));
        $this->b->pullFrom($this->a);

        $this->assertSame('20', (string) $this->b->row($bid)['price']);
        $this->assertSame([], $this->b->conflicts('pending'));
        $this->sync();
        $this->assertSame([], $this->a->conflicts('pending'));
    }

    public function testStalePropagationIsRefused(): void
    {
        [$id, $bid] = $this->priceConflict();
        $ca         = $this->a->conflicts('pending')[0];

        $this->tick();
        $this->b->update($bid, ['price' => 99]); // B moves on after the conflict was detected

        $r = $this->a->resolver(new DirectPeer($this->b, 'site1'))->resolve((int) $ca['id'], 'site1', null, 'admin');

        $this->assertFalse($r['propagated']);
        $this->assertSame('99', (string) $this->b->row($bid)['price'], 'B keeps its newer edit');
    }

    public function testIgnoreStopsTheConflictFromReturning(): void
    {
        [$id, $bid] = $this->priceConflict();
        $ca         = $this->a->conflicts('pending')[0];

        $this->a->resolver()->resolve((int) $ca['id'], 'ignore', null, 'admin');
        $res = $this->a->pullFrom($this->b);

        $this->assertSame([], $this->a->conflicts('pending'));
        $this->assertSame('20', (string) $this->a->row($id)['price']);
        $this->assertContains(Reconciler::SKIPPED, array_column($res, 'outcome') ?: [Reconciler::SKIPPED]);
    }
}
