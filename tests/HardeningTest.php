<?php

declare(strict_types=1);

namespace AdGo\Sync\Tests;

use AdGo\Sync\Libraries\FileSync;
use AdGo\Sync\Libraries\NonceStore;
use AdGo\Sync\Libraries\Options;
use AdGo\Sync\Libraries\RowGateway;
use AdGo\Sync\Libraries\SyncClient;
use RuntimeException;

final class HardeningTest extends TwoNodes
{
    // --- nonce -----------------------------------------------------------------------------------------------

    public function testANonceIsHonouredOnceAndForgottenAfterItsTtl(): void
    {
        $store = new NonceStore($this->a->db, 600);

        $this->assertTrue($store->claim('n-1', 1000));
        $this->assertFalse($store->claim('n-1', 1001), 'the same nonce again is a replay');
        $this->assertTrue($store->claim('n-2', 1001), 'another nonce is fine');

        $this->assertSame(2, $store->purge(1000 + 601 + 1));
        $this->assertTrue($store->claim('n-1', 2000), 'forgotten nonces are free again (the timestamp window rejects them by then)');
    }

    public function testWithoutTheTableTheCacheIsUsedOrTheRequestIsRefusedNeverWaved(): void
    {
        $this->a->db->query('DROP TABLE sync_nonce');
        $store = new NonceStore($this->a->db, 600);

        try {
            $first  = $store->claim('n-9');
            $second = $store->claim('n-9');
            $this->assertTrue($first);
            $this->assertFalse($second, 'if a cache answers, it must catch the replay');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('no nonce store', $e->getMessage());
        }
    }

    // --- transport -------------------------------------------------------------------------------------------

    public function testPlainHttpIsOnlyAllowedForThisMachineOrByOptIn(): void
    {
        $strict = Options::fromArray([]);
        $this->assertTrue($strict->transportAllowed('https://beta.example.com'));
        $this->assertTrue($strict->transportAllowed('HTTPS://beta.example.com'));
        $this->assertTrue($strict->transportAllowed('http://localhost:8080'));
        $this->assertTrue($strict->transportAllowed('http://127.0.0.1'));
        $this->assertTrue($strict->transportAllowed('http://app.localhost'));
        $this->assertFalse($strict->transportAllowed('http://beta.example.com'));
        $this->assertFalse($strict->transportAllowed('http://10.0.0.5'));
        $this->assertFalse($strict->transportAllowed('ftp://x'));
        $this->assertFalse($strict->transportAllowed('http://localhost.evil.com'));

        $this->assertTrue(Options::fromArray(['allowInsecure' => true])->transportAllowed('http://10.0.0.5'));
    }

    public function testTheClientRefusesPlainHttpBeforeSendingAnything(): void
    {
        $opt = Options::fromArray(['node' => 'site1', 'peerNode' => 'site2', 'peerUrl' => 'http://beta.example.com', 'secret' => 'x']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Refusing plain HTTP');
        (new SyncClient($opt))->call('ping', []);
    }

    // --- sizes -----------------------------------------------------------------------------------------------

    public function testBatchSizeNeverExceedsWhatAPushAccepts(): void
    {
        $this->assertSame(200, Options::fromArray([])->batch());
        $this->assertSame(1000, Options::fromArray(['pullBatch' => 50000])->batch());
        $this->assertSame(1, Options::fromArray(['pullBatch' => 0])->batch());
        $this->assertSame(1000, Options::fromArray([])->maxPushChanges);
        $this->assertSame(5, Options::fromArray(['maxPushChanges' => 5])->maxPushChanges);
    }

    // --- Telegram --------------------------------------------------------------------------------------------

    public function testTelegramUsersAcceptAListOrACommaSeparatedString(): void
    {
        $this->assertSame([], Options::fromArray([])->telegramUsers());
        $this->assertSame([], Options::fromArray(['telegram' => ['allowed_users' => '']])->telegramUsers());
        $this->assertSame(['11', '22'], Options::fromArray(['telegram' => ['allowed_users' => ' 11, 22 ,']])->telegramUsers());
        $this->assertSame(['5', '6'], Options::fromArray(['telegram' => ['allowed_users' => [5, '6']]])->telegramUsers());
    }

    // --- incoming rows ---------------------------------------------------------------------------------------

    public function testAnIncomingRowCannotChooseItsIdOrAddColumns(): void
    {
        $gw = new RowGateway($this->a->db, $this->a->k->opt, $this->a->k->meta);

        $id = $gw->insert('items', ['id' => 999, 'name' => 'x', 'price' => 1, 'evil' => 'y', 'note' => 'n']);

        $this->assertNotSame(999, (int) $id, 'the primary key is local-only');
        $this->assertEquals(['name' => 'x', 'price' => '1', 'note' => 'n'], array_diff_key($this->a->row((int) $id), ['id' => 1]));

        $gw->update('items', $id, ['id' => 5, 'name' => 'z', 'nope' => 1]);
        $row = $this->a->row((int) $id);
        $this->assertSame('z', $row['name']);
        $this->assertSame((int) $id, (int) $row['id']);
    }

    // --- files -----------------------------------------------------------------------------------------------

    private function files(): array
    {
        $root = sys_get_temp_dir() . '/adgo-hard-' . bin2hex(random_bytes(4));
        mkdir($root . '/files', 0775, true);
        file_put_contents($root . '/secret.txt', 'outside');
        $fs = new FileSync($this->a->db, Options::fromArray(['node' => 'site1', 'peerNode' => 'site2', 'files' => ['dir' => $root . '/files', 'ignore' => ['*.tmp', '.private']]]), new LocalPeer($this->b));

        return [$fs, $root];
    }

    public function testFilesGetServesNormalFilesButNotIgnoredOrConflictCopies(): void
    {
        [$fs, $root] = $this->files();
        file_put_contents($root . '/files/a.txt', 'A');
        file_put_contents($root . '/files/.private', 'P');
        file_put_contents($root . '/files/x.tmp', 'T');
        file_put_contents($root . '/files/a.txt.conflict-site2-1', 'C');

        $this->assertSame('A', $fs->read('a.txt'));
        $this->assertNull($fs->read('.private'));
        $this->assertNull($fs->read('x.tmp'));
        $this->assertNull($fs->read('a.txt.conflict-site2-1'));
        $this->assertNull($fs->read('../secret.txt'));
        $this->assertNull($fs->read('missing.txt'));
    }

    public function testFilesGetDoesNotFollowALinkOutOfTheDirectory(): void
    {
        [$fs, $root] = $this->files();

        if (! @symlink($root . '/secret.txt', $root . '/files/link.txt')) {
            $this->markTestSkipped('symlinks cannot be created here');
        }

        mkdir($root . '/files/d');
        @symlink($root, $root . '/files/d/up');

        $this->assertNull($fs->read('link.txt'));
        $this->assertNull($fs->read('d/up/secret.txt'));
    }
}
