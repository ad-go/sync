<?php

declare(strict_types=1);

namespace AdGo\Sync\Tests;

use AdGo\Sync\Libraries\ClockMonitor;
use AdGo\Sync\Libraries\NtpClock;
use AdGo\Sync\Libraries\Options;
use AdGo\Sync\Libraries\Support;
use Closure;
use RuntimeException;

final class NtpTest extends TwoNodes
{
    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sync-ntp-test-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        Support::correctWith(null);
        @unlink($this->file);
        parent::tearDown();
    }

    private static function stamp(float $t): string
    {
        $s = (int) floor($t);

        return pack('NN', $s + 2_208_988_800, (int) floor(($t - $s) * 4_294_967_296));
    }

    /** A server whose clock is $ahead seconds ahead of ours. */
    private static function server(float $ahead, int $mode = 4, int $stratum = 2, bool $echo = true): Closure
    {
        return static function (string $host, string $packet) use ($ahead, $mode, $stratum, $echo): string {
            $t = microtime(true) + $ahead;

            return chr(($mode & 7) | 0x18) . chr($stratum) . str_repeat("\0", 22)
                . ($echo ? substr($packet, 40, 8) : str_repeat("\1", 8)) . self::stamp($t) . self::stamp($t);
        };
    }

    public function testTheOffsetOfAServerIsMeasured(): void
    {
        $r = (new NtpClock($this->file, self::server(3.0)))->query('ntp.test');

        $this->assertEqualsWithDelta(3.0, $r['offset'], 0.05, 'the server is 3 s ahead: add 3 s to our clock');
        $this->assertLessThan(0.1, $r['rtt']);
    }

    public function testAnswersThatAreNotForUsAreRefused(): void
    {
        foreach ([self::server(1.0, 3), self::server(1.0, 4, 0), self::server(1.0, 4, 16), self::server(1.0, 4, 2, false), static fn () => 'short'] as $send) {
            try {
                (new NtpClock($this->file, $send))->query('ntp.test');
                $this->fail('an unusable answer was accepted');
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testTheMedianIgnoresAWildServerAndOneThatDoesNotAnswer(): void
    {
        $by = ['a' => self::server(1.0), 'b' => self::server(1.2), 'c' => self::server(900.0), 'd' => static function (): string {
            throw new RuntimeException('timeout');
        }];
        $r = (new NtpClock($this->file, static fn (string $h, string $p, float $t): string => $by[$h]($h, $p, $t)))->measure(array_keys($by));

        $this->assertSame(3, $r['answers']);
        $this->assertEqualsWithDelta(1.2, $r['offset'], 0.05, 'median of 1.0, 1.2 and 900');
        $this->assertSame('timeout', $r['servers']['d']);
    }

    public function testNoAnswerIsNotAMeasurement(): void
    {
        $r = (new NtpClock($this->file, static fn () => throw new RuntimeException('blocked')))->measure(['a', 'b']);

        $this->assertNull($r['offset']);
        $this->assertSame(0, $r['answers']);
    }

    public function testTheLastMeasurementIsKeptUntilItIsTooOld(): void
    {
        $n = new NtpClock($this->file);
        $this->assertNull($n->load(), 'never measured');

        $n->save(-2.5, 10_000_000);
        $this->assertEqualsWithDelta(-2.5, $n->load(40_000_000)['offset_s'], 0.000001);
        $this->assertSame(30, $n->load(40_000_000)['age_s']);
        $this->assertNull($n->load(10_000_000 + (NtpClock::FRESH_S + 1) * 1_000_000), 'an old measurement says nothing about the clock now');

        $n->save(100_000.0, 10_000_000);
        $this->assertNull($n->load(11_000_000), 'a day-sized offset is a broken server, not a broken clock');
    }

    public function testEventTimeIsTheLocalClockOnlyWhenTheCorrectionIsOn(): void
    {
        Support::freezeNow(50_000_000);
        $this->assertSame(50_000_000, Support::eventUs());
        $this->assertNull(Support::correctionS());

        Support::correctWith(static fn (): int => -10_000_000);
        $this->assertSame(40_000_000, Support::eventUs());
        $this->assertSame(50_000_000, Support::nowUs(), 'the clock itself is never touched');
        $this->assertEqualsWithDelta(-10.0, Support::correctionS(), 0.000001);
    }

    public function testACorrectedNodeWinsTheConflictItReallyWon(): void
    {
        $id = $this->a->insert(['name' => 'apple', 'note' => 'n0']);
        $this->sync();
        $bid = $this->b->idByUuid($this->a->uuidOf($id));

        // site1's clock runs 10 s ahead. site1 edits at real time 2 s (it reads 12 s), site2 at real time 5 s.
        Support::freezeNow(12_000_000);
        Support::correctWith(static fn (): int => -10_000_000);
        $this->a->update($id, ['note' => 'from-A']);

        Support::freezeNow(5_000_000);
        Support::correctWith(null);
        $this->b->update($bid, ['note' => 'from-B']);

        $this->sync();

        $this->assertSame('from-B', $this->a->row($id)['note'], 'the later edit wins although site1 wrote a larger raw time');
        $this->assertSame('from-B', $this->b->row($bid)['note']);
    }

    public function testWithoutTheCorrectionTheFastClockWins(): void
    {
        $id = $this->a->insert(['name' => 'apple', 'note' => 'n0']);
        $this->sync();
        $bid = $this->b->idByUuid($this->a->uuidOf($id));

        Support::freezeNow(12_000_000);
        $this->a->update($id, ['note' => 'from-A']);
        Support::freezeNow(5_000_000);
        $this->b->update($bid, ['note' => 'from-B']);
        $this->sync();

        $this->assertSame('from-A', $this->b->row($bid)['note'], 'the case the correction exists for');
    }

    public function testThePeersCorrectionIsTakenOutOfTheClockDifference(): void
    {
        $m = new ClockMonitor($this->a->db, Options::fromArray(['node' => 'site1', 'peerNode' => 'site2']));

        // site2 reads 10 s ahead of us but says it corrects its stamps by -10 s: its stamps are on real time
        $m->observeResponse('site2', 100.0, 100.2, 110.1, -10.0);
        $c = $m->get('site2', 1_000_000);

        $this->assertEqualsWithDelta(10.0, $c['offset_s'], 0.001);
        $this->assertEqualsWithDelta(-10.0, $c['ntp_s'], 0.001);
        $this->assertEqualsWithDelta(0.0, $c['effective_s'], 0.001);
        $this->assertFalse($m->unreliable('site2', 1_000_000), 'rows: the stamps agree');
        $this->assertTrue($m->unreliable('site2', 1_000_000, true), 'files still compare raw mtimes: the clocks are 10 s apart');
    }

    public function testOurOwnCorrectionCountsToo(): void
    {
        $m = new ClockMonitor($this->a->db, Options::fromArray(['node' => 'site1', 'peerNode' => 'site2']));
        $m->observeResponse('site2', 100.0, 100.2, 100.1);   // the clocks agree and the peer does not correct

        Support::correctWith(static fn (): int => -8_000_000);   // but our stamps are moved 8 s back
        $this->assertEqualsWithDelta(8.0, $m->get('site2', 1_000_000)['effective_s'], 0.001);
        $this->assertTrue($m->unreliable('site2', 1_000_000), 'the peer is now 8 s away from our stamps');
    }
}
