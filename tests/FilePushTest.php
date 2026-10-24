<?php

declare(strict_types=1);

namespace AdGo\Sync\Tests;

use AdGo\Sync\Libraries\FilePushClient;
use AdGo\Sync\Libraries\FileSync;
use AdGo\Sync\Libraries\Options;

/** The hub as seen by a passive node: manifest and download as always, plus files/put. */
final class HubFiles implements FilePushClient
{
    /** @var list<string> */
    public array $puts = [];

    public function __construct(private readonly FileSync $hub)
    {
    }

    public function filesManifest(): array { return $this->hub->manifest(); }
    public function fileGet(string $path): ?string { return $this->hub->read($path); }

    public function filePut(string $path, string $kind, ?string $expect, ?string $sha, int $mtime, string $content): array
    {
        $this->puts[] = $kind . ':' . $path;

        return $this->hub->receive(['path' => $path, 'kind' => $kind, 'expect' => $expect ?? '', 'sha256' => $sha ?? '', 'mtime' => $mtime, 'content' => base64_encode($content)]);
    }

    public function bootstrapPage(string $table, int $offset, int $limit): array { throw new \LogicException(); }
    public function pull(int $afterSeq, int $limit): array { throw new \LogicException(); }
    public function resolveConflict(string $table, string $uuid, ?array $state, int $newRev, int $expectRev, int $ts, string $by): array { throw new \LogicException(); }
    public function lockStatus(string $kind): array { throw new \LogicException(); }
    public function lockAcquire(string $kind, string $requester, bool $confirm, array $meta = []): array { throw new \LogicException(); }
    public function lockHeartbeat(string $kind, string $holder, array $meta = []): array { throw new \LogicException(); }
    public function lockSync(string $from, bool $kick): array { throw new \LogicException(); }
    public function lockRelease(string $kind, string $holder): array { throw new \LogicException(); }
}

final class FilePushTest extends TwoNodes
{
    private string $agentDir;
    private string $hubDir;

    /** @return array{FileSync, FileSync, HubFiles} agent (passive), hub, the agent's view of the hub */
    private function topology(bool $passive = true): array
    {
        $root = sys_get_temp_dir() . '/adgo-push-' . bin2hex(random_bytes(4));
        mkdir($this->agentDir = $root . '/agent', 0775, true);
        mkdir($this->hubDir = $root . '/hub', 0775, true);

        $hubOpt = Options::fromArray(['node' => 'site2', 'peerNode' => 'site1', 'files' => ['dir' => $this->hubDir, 'ignore' => ['*.tmp']]]);
        $hub    = new FileSync($this->b->db, $hubOpt, new LocalPeer($this->a));
        $view   = new HubFiles($hub);
        $agent  = new FileSync($this->a->db, Options::fromArray([
            'node' => 'site1', 'peerNode' => 'site2', 'passive' => $passive,
            'files' => ['dir' => $this->agentDir, 'ignore' => ['*.tmp']],
        ]), $view);

        return [$agent, $hub, $view];
    }

    public function testANewFileInASubdirectoryReachesTheHubAndIsNotSentAgain(): void
    {
        [$agent, , $view] = $this->topology();
        mkdir($this->agentDir . '/labels/2026', 0775, true);
        file_put_contents($this->agentDir . '/labels/2026/a.pdf', 'PDF-1');

        $r = $agent->run();

        $this->assertSame(3, $r['pushed'], 'two directories and the file');
        $this->assertSame('PDF-1', file_get_contents($this->hubDir . '/labels/2026/a.pdf'));
        $this->assertSame(['file:labels/2026/a.pdf', 'dir:labels/2026', 'dir:labels'], $view->puts);

        $view->puts = [];
        $this->assertSame(0, $agent->run()['pushed']);
        $this->assertSame([], $view->puts, 'steady state sends nothing');
    }

    public function testAChangeAndADeletionAreDelivered(): void
    {
        [$agent, , $view] = $this->topology();
        file_put_contents($this->agentDir . '/f.txt', 'one');
        $agent->run();

        file_put_contents($this->agentDir . '/f.txt', 'two!');
        touch($this->agentDir . '/f.txt', time() + 5);
        $this->assertSame(1, $agent->run()['pushed']);
        $this->assertSame('two!', file_get_contents($this->hubDir . '/f.txt'));

        unlink($this->agentDir . '/f.txt');
        $this->assertSame(1, $agent->run()['pushed']);
        $this->assertFileDoesNotExist($this->hubDir . '/f.txt');
        $this->assertSame('delete:f.txt', end($view->puts));
    }

    public function testANestedDirectoryDeletedOnTheAgentIsRemovedFromTheHubInOneRun(): void
    {
        [$agent] = $this->topology();
        mkdir($this->agentDir . '/a/b', 0775, true);
        file_put_contents($this->agentDir . '/a/b/f.txt', 'x');
        $agent->run();
        $agent->run();
        $this->assertFileExists($this->hubDir . '/a/b/f.txt');

        unlink($this->agentDir . '/a/b/f.txt');
        rmdir($this->agentDir . '/a/b');
        rmdir($this->agentDir . '/a');

        $r = $agent->run();

        $this->assertSame(3, $r['pushed']);
        $this->assertSame(0, $r['kept']);
        $this->assertDirectoryDoesNotExist($this->hubDir . '/a');
    }

    public function testTheHubRefusesAChangeBasedOnAStaleView(): void
    {
        [, $hub] = $this->topology();
        file_put_contents($this->hubDir . '/doc.txt', 'hub version');

        $r = $hub->receive(['path' => 'doc.txt', 'kind' => 'file', 'expect' => hash('sha256', 'something else'), 'sha256' => hash('sha256', 'x'), 'mtime' => time(), 'content' => base64_encode('x')]);

        $this->assertSame(['ok' => false, 'error' => 'stale'], $r);
        $this->assertSame('hub version', file_get_contents($this->hubDir . '/doc.txt'));
    }

    public function testBothSidesChangedIsDecidedByTheNewerMtimeAndNothingIsLost(): void
    {
        [$agent, $hub] = $this->topology();
        file_put_contents($this->agentDir . '/doc.txt', 'v0');
        $agent->run();

        file_put_contents($this->agentDir . '/doc.txt', 'agent edit');
        touch($this->agentDir . '/doc.txt', time() - 100);
        file_put_contents($this->hubDir . '/doc.txt', 'hub edit');
        touch($this->hubDir . '/doc.txt', time());

        $r = $agent->run();

        $this->assertSame(1, $r['conflicts']);
        $this->assertSame('hub edit', file_get_contents($this->agentDir . '/doc.txt'), 'the newer hub version wins');
        $copies = glob($this->agentDir . '/doc.txt.conflict-*');
        $this->assertCount(1, $copies);
        $this->assertSame('agent edit', file_get_contents($copies[0]), 'the loser is kept as a conflict copy');
        $this->assertSame('hub edit', file_get_contents($this->hubDir . '/doc.txt'));
    }

    public function testThePathIsConfinedAndIgnoredNamesAreRefused(): void
    {
        [, $hub] = $this->topology();
        $base = ['kind' => 'file', 'expect' => '', 'sha256' => hash('sha256', 'x'), 'mtime' => time(), 'content' => base64_encode('x')];

        foreach (['../escape.txt', '/etc/passwd', 'a/../../b', 'skip.tmp', 'x.conflict-site1-1', ''] as $path) {
            $this->assertSame('refused', $hub->receive(['path' => $path] + $base)['error'] ?? null, $path);
        }

        $this->assertSame('refused', $hub->receive(['path' => 'ok.txt'] + ['kind' => 'rename'] + $base)['error']);
        $this->assertSame('bad content', $hub->receive(['path' => 'ok.txt', 'sha256' => hash('sha256', 'other')] + $base)['error'], 'bytes must match the announced hash');
        $this->assertFileDoesNotExist($this->hubDir . '/ok.txt');
        $this->assertFileDoesNotExist(dirname($this->hubDir) . '/escape.txt');
    }

    public function testADirectoryIsOnlyRemovedWhenEmptyAndATypeClashIsRefused(): void
    {
        [, $hub] = $this->topology();
        mkdir($this->hubDir . '/d');
        file_put_contents($this->hubDir . '/d/f.txt', 'x');
        $this->assertSame('not empty', $hub->receive(['path' => 'd', 'kind' => 'delete', 'expect' => 'dir'])['error']);

        file_put_contents($this->hubDir . '/plain', 'x');
        $this->assertSame('type clash', $hub->receive(['path' => 'plain', 'kind' => 'dir', 'expect' => hash('sha256', 'x')])['error']);
    }

    public function testANodeThePeerCanReachDoesNotPush(): void
    {
        [$agent, , $view] = $this->topology(false);
        file_put_contents($this->agentDir . '/f.txt', 'one');

        $r = $agent->run();

        $this->assertSame(0, $r['pushed']);
        $this->assertSame(1, $r['kept'], 'left for the peer to pull');
        $this->assertSame([], $view->puts);
    }
}
