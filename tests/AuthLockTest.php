<?php

declare(strict_types=1);

namespace AdGo\Sync\Tests;

use AdGo\Sync\Libraries\AuthLock;
use AdGo\Sync\Libraries\PeerClient;

/** Lock calls forwarded straight to the other node's AuthLock. */
final class LockPeer implements PeerClient
{
    public bool $down = false;
    public ?AuthLock $other = null;

    public function __construct(private readonly string $origin)
    {
    }

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
    public function lockSync(string $from, bool $kick): array { throw new \LogicException(); }
    public function lockRelease(string $kind, string $holder): array { return $this->t()->handleRelease($kind, $holder); }
    public function pull(int $afterSeq, int $limit): array { throw new \LogicException(); }
    public function resolveConflict(string $table, string $uuid, ?array $state, int $newRev, int $expectRev, int $ts, string $by): array { throw new \LogicException(); }
    public function filesManifest(): array { throw new \LogicException(); }
    public function fileGet(string $path): ?string { throw new \LogicException(); }
    public function bootstrapPage(string $table, int $offset, int $limit): array { throw new \LogicException(); }
}

final class AuthLockTest extends TwoNodes
{
    private AuthLock $la;
    private AuthLock $lb;
    private LockPeer $pa;
    private LockPeer $pb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pa = new LockPeer('site1');
        $this->pb = new LockPeer('site2');
        $this->la = new AuthLock($this->a->db, $this->a->k->opt, $this->pa);
        $this->lb = new AuthLock($this->b->db, $this->b->k->opt, $this->pb);
        $this->pa->other = $this->lb;
        $this->pb->other = $this->la;
    }

    public function testOnlyOneHolderAtATime(): void
    {
        $this->assertTrue($this->la->acquire()['ok']);
        $this->assertTrue($this->la->isHeld());
        $this->assertFalse($this->lb->isHeld());
        $this->assertSame('site1', $this->lb->holder(), 'peer mirrors the holder');

        $r = $this->lb->acquire();
        $this->assertFalse($r['ok']);
        $this->assertSame('site1', $r['holder']);
        $this->assertFalse($this->lb->isHeld());
    }

    public function testHeartbeatKeepsTheLockAliveBeyondTheTtl(): void
    {
        $this->la->acquire();
        $ttl = $this->a->k->opt->lockTtl;

        $this->tick(($ttl - 10) * 1_000_000);
        $this->assertTrue($this->la->heartbeat()['ok']);
        $this->tick(($ttl - 10) * 1_000_000);

        $this->assertTrue($this->la->isHeld());
        $this->assertSame('site1', $this->lb->holder());
    }

    public function testExpiredLockCanBeTakenOverByTheSurvivor(): void
    {
        $this->la->acquire();
        $this->pb->down = true; // site1 died; site2 cannot reach it
        $this->tick(($this->a->k->opt->lockTtl + 5) * 1_000_000);

        $this->assertNull($this->lb->holder());

        $auto = $this->lb->acquire();
        $this->assertFalse($auto['ok'], 'site2 must not take over automatically when the peer is unreachable');

        $confirmed = $this->lb->acquire('auth', true);
        $this->assertTrue($confirmed['ok']);
        $this->assertTrue($this->lb->isHeld());
    }

    public function testPrimaryNodeTakesAnAbandonedLockAutomatically(): void
    {
        $this->lb->acquire();
        $this->pa->down = true;
        $this->tick(($this->a->k->opt->lockTtl + 5) * 1_000_000);

        $this->assertTrue($this->la->acquire()['ok']);
        $this->assertTrue($this->la->isHeld());
    }

    public function testSimultaneousClaimIsWonByTheFirstRankedNode(): void
    {
        // site2 recorded its claim, then site1 claims at the same moment
        $this->pb->down = true;
        $this->lb->acquire('auth', true);   // site2 holds locally (peer unreachable)
        $this->pb->down = false;
        $this->la->handleAcquire('auth', 'site2');
        $r = $this->la->acquire(); // site1 sees a live site2 holder => refuses

        $this->assertFalse($r['ok']);
    }

    public function testReleaseFreesTheLockOnBothNodes(): void
    {
        $this->la->acquire();
        $this->la->release();

        $this->assertNull($this->la->holder());
        $this->assertNull($this->lb->holder());
        $this->assertTrue($this->lb->acquire()['ok']);
    }

    public function testCronAndAuthLocksAreIndependent(): void
    {
        $this->la->acquire('auth');
        $this->assertTrue($this->lb->acquire('cron')['ok']);
        $this->assertTrue($this->la->isHeld('auth'));
        $this->assertTrue($this->lb->isHeld('cron'));
    }
}
