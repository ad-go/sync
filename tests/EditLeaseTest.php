<?php

declare(strict_types=1);

namespace AdGo\Sync\Tests;

use AdGo\Sync\Libraries\AuthLock;
use AdGo\Sync\Libraries\PeerClient;

/** Lock calls forwarded to the other node's AuthLock, plus the journal catch-up used by leases. */
final class LeasePeer implements PeerClient
{
    public bool $down = false;
    public ?AuthLock $other = null;

    /** Runs the remote side of "pull my journal now" (the test wires it to Node::pullFrom). */
    public ?\Closure $onKick = null;

    /** Where the remote node's cursor for us is. */
    public ?\Closure $applied = null;

    public int $kicks = 0;

    private function t(): AuthLock
    {
        if ($this->down || $this->other === null) {
            throw new \RuntimeException('unreachable');
        }

        return $this->other;
    }

    public function lockStatus(string $kind): array { return $this->t()->handleStatus($kind); }
    public function lockAcquire(string $kind, string $requester, bool $confirm, array $meta = []): array { return $this->t()->handleAcquire($kind, $requester, $meta); }
    public function lockHeartbeat(string $kind, string $holder, array $meta = []): array { return $this->t()->handleHeartbeat($kind, $holder, $meta); }
    public function lockRelease(string $kind, string $holder): array { return $this->t()->handleRelease($kind, $holder); }

    public function lockSync(string $from, bool $kick): array
    {
        $this->t();

        if ($kick) {
            $this->kicks++;

            if ($this->onKick !== null) {
                ($this->onKick)();
            }
        }

        return ['applied' => ($this->applied)()];
    }

    public function pull(int $afterSeq, int $limit): array { throw new \LogicException(); }
    public function resolveConflict(string $table, string $uuid, ?array $state, int $newRev, int $expectRev, int $ts, string $by): array { throw new \LogicException(); }
    public function filesManifest(): array { throw new \LogicException(); }
    public function fileGet(string $path): ?string { throw new \LogicException(); }
    public function bootstrapPage(string $table, int $offset, int $limit): array { throw new \LogicException(); }
}

final class EditLeaseTest extends TwoNodes
{
    private AuthLock $la;
    private AuthLock $lb;
    private LeasePeer $pa;
    private LeasePeer $pb;
    private int $catchUpsA = 0;
    private int $catchUpsB = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pa = new LeasePeer();
        $this->pb = new LeasePeer();

        // site1's view of the peer: kicking site2 makes site2 pull site1; "applied" is site2's cursor for site1
        $this->pa->onKick  = fn () => $this->b->pullFrom($this->a);
        $this->pa->applied = fn () => $this->b->k->meta->cursor('site1');
        $this->pb->onKick  = fn () => $this->a->pullFrom($this->b);
        $this->pb->applied = fn () => $this->a->k->meta->cursor('site2');

        $this->la = new AuthLock($this->a->db, $this->a->k->opt, $this->pa, function (): void {
            $this->catchUpsA++;
            $this->a->pullFrom($this->b);
        }, fn () => $this->a->k->scanner()->scan());
        $this->lb = new AuthLock($this->b->db, $this->b->k->opt, $this->pb, function (): void {
            $this->catchUpsB++;
            $this->b->pullFrom($this->a);
        }, fn () => $this->b->k->scanner()->scan());

        $this->pa->other = $this->lb;
        $this->pb->other = $this->la;
    }

    public function testBeginTakesALeaseAndPublishesTheEditingPosition(): void
    {
        $this->assertTrue($this->la->begin('auth', 'users:12', 'admin')['ok']);
        $this->assertTrue($this->la->isHeld());

        $seen = $this->lb->describe();
        $this->assertSame('site1', $seen['holder']);
        $this->assertSame('lease', $seen['mode']);
        $this->assertSame('users:12', $seen['scope']);
        $this->assertSame('admin', $seen['by']);
        $this->assertFalse($this->lb->isHeld(), 'the peer only mirrors it');
        $this->assertSame($this->a->k->opt->leaseTtl, $seen['expires_in']);
    }

    public function testThePeerIsRefusedWithWhoEditsWhat(): void
    {
        $this->la->begin('auth', 'users:12', 'admin');

        $r = $this->lb->begin('auth', 'users:3', 'maria');
        $this->assertFalse($r['ok']);
        $this->assertSame('site1', $r['holder']);
        $this->assertStringContainsString('users:12', $r['reason']);
        $this->assertStringContainsString('admin', $r['reason']);
        $this->assertFalse($this->lb->isHeld());
    }

    public function testEditActivityRefreshesTheLeaseAndTheClockDoesNot(): void
    {
        $ttl = $this->a->k->opt->leaseTtl;
        $this->la->begin('auth', 'users:12', 'admin');

        $this->tick(($ttl - 60) * 1_000_000);
        $this->assertFalse($this->la->renewManual()['ok'], 'the cron heartbeat must not keep a lease alive');
        $this->la->begin('auth', 'users:12', 'admin'); // the editor is still working
        $this->tick(($ttl - 60) * 1_000_000);
        $this->assertTrue($this->la->isHeld(), 'refreshed by activity');
        $this->assertSame('site1', $this->lb->holder());

        $this->tick(120 * 1_000_000); // now idle for longer than leaseTtl
        $this->assertFalse($this->la->isHeld());
        $this->assertNull($this->lb->holder(), 'an abandoned edit frees itself on the peer too');
        $this->assertTrue($this->lb->begin('auth', 'users:3', 'maria')['ok']);
    }

    public function testManualLocksKeepTheirHeartbeatAndAreNotConvertedToLeases(): void
    {
        $this->la->acquire();
        $this->assertSame('manual', $this->lb->describe()['mode']);

        $this->la->begin('auth', 'users:12', 'admin');
        $this->assertSame('manual', $this->la->describe()['mode'], 'a human-taken lock stays as it is');

        $this->tick(($this->a->k->opt->lockTtl - 10) * 1_000_000);
        $this->assertTrue($this->la->renewManual()['ok']);
        $this->tick(($this->a->k->opt->lockTtl - 10) * 1_000_000);
        $this->assertTrue($this->la->isHeld());
    }

    public function testHandoverReleasesOnlyAfterThePeerAppliedOurChanges(): void
    {
        $this->la->begin('auth', 'users:12', 'admin');
        $this->a->insert(['name' => 'edited-by-admin']); // the editor's save, journaled on site1

        $r = $this->la->handover('auth', 2);
        $this->assertTrue($r['ok']);
        $this->assertSame(1, $this->pa->kicks, 'the peer was told to pull');
        $this->assertCount(1, $this->b->all(), 'the peer has the change before the lock is gone');
        $this->assertNull($this->la->holder());
        $this->assertNull($this->lb->holder());
    }

    public function testHandoverKeepsTheLeaseWhenThePeerCannotConfirm(): void
    {
        $this->la->begin('auth', 'users:12', 'admin');
        $this->a->insert(['name' => 'edited-by-admin']);
        $this->pa->down = true;

        $r = $this->la->handover('auth', 1);
        $this->assertFalse($r['ok']);
        $this->assertTrue($this->la->isHeld(), 'never hand over data the peer has not got');
    }

    public function testHandoverTimesOutWhenThePeerKeepsLaggingBehind(): void
    {
        $this->la->begin('auth', 'users:12', 'admin');
        $this->a->insert(['name' => 'x']);
        $this->pa->onKick = null; // the peer never pulls

        $r = $this->la->handover('auth', 1);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('not applied', $r['reason']);
        $this->assertTrue($this->la->isHeld());
    }

    public function testTheNextEditorCatchesUpBeforeItIsAllowedToEdit(): void
    {
        $this->la->begin('auth', 'users:12', 'admin');
        $this->a->insert(['name' => 'written-while-leased']);
        $this->tick(($this->a->k->opt->leaseTtl + 5) * 1_000_000); // site1's editor vanished without a handover

        $this->assertCount(0, $this->b->all());
        $this->assertTrue($this->lb->begin('auth', 'users:3', 'maria')['ok']);
        $this->assertSame(1, $this->catchUpsB);
        $this->assertCount(1, $this->b->all(), 'site2 pulled site1\'s journal BEFORE it got the lock');
    }

    public function testFailedCatchUpDeniesTheLeaseAndLeavesNothingHeld(): void
    {
        $lb = new AuthLock($this->b->db, $this->b->k->opt, $this->pb, static function (): void {
            throw new \RuntimeException('journal unreadable');
        });
        $this->pa->other = $lb;

        $r = $lb->begin('auth', 'users:3', 'maria');
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('catch up', $r['reason']);
        $this->assertNull($lb->holder());
        $this->assertNull($this->la->holder());
    }

    public function testPeerDownOnlyThePrimaryNodeLeasesAutomatically(): void
    {
        $this->pa->down = true;
        $this->pb->down = true;

        $this->assertTrue($this->la->begin('auth', 'users:1', 'admin')['ok'], 'site1 is ranked first');
        $r = $this->lb->begin('auth', 'users:2', 'maria');
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('confirm', $r['reason']);
        $this->assertTrue($this->lb->begin('auth', 'users:2', 'maria', true)['ok'], 'an explicit human confirmation takes it');
    }
}
