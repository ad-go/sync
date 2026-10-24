<?php

declare(strict_types=1);

namespace AdGo\Sync\Tests;

use PHPUnit\Framework\TestCase;
use AdGo\Sync\Libraries\Options;
use AdGo\Sync\Libraries\Reachability;

/** internet / lan / punch / relay: classification and the comparison of the observed mode with the configured one. */
final class ReachabilityTest extends TestCase
{
    private function opt(array $a): Options
    {
        return Options::fromArray($a + ['node' => 'site1', 'peerNode' => 'site2', 'replayWindow' => 300]);
    }

    /** @param array<string, callable(array): array> $peers endpoint handlers per peer name, keyed "peer/endpoint" */
    private function check(Options $o, array $handlers, array $dns = []): Reachability
    {
        $call = static function (string $peer, string $endpoint, array $params) use ($handlers): array {
            $h = $handlers["{$peer}/{$endpoint}"] ?? throw new \RuntimeException("no route to {$peer}/{$endpoint}");

            return $h($params);
        };

        return new Reachability($o, null, $call, static fn (string $host): array => $dns[$host] ?? []);
    }

    private function pong(string $node, array $extra = []): callable
    {
        return static fn (array $p): array => $extra + ['node' => $node, 'time' => microtime(true), 'ip' => '203.0.113.9', 'relay' => false, 'peers' => []];
    }

    public function testAddressClassification(): void
    {
        foreach (['10.0.0.250', '172.16.5.1', '192.168.1.10', '127.0.0.1', '169.254.1.1', '100.64.0.1', '100.127.255.254', '::1', 'fd00::1', 'fe80::1'] as $ip) {
            $this->assertTrue(Reachability::isPrivateIp($ip), $ip);
        }

        foreach (['8.8.8.8', '203.0.113.9', '100.63.255.255', '100.128.0.0', '2001:4860:4860::8888'] as $ip) {
            $this->assertFalse(Reachability::isPrivateIp($ip), $ip);
        }

        $r = $this->check($this->opt([]), [], ['intern.lan' => ['10.0.0.5'], 'mixed.example' => ['8.8.8.8', '10.0.0.5'], 'pub.example' => ['203.0.113.7']]);
        $this->assertSame('lan', $r->classifyUrl('http://intern.lan:8080/x'));
        $this->assertSame('lan', $r->classifyUrl('https://mixed.example'), 'one private address is enough: only reachable from the LAN');
        $this->assertSame('internet', $r->classifyUrl('https://pub.example'));
        $this->assertSame('internet', $r->classifyUrl('https://8.8.8.8'));
        $this->assertSame('unknown', $r->classifyUrl('https://does-not-resolve.example'));
    }

    public function testADialInNodeThatPeersCanReachIsOk(): void
    {
        $o = $this->opt(['peers' => ['site2' => ['url' => 'https://hub.example']], 'selfUrl' => 'https://me.example']);
        $r = $this->check($o, ['site2/ping' => $this->pong('site2'), 'site2/probe' => fn () => ['ok' => true, 'rtt_ms' => 40]], ['hub.example' => ['203.0.113.1'], 'me.example' => ['203.0.113.2']]);

        $x = $r->run()['peers'][0];
        $this->assertSame('ok', $x['verdict']);
        $this->assertSame('internet', $x['declared']);
        $this->assertSame('internet', $x['observed']);
        $this->assertTrue($x['inbound']['ok']);
    }

    public function testALanNodeIsClassifiedAsLan(): void
    {
        $o = $this->opt(['peers' => ['site2' => ['url' => 'http://10.0.0.5']], 'selfUrl' => 'http://10.0.0.6']);
        $x = $this->check($o, ['site2/ping' => $this->pong('site2'), 'site2/probe' => fn () => ['ok' => true, 'rtt_ms' => 3]])->run()['peers'][0];

        $this->assertSame('lan', $x['peerClass']);
        $this->assertSame('lan', $x['observed']);
        $this->assertSame('ok', $x['verdict']);
    }

    public function testADialInNodeThatThePeerCannotReachFailsAndSaysWhatToDo(): void
    {
        $o = $this->opt(['peers' => ['site2' => ['url' => 'https://hub.example']], 'selfUrl' => 'https://me.example']);
        $r = $this->check($o, ['site2/ping' => $this->pong('site2'), 'site2/probe' => fn () => ['ok' => false, 'rtt_ms' => 0, 'error' => 'connection timed out']], ['hub.example' => ['203.0.113.1']]);

        $x = $r->run()['peers'][0];
        $this->assertSame('fail', $x['verdict']);
        $this->assertSame('punch', $x['observed'], 'outbound only: that is a punch / NAT node');
        $this->assertStringContainsString('sync.passive = true', implode(' ', $x['notes']));
    }

    public function testAPassiveNodeIsPunchAndIsNotProbedInbound(): void
    {
        $o = $this->opt(['passive' => true, 'peers' => ['site2' => ['url' => 'https://hub.example']]]);
        $r = $this->check($o, ['site2/ping' => $this->pong('site2')], ['hub.example' => ['203.0.113.1']]);

        $x = $r->run()['peers'][0];
        $this->assertSame('ok', $x['verdict']);
        $this->assertSame('punch', $x['observed']);
        $this->assertNull($x['inbound']);
    }

    public function testAnUnreachablePeerFailsAndAWrongNodeNameIsCaught(): void
    {
        $o = $this->opt(['peers' => ['site2' => ['url' => 'https://hub.example']]]);

        $x = $this->check($o, [])->run()['peers'][0];
        $this->assertSame('fail', $x['verdict']);
        $this->assertSame('offline', $x['observed']);

        $x = $this->check($o, ['site2/ping' => $this->pong('somebody-else')])->run()['peers'][0];
        $this->assertSame('fail', $x['verdict']);
        $this->assertStringContainsString("answered as 'somebody-else'", implode(' ', $x['notes']));
    }

    public function testClockSkewAgainstTheReplayWindow(): void
    {
        $o = $this->opt(['peers' => ['site2' => ['url' => 'https://hub.example']], 'passive' => true]);
        $skew = fn (float $s) => $this->check($o, ['site2/ping' => fn () => ['node' => 'site2', 'time' => microtime(true) + $s, 'ip' => '1.2.3.4']])->run()['peers'][0]['verdict'];

        $this->assertSame('ok', $skew(0.5));
        $this->assertSame('warn', $skew(3.0), 'above clockWarn (2 s): newest-wins may pick the older edit');
        $this->assertSame('warn', $skew(200.0), 'above clockMax and half of the 300 s window');
        $this->assertSame('fail', $skew(400.0), 'beyond the window the signatures are rejected');
        $this->assertSame('fail', $skew(-400.0));
    }

    public function testPassivePeerSilenceIsReportedFromTheCursor(): void
    {
        $o = $this->opt(['peers' => ['app' => ['passive' => true], 'site2' => ['url' => 'https://x.example']], 'relay' => true]);
        $x = $this->check($o, ['site2/ping' => $this->pong('site2')])->run('app')['peers'][0];

        $this->assertSame('warn', $x['verdict'], 'no database here, so no contact is known');
        $this->assertNull($x['outbound']);
    }

    public function testARelayThatReachesAPeerIsUsedWhenTheDirectLinkIsDown(): void
    {
        // site1 has site2 (hub, relay) and site3 as peers; site3 is down directly, the hub still reaches it
        $o = $this->opt(['peers' => ['site2' => ['url' => 'https://hub.example'], 'site3' => ['url' => 'https://third.example']], 'passive' => true]);
        $r = $this->check($o, [
            'site2/ping'  => $this->pong('site2', ['relay' => true, 'peers' => ['site1' => ['passive' => true, 'seen' => 5], 'site3' => ['passive' => false, 'seen' => null]]]),
            'site2/probe' => function (array $p): array {
                $this->assertSame('site3', $p['peer'] ?? null);

                return ['ok' => true, 'rtt_ms' => 55, 'skew_s' => 0.1, 'error' => ''];
            },
        ]);

        $res = $r->run();
        $x   = array_values(array_filter($res['peers'], static fn ($p) => $p['peer'] === 'site3'))[0];
        $this->assertSame('relay', $x['observed']);
        $this->assertSame('site2', $x['via']);
        $this->assertSame('warn', $x['verdict'], 'it works, one hop longer');
    }

    public function testNodesOnlyAHubCanReachAreListedAndProbedThroughIt(): void
    {
        // the agent knows only the hub; the hub reports beta1 (live) and the agent itself (passive)
        $o = $this->opt(['node' => 'app', 'passive' => true, 'peerNode' => 'site2', 'peers' => ['site2' => ['url' => 'https://hub.example']]]);
        $r = $this->check($o, [
            'site2/ping'  => $this->pong('site2', ['relay' => true, 'peers' => ['site1' => ['passive' => false, 'seen' => null], 'app' => ['passive' => true, 'seen' => 3]]]),
            'site2/probe' => fn (array $p): array => ($p['peer'] ?? '') === 'site1' ? ['ok' => true, 'rtt_ms' => 120, 'skew_s' => -0.4, 'error' => ''] : ['ok' => false, 'error' => 'unexpected'],
        ]);

        $res = $r->run();
        $this->assertCount(1, $res['relayed'], 'itself is not listed');
        $this->assertSame('site1', $res['relayed'][0]['node']);
        $this->assertSame('site2', $res['relayed'][0]['via']);
        $this->assertTrue($res['relayed'][0]['ok']);
        $this->assertSame('ok', $res['verdict']);
    }

    public function testTheServerSideNeverCallsAUrlFromTheRequest(): void
    {
        $hub = $this->opt(['node' => 'site2', 'peerNode' => 'site1', 'relay' => true, 'peers' => ['site1' => ['url' => 'https://beta1.example'], 'app' => ['passive' => true]]]);
        $seen = [];
        $call = function (string $peer, string $endpoint, array $params) use (&$seen): array {
            $seen[] = $peer;

            return ['node' => $peer, 'time' => microtime(true)];
        };
        $r = new Reachability($hub, null, $call);

        $this->assertTrue($r->probeBack('site1', '', $call)['ok'], 'called back at its configured URL');
        $this->assertTrue($r->probeBack('app', 'site1', $call)['ok'], 'a relay check for the passive node');
        $this->assertSame(['site1', 'site1'], $seen);

        $this->assertFalse($r->probeBack('app', '', $call)['ok'], 'a passive peer cannot be called back');
        $this->assertFalse($r->probeBack('site1', 'site1', $call)['ok'], 'not through itself');
        $this->assertFalse($r->probeBack('site1', 'evil.example', $call)['ok'], 'only configured peers can be named');
        $this->assertFalse($r->probeBack('app', 'app', $call)['ok']);

        $pong = $r->pingAnswer('198.51.100.4');
        $this->assertSame('site2', $pong['node']);
        $this->assertTrue($pong['relay']);
        $this->assertSame(['site1', 'app'], array_keys($pong['peers']));
        $this->assertTrue($pong['peers']['app']['passive']);
    }
}
