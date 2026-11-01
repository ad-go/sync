<?php

declare(strict_types=1);

namespace AdGo\Sync\Tests;

use CodeIgniter\Model;
use AdGo\Sync\Libraries\Kernel;
use AdGo\Sync\Libraries\PeerClient;
use AdGo\Sync\Traits\Syncable;

/** A peer that only records (or fails) the "pull my journal now" notification. */
final class KickPeer implements PeerClient
{
    /** @var list<array{string, bool}> */
    public array $kicks = [];
    public bool $fail   = false;

    public function lockSync(string $from, bool $kick): array
    {
        if ($this->fail) {
            throw new \RuntimeException('down');
        }

        $this->kicks[] = [$from, $kick];

        return ['applied' => 0];
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

final class PeerKickTest extends TwoNodes
{
    private KickPeer $peer;

    protected function setUp(): void
    {
        parent::setUp();
        Kernel::use($this->a->k);
        $this->peer = new KickPeer();
        $this->a->k->usePeer($this->peer);
    }

    protected function tearDown(): void
    {
        Kernel::use(null);
        parent::tearDown();
    }

    private function model(): Model
    {
        return new class ($this->a->db) extends Model {
            use Syncable;

            protected $table          = 'items';
            protected $allowedFields  = ['name', 'price', 'note'];
            protected $skipValidation = true;
        };
    }

    public function testSeveralWritesOfOneRequestTellThePeerOnce(): void
    {
        $m  = $this->model();
        $id = $m->insert(['name' => 'a']);
        $m->update($id, ['price' => 2]);
        $m->insert(['name' => 'b']);

        $this->assertSame([], $this->peer->kicks, 'nothing is sent while the request is still running');

        $this->a->k->flushPeerKick();
        $this->assertSame([['site1', true]], $this->peer->kicks);

        $this->a->k->flushPeerKick();
        $this->assertCount(1, $this->peer->kicks, 'a second flush with nothing new sends nothing');
    }

    public function testAnUnreachablePeerNeverBreaksTheRequest(): void
    {
        $this->peer->fail = true;
        $this->model()->insert(['name' => 'a']);

        $this->a->k->flushPeerKick(); // must not throw
        $this->assertSame([], $this->peer->kicks);
    }

    public function testChangesThatArriveFromThePeerDoNotKickItBack(): void
    {
        $this->b->insert(['name' => 'from-b']);
        $this->a->pullFrom($this->b); // applied through the gateway, not through a Syncable model

        $this->a->k->flushPeerKick();
        $this->assertSame([], $this->peer->kicks, 'no ping-pong');
    }
}
