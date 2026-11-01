<?php

declare(strict_types=1);

namespace AdGo\Sync\Tests;

require_once __DIR__ . '/TwoNodes.php';

use AdGo\Sync\Libraries\Options;
use AdGo\Sync\Libraries\PushClient;
use AdGo\Sync\Libraries\Pusher;
use AdGo\Sync\Libraries\Support;

/** A hub that a passive node (behind NAT) pushes to, in process: app --push--> hub <--pull-- site2. */
final class HubClient implements PushClient
{
    public function __construct(private readonly Node $hub, private readonly string $as)
    {
    }

    public function push(array $changes): array
    {
        $r = $this->hub->k->puller()->applyBatch($this->as, $changes);

        return ['applied' => $r['cursor'], 'stopped' => $r['stopped']];
    }

    public function pull(int $afterSeq, int $limit): array
    {
        $c = $this->hub->k->meta->changesSince($afterSeq, $limit);

        return ['changes' => $c, 'last_seq' => $c === [] ? $afterSeq : (int) end($c)['seq']];
    }

    public function lockSync(string $from, bool $kick): array
    {
        return ['applied' => $this->hub->k->meta->cursor($this->as)];
    }

    public function resolveConflict(string $table, string $uuid, ?array $state, int $newRev, int $expectRev, int $ts, string $by): array { throw new \LogicException(); }
    public function lockStatus(string $kind): array { throw new \LogicException(); }
    public function lockAcquire(string $kind, string $requester, bool $confirm, array $meta = []): array { throw new \LogicException(); }
    public function lockHeartbeat(string $kind, string $holder, array $meta = []): array { throw new \LogicException(); }
    public function lockRelease(string $kind, string $holder): array { throw new \LogicException(); }
    public function filesManifest(): array { throw new \LogicException(); }
    public function fileGet(string $path): ?string { throw new \LogicException(); }
    public function bootstrapPage(string $table, int $offset, int $limit): array { throw new \LogicException(); }
}

final class PassivePeerTest extends \PHPUnit\Framework\TestCase
{
    private Node $app;
    private Node $hub;
    private Node $other;

    protected function setUp(): void
    {
        Support::freezeNow(1_000_000);
        $t = ['items' => ['policy' => 'tolerant', 'sensitive_fields' => ['price']]];
        // hub = site1 (reachable, relays), other = site2 (the classic partner of the hub), app = passive NAT node
        $this->hub   = new Node('site1', 'site2', $t, ['peers' => ['site2' => ['url' => 'https://b.example'], 'app' => ['passive' => true]], 'relay' => true]);
        $this->other = new Node('site2', 'site1', $t);
        $this->app = new Node('app', 'site1', $t, ['peers' => ['site1' => ['url' => 'https://a.example']], 'passive' => true, 'tiebreak' => ['site1', 'app']]);
    }

    protected function tearDown(): void
    {
        Support::freezeNow(null);
    }

    private function tick(): void
    {
        Support::freezeNow(Support::nowUs() + 1000);
    }

    /** @return array{pushed: int, applied: int, stopped: ?string} */
    private function push(): array
    {
        return (new Pusher($this->app->k, new HubClient($this->hub, 'app')))->run();
    }

    /** One round: app pushes, the hub pulls site2, site2 pulls the hub, app pulls the hub. */
    private function round(): void
    {
        $this->push();
        $this->hub->pullFrom($this->other);
        $this->other->pullFrom($this->hub);
        $this->app->pullFrom($this->hub);
    }

    public function testOptionsDescribeTheTopology(): void
    {
        $o = $this->hub->k->opt;
        $this->assertSame(['site2'], $o->activePeers());
        $this->assertTrue($o->isPassivePeer('app'));
        $this->assertTrue($o->isPeer('app'));
        $this->assertSame('', $o->urlOf('app'));
        $this->assertTrue($o->relay);

        $f = $this->app->k->opt;
        $this->assertTrue($f->passive);
        $this->assertSame(['site1'], $f->activePeers());
        $this->assertSame('site1', $f->hub());

        $classic = Options::fromArray(['node' => 'a', 'peerNode' => 'b', 'peerUrl' => 'https://b/']);
        $this->assertSame(['b'], $classic->activePeers());
        $this->assertSame('https://b', $classic->urlOf('b'));
        $this->assertFalse($classic->passive);
    }

    public function testThePassiveNodePushesWithoutBeingReachable(): void
    {
        $id = $this->app->insert(['name' => 'from app', 'price' => 10]);
        $r  = $this->push();

        $this->assertSame(1, $r['pushed']);
        $this->assertNull($r['stopped']);
        $hubId = $this->hub->idByUuid($this->app->uuidOf($id));
        $this->assertNotNull($hubId);
        $this->assertSame('from app', $this->hub->row($hubId)['name']);
        $this->assertSame(1, $this->hub->k->meta->cursor('app'));
    }

    public function testAResendChangesNothingAndANewChangeFollows(): void
    {
        $id = $this->app->insert(['name' => 'x', 'price' => 1]);
        $this->push();
        $this->assertSame(0, $this->push()['pushed']);

        $this->tick();
        $this->app->update($id, ['price' => 2]);
        $this->assertSame(1, $this->push()['pushed']);
        $this->assertSame(2, (int) $this->hub->row($this->hub->idByUuid($this->app->uuidOf($id)))['price']);
    }

    public function testRepeatedBatchesAreSkippedByTheCursor(): void
    {
        $this->app->insert(['name' => 'a', 'price' => 1]);
        $this->app->insert(['name' => 'b', 'price' => 2]);
        $batch = $this->app->k->meta->changesSince(0, 100);

        $first = $this->hub->k->puller()->applyBatch('app', $batch);
        $again = $this->hub->k->puller()->applyBatch('app', $batch);

        $this->assertSame(2, $first['pulled']);
        $this->assertSame(0, $again['pulled']);
        $this->assertCount(2, $this->hub->all());
    }

    public function testTheHubRelaysAppRowsToTheOtherNodeAndBack(): void
    {
        $f = $this->app->insert(['name' => 'app row', 'price' => 5]);
        $o = $this->other->insert(['name' => 'site2 row', 'price' => 7]);

        $this->round();
        $this->round();

        $this->assertNotNull($this->other->idByUuid($this->app->uuidOf($f)), 'the app row reached site2 through the hub');
        $this->assertNotNull($this->app->idByUuid($this->other->uuidOf($o)), 'the site2 row reached the app through the hub');
        $this->assertSame(['app row', 'site2 row'], array_column($this->app->all(), 'name'));
        $this->assertSame(['app row', 'site2 row'], array_column($this->other->all(), 'name'));
    }

    public function testRelayDoesNotPingPong(): void
    {
        $this->app->insert(['name' => 'one', 'price' => 1]);
        $this->round();
        $this->round();
        $this->round();

        $before = (int) ($this->hub->db->table('sync_changes')->selectMax('seq')->get()->getRowArray()['seq'] ?? 0);
        $this->round();
        $this->round();
        $after = (int) ($this->hub->db->table('sync_changes')->selectMax('seq')->get()->getRowArray()['seq'] ?? 0);

        $this->assertSame($before, $after, 'once everybody agrees, no new journal entries appear');
        $this->assertCount(1, $this->other->all());
        $this->assertCount(1, $this->app->all());
    }

    public function testRelayedEntriesCarryTheirOrigin(): void
    {
        $this->app->insert(['name' => 'tagged', 'price' => 1]);
        $this->push();

        $rows = $this->hub->k->meta->changesSince(0, 100);
        $this->assertCount(1, $rows);
        $this->assertSame('app', $rows[0]['origin']);
        $this->assertSame('site1', $rows[0]['node'], 'the hub journals it as its own revision');
    }

    public function testASingleWriterTableRefusesChangesFromOtherOrigins(): void
    {
        $t = ['items' => ['policy' => 'tolerant', 'sensitive_fields' => ['price'], 'writers' => ['app']]];
        $hub   = new Node('site1', 'site2', $t, ['peers' => ['site2' => ['url' => 'https://b.example'], 'app' => ['passive' => true]], 'relay' => true]);
        $other = new Node('site2', 'site1', $t);
        $app = new Node('app', 'site1', $t, ['peers' => ['site1' => ['url' => 'https://a.example']], 'passive' => true, 'tiebreak' => ['site1', 'app']]);

        $f = $app->insert(['name' => 'owned by app', 'price' => 10]);
        (new Pusher($app->k, new HubClient($hub, 'app')))->run();
        $other->pullFrom($hub);
        $this->assertNotNull($other->idByUuid($app->uuidOf($f)), 'the writer rows travel to the other node');

        // site2 invents a row: the hub refuses it
        $other->insert(['name' => 'rogue', 'price' => 1]);
        $res = $hub->pullFrom($other);
        $this->assertSame(['rejected'], array_values(array_unique(array_column($res, 'outcome'))));
        $this->assertCount(1, $hub->all());

        // and a rogue edit of the writer's row is refused as well
        Support::freezeNow(Support::nowUs() + 1000);
        $u = $other->idByUuid($app->uuidOf($f));
        $other->update($u, ['price' => 99]);
        $hub->pullFrom($other);
        $this->assertSame(10, (int) $hub->row($hub->idByUuid($app->uuidOf($f)))['price']);
    }

    /** What the hub relays back to its origin is an echo: converged, nothing rewritten. */
    public function testTheNodeDoesNotApplyTheEchoOfItsOwnChanges(): void
    {
        $id = $this->app->insert(['name' => 'mine', 'price' => 5]);
        $this->push();
        $echoes = $this->app->pullFrom($this->hub);

        foreach ($echoes as $r) {
            $this->assertSame('converged', $r['outcome']);
        }

        $this->assertSame('mine', $this->app->row($id)['name']);
    }
}
