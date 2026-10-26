<?php

declare(strict_types=1);

namespace AdGo\Sync\Tests;

use AdGo\Sync\Libraries\Bootstrapper;
use AdGo\Sync\Libraries\FileSync;
use AdGo\Sync\Libraries\PeerClient;
use AdGo\Sync\Libraries\Puller;
use AdGo\Sync\Libraries\Signer;
use AdGo\Sync\Libraries\Support;

/** In-process stand-in for the HTTP peer: answers from the other node's own classes. */
final class LocalPeer implements PeerClient
{
    public function __construct(private readonly Node $other, private readonly ?FileSync $files = null)
    {
    }

    public function pull(int $afterSeq, int $limit): array
    {
        // round-trip through the wire codec like the real API
        $items = Support::unpack(Support::pack($this->other->k->meta->changesSince($afterSeq, $limit)));

        return ['changes' => $items, 'last_seq' => $items === [] ? $afterSeq : end($items)['seq']];
    }

    public function filesManifest(): array { return $this->files->manifest(); }
    public function fileGet(string $path): ?string { return $this->files->read($path); }

    public function bootstrapPage(string $table, int $offset, int $limit): array
    {
        return (new Bootstrapper($this->other->db, $this->other->k->opt, $this))->page($table, $offset, $limit);
    }

    public function resolveConflict(string $table, string $uuid, ?array $state, int $newRev, int $expectRev, int $ts, string $by): array { throw new \LogicException(); }
    public function lockStatus(string $kind): array { throw new \LogicException(); }
    public function lockAcquire(string $kind, string $requester, bool $confirm, array $meta = []): array { throw new \LogicException(); }
    public function lockHeartbeat(string $kind, string $holder, array $meta = []): array { throw new \LogicException(); }
    public function lockSync(string $from, bool $kick): array { throw new \LogicException(); }
    public function lockRelease(string $kind, string $holder): array { throw new \LogicException(); }
}

final class TransportTest extends TwoNodes
{
    public function testSignatureRoundTripAndTamperDetection(): void
    {
        $sig = Signer::sign('s3cret', 'POST', '/sync-api/pull', 'site1', 1000, 'n1', 'after=0');
        $ok  = fn (string $secret, string $path, int $ts, string $nonce, string $body, string $s, int $now = 1000, string $node = 'site1') => Signer::verify($secret, 'POST', $path, $node, $ts, $nonce, $body, $s, 300, $now)['ok'];

        $this->assertTrue($ok('s3cret', '/sync-api/pull', 1000, 'n1', 'after=0', $sig));
        $this->assertFalse($ok('s3cret', '/sync-api/pull', 1000, 'n1', 'after=1', $sig), 'body tampered');
        $this->assertFalse($ok('s3cret', '/sync-api/other', 1000, 'n1', 'after=0', $sig), 'path tampered');
        $this->assertFalse($ok('s3cret', '/sync-api/pull', 1000, 'n2', 'after=0', $sig), 'nonce tampered');
        $this->assertFalse($ok('s3cret', '/sync-api/pull', 1000, 'n1', 'after=0', $sig, 1000, 'site2'), 'sender node tampered (F-1)');
        $this->assertFalse($ok('s3cret', '/sync-api/pull', 1000, 'n1', 'after=0', $sig, 1000, ''), 'sender node missing');
        $this->assertFalse($ok('wrong', '/sync-api/pull', 1000, 'n1', 'after=0', $sig), 'wrong secret');
        $this->assertFalse($ok('s3cret', '/sync-api/pull', 1000, 'n1', 'after=0', $sig, 5000), 'replayed outside the window');
        $this->assertFalse(Signer::verify('', 'POST', '/x', 'n', 1, 'n', '', 'x', 300, 1)['ok'], 'no secret configured');
    }

    public function testResponseSignatureIsBoundToTheRequestNonce(): void
    {
        $this->assertNotSame(Signer::signResponse('k', 'a', 'body'), Signer::signResponse('k', 'b', 'body'));
    }

    public function testSerializedPayloadNeverInstantiatesObjects(): void
    {
        $evil = serialize(new \ArrayObject(['x']));
        $out  = Support::unpack($evil);

        $this->assertInstanceOf(\__PHP_Incomplete_Class::class, $out);
        $this->expectException(\RuntimeException::class);
        Support::unpack('a:1:{broken');
    }

    public function testPullerAppliesBatchesAndResumesFromTheCursor(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->a->insert(['name' => "n{$i}"]);
        }

        $puller = new Puller($this->b->k, new LocalPeer($this->a));
        $first  = $puller->run();

        $this->assertSame(5, $first['pulled']);
        $this->assertCount(5, $this->b->all());
        $this->assertSame(5, $this->b->k->meta->cursor('site1'));

        $this->tick();
        $this->a->update(1, ['name' => 'renamed']);
        $second = $puller->run();

        $this->assertSame(1, $second['pulled']);
        $this->assertSame(['applied' => 1], $second['outcomes']);
        $this->assertSame(0, $puller->run()['pulled'], 'nothing new');
    }

    public function testBootstrapCopiesTablesOnceAndRefusesNonEmptyTargets(): void
    {
        foreach ([$this->a, $this->b] as $n) {
            $n->db->query('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        }

        $this->a->db->table('users')->insertBatch([['id' => 1, 'name' => 'root'], ['id' => 2, 'name' => 'ann']]);

        $opt = \AdGo\Sync\Libraries\Options::fromArray(['node' => 'site2', 'peerNode' => 'site1', 'bootstrapTables' => ['users', 'missing_table']]);
        $optA = \AdGo\Sync\Libraries\Options::fromArray(['node' => 'site1', 'peerNode' => 'site2', 'bootstrapTables' => ['users']]);

        $peer = new class ($this->a, $optA) implements PeerClient {
            public function __construct(private Node $n, private \AdGo\Sync\Libraries\Options $o) {}
            public function bootstrapPage(string $table, int $offset, int $limit): array { return (new Bootstrapper($this->n->db, $this->o, $this))->page($table, $offset, $limit); }
            public function pull(int $a, int $l): array { throw new \LogicException(); }
            public function resolveConflict(string $t, string $u, ?array $s, int $n, int $e, int $ts, string $b): array { throw new \LogicException(); }
            public function lockStatus(string $k): array { throw new \LogicException(); }
            public function lockAcquire(string $k, string $r, bool $c, array $meta = []): array { throw new \LogicException(); }
            public function lockHeartbeat(string $k, string $h, array $meta = []): array { throw new \LogicException(); }
            public function lockSync(string $from, bool $kick): array { throw new \LogicException(); }
            public function lockRelease(string $k, string $h): array { throw new \LogicException(); }
            public function filesManifest(): array { throw new \LogicException(); }
            public function fileGet(string $p): ?string { throw new \LogicException(); }
        };

        $copied = (new Bootstrapper($this->b->db, $opt, $peer))->run();
        $this->assertSame(['users' => 2], $copied);
        $this->assertSame(2, $this->b->db->table('users')->countAllResults());

        $this->expectException(\RuntimeException::class);
        (new Bootstrapper($this->b->db, $opt, $peer))->run(); // now non-empty
    }

    public function testBootstrapServerRefusesTablesOutsideTheAllowlist(): void
    {
        $opt = \AdGo\Sync\Libraries\Options::fromArray(['node' => 'site1', 'peerNode' => 'site2', 'bootstrapTables' => ['users']]);
        $this->expectException(\RuntimeException::class);
        (new Bootstrapper($this->a->db, $opt, new LocalPeer($this->b)))->page('sync_changes', 0, 10);
    }

    public function testExcludedTablesCannotBeConfiguredForSync(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        \AdGo\Sync\Libraries\Options::fromArray(['tables' => ['auth_logins' => []]]);
    }

    private function dirs(): array
    {
        $root = sys_get_temp_dir() . '/adgo-sync-' . bin2hex(random_bytes(4));
        mkdir($root . '/a', 0775, true);
        mkdir($root . '/b', 0775, true);

        return [$root, $root . '/a', $root . '/b'];
    }

    private function fs(Node $n, string $dir, ?FileSync $peerFs, ?Node $peerNode): FileSync
    {
        $opt = \AdGo\Sync\Libraries\Options::fromArray([
            'node' => $n->name, 'peerNode' => $n->name === 'site1' ? 'site2' : 'site1',
            'files' => ['dir' => $dir, 'ignore' => ['*.tmp']],
        ]);

        return new FileSync($n->db, $opt, $peerFs === null ? new LocalPeer($n) : new LocalPeer($peerNode, $peerFs));
    }

    /** @return array{FileSync, FileSync, string, string} */
    private function pair(): array
    {
        [, $da, $db] = $this->dirs();
        // bootstrap each side first with a manifest-only view of itself
        $fa = $this->fs($this->a, $da, null, null);
        $fb = $this->fs($this->b, $db, null, null);
        // now wire them to each other
        $fa = $this->fs($this->a, $da, $fb, $this->b);
        $fb = $this->fs($this->b, $db, $fa, $this->a);

        return [$fa, $fb, $da, $db];
    }

    public function testFilesAreCopiedOnlyWhenDifferentAndDeletionsPropagate(): void
    {
        [$fa, $fb, $da, $db] = $this->pair();
        mkdir($da . '/img');
        file_put_contents($da . '/img/logo.png', 'PNGDATA');
        file_put_contents($da . '/skip.tmp', 'x');

        $r = $fb->run();
        $this->assertSame(1, $r['downloaded']);
        $this->assertSame('PNGDATA', file_get_contents($db . '/img/logo.png'));
        $this->assertFileDoesNotExist($db . '/skip.tmp');

        $this->assertSame(0, $fb->run()['downloaded'], 'second run transfers nothing');

        // delete on A -> A records nothing itself; B learns via its index
        $fa->run();
        unlink($da . '/img/logo.png');
        $fb->run();
        $this->assertFileDoesNotExist($db . '/img/logo.png');
    }

    public function testAnUnchangedFileIsNotHashedAgainButAChangedOneIs(): void
    {
        [$fa, $fb, $da] = $this->pair();
        Support::freezeNow(null); // real clock: the index only trusts a file that was stable when it was indexed
        file_put_contents($da . '/p.bin', 'AAAA');
        touch($da . '/p.bin', time() - 60);
        $fb->run(); // B takes it ...
        $fa->run(); // ... and A sees both sides equal and indexes it
        $this->assertSame(1, $this->a->db->table('sync_files')->countAllResults());

        $real = hash('sha256', 'AAAA');
        $this->assertSame($real, $fa->manifest()[0]['sha256']);

        // a marker in the index shows whether the recorded hash is used for an untouched file
        $this->a->db->table('sync_files')->update(['sha256' => 'cached-marker']);
        $this->assertSame('cached-marker', $fa->manifest()[0]['sha256'], 'same mtime and size, indexed later: not read again');

        // same size, newer mtime: the file is read again
        file_put_contents($da . '/p.bin', 'BBBB');
        touch($da . '/p.bin', time() - 5);
        $this->assertSame(hash('sha256', 'BBBB'), $fa->manifest()[0]['sha256']);

        // different size, even with the old mtime: read again
        $this->a->db->table('sync_files')->update(['sha256' => 'cached-marker', 'mtime' => time() - 5, 'size' => 4, 'seen_at' => Support::nowUs()]);
        file_put_contents($da . '/p.bin', 'CCCCCC');
        touch($da . '/p.bin', time() - 5);
        $this->assertSame(hash('sha256', 'CCCCCC'), $fa->manifest()[0]['sha256']);
    }

    public function testASteadyRunWritesNothingToTheIndex(): void
    {
        [$fa, $fb, $da] = $this->pair();
        file_put_contents($da . '/a.png', 'ONE');
        $fb->run();
        $fa->run();

        $seen = $this->a->db->table('sync_files')->get()->getRowArray()['seen_at'];
        $this->tick(5_000_000);
        $fa->run();

        $this->assertSame($seen, $this->a->db->table('sync_files')->get()->getRowArray()['seen_at'], 'unchanged: the index row is not rewritten');
    }

    public function testIndexEntriesOfFilesGoneFromBothNodesAreForgotten(): void
    {
        [$fa, $fb, $da, $db] = $this->pair();
        file_put_contents($da . '/a.png', 'ONE');
        file_put_contents($da . '/b.png', 'TWO');
        $fb->run();
        $fa->run();
        $this->assertSame(2, $this->a->db->table('sync_files')->countAllResults());

        unlink($da . '/a.png');
        $fa->run(); // A: a.png is missing here but B still has it: B has to learn it
        $fb->run(); // B deletes it
        $fa->run(); // gone on both sides: the index of A drops the entry
        $fb->run();

        $this->assertSame(1, $this->a->db->table('sync_files')->countAllResults());
        $this->assertSame(1, $this->b->db->table('sync_files')->countAllResults());
        $this->assertFileExists($db . '/b.png');
    }

    public function testBothSidesChangedKeepsTheNewerAndSavesTheOtherAsConflictCopy(): void
    {
        [$fa, $fb, $da, $db] = $this->pair();
        file_put_contents($da . '/doc.txt', 'v0');
        $fb->run();
        $fa->run();

        file_put_contents($da . '/doc.txt', 'from-A');
        touch($da . '/doc.txt', time() - 100);
        file_put_contents($db . '/doc.txt', 'from-B');
        touch($db . '/doc.txt', time());

        $r = $fa->run();

        $this->assertSame(1, $r['conflicts']);
        $this->assertSame('from-B', file_get_contents($da . '/doc.txt'));
        $copies = glob($da . '/doc.txt.conflict-*');
        $this->assertCount(1, $copies);
        $this->assertSame('from-A', file_get_contents($copies[0]));
    }

    public function testAnUntrustedPeerClockMakesTiebreakNotMtimeDecideAFileConflict(): void
    {
        (new \AdGo\Sync\Libraries\ClockMonitor($this->a->db, $this->a->k->opt))->observe('site2', 30_000_000, 1000);   // site2 is 30 s ahead
        (new \AdGo\Sync\Libraries\ClockMonitor($this->b->db, $this->b->k->opt))->observe('site1', -30_000_000, 1000);   // and site2 sees the same from its side

        [$fa, $fb, $da, $db] = $this->pair();
        file_put_contents($da . '/doc.txt', 'v0');
        $fb->run();
        $fa->run();

        file_put_contents($da . '/doc.txt', 'from-A');
        touch($da . '/doc.txt', time() - 100);
        file_put_contents($db . '/doc.txt', 'from-B');
        touch($db . '/doc.txt', time());                       // clearly newer, but the clock that says so is not trusted

        $r = $fa->run();

        $this->assertSame(1, $r['conflicts']);
        $this->assertSame('from-A', file_get_contents($da . '/doc.txt'), 'site1 ranks first in the tiebreak and keeps its version');
        $this->assertSame([], glob($da . '/doc.txt.conflict-*'));

        $fb->run();                                           // the other node reaches the same decision and keeps its loser as a copy
        $this->assertSame('from-A', file_get_contents($db . '/doc.txt'));
        $this->assertCount(1, glob($db . '/doc.txt.conflict-*'));
    }

    public function testFileTimesWithinTheToleranceCountAsSimultaneous(): void
    {
        [$fa, $fb, $da, $db] = $this->pair();
        file_put_contents($da . '/doc.txt', 'v0');
        $fb->run();
        $fa->run();

        $now = time();
        file_put_contents($da . '/doc.txt', 'from-A');
        touch($da . '/doc.txt', $now - 1);
        file_put_contents($db . '/doc.txt', 'from-B');
        touch($db . '/doc.txt', $now);                         // one second newer, below the 2 s tolerance

        $fa = $this->toleranceFs($this->a, $da, $fb, $this->b);
        $fa->run();

        $this->assertSame('from-A', file_get_contents($da . '/doc.txt'), 'not decided by a one second difference');
    }

    private function toleranceFs(Node $n, string $dir, FileSync $peerFs, Node $peerNode): FileSync
    {
        $opt = \AdGo\Sync\Libraries\Options::fromArray([
            'node' => $n->name, 'peerNode' => $peerNode->name, 'clockTolerance' => 2.0,
            'files' => ['dir' => $dir, 'ignore' => ['*.tmp']],
        ]);

        return new FileSync($n->db, $opt, new LocalPeer($peerNode, $peerFs));
    }

    public function testPathTraversalIsRejected(): void
    {
        [$fa, , $da] = $this->pair();
        file_put_contents(dirname($da) . '/secret.txt', 'top');

        $this->assertNull($fa->read('../secret.txt'));
        $this->assertNull($fa->read('/etc/passwd'));
        $this->assertNull($fa->read('C:\\Windows\\win.ini'));
        $this->assertNull($fa->read("a\0b"));
    }

    public function testEmptyDirectoriesAndNestedTreesAreCreatedOnThePeer(): void
    {
        [$fa, $fb, $da, $db] = $this->pair();
        mkdir($da . '/empty');
        mkdir($da . '/deep/er/still', 0775, true);
        file_put_contents($da . '/deep/er/f.txt', 'x');

        $r = $fb->run();
        $this->assertDirectoryExists($db . '/empty');
        $this->assertDirectoryExists($db . '/deep/er/still');
        $this->assertSame('x', file_get_contents($db . '/deep/er/f.txt'));
        $this->assertSame(1, $r['downloaded']);
        $this->assertSame(2, $r['dirs'], 'empty and deep/er/still; deep and deep/er already came with the file');

        $this->assertSame(0, $fb->run()['dirs'], 'nothing left to do');
    }

    public function testARemovedDirectoryIsRemovedOnThePeerAfterItsFiles(): void
    {
        [$fa, $fb, $da, $db] = $this->pair();
        mkdir($da . '/tree/sub', 0775, true);
        file_put_contents($da . '/tree/sub/f.txt', 'x');
        $fb->run();
        $fa->run(); // both sides indexed
        $this->assertDirectoryExists($db . '/tree/sub');

        unlink($da . '/tree/sub/f.txt');
        rmdir($da . '/tree/sub');
        rmdir($da . '/tree');

        $fb->run(); // file, then sub, then tree in one run
        $this->assertDirectoryDoesNotExist($db . '/tree');
    }

    public function testADirectoryThatStillHoldsLocalFilesIsNotRemoved(): void
    {
        [$fa, $fb, $da, $db] = $this->pair();
        mkdir($da . '/keep');
        $fb->run();
        $fa->run();

        rmdir($da . '/keep'); // gone on A ...
        file_put_contents($db . '/keep/new.txt', 'mine'); // ... but B has put a file in it meanwhile

        $fb->run();
        $this->assertFileExists($db . '/keep/new.txt');

        $fa->run(); // A takes the file and with it the directory again
        $this->assertSame('mine', file_get_contents($da . '/keep/new.txt'));
    }

    public function testADirectoryAgainstAFileIsLeftAlone(): void
    {
        [$fa, $fb, $da, $db] = $this->pair();
        mkdir($da . '/x');
        file_put_contents($db . '/x', 'file');

        $r = $fb->run();
        $this->assertSame(1, $r['conflicts']);
        $this->assertSame('file', file_get_contents($db . '/x'));
        $this->assertDirectoryExists($da . '/x');
    }
}
