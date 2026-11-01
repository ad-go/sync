<?php

declare(strict_types=1);

namespace AdGo\Sync\Tests;

use AdGo\Sync\Libraries\ClockMonitor;
use AdGo\Sync\Libraries\Options;

final class ClockTest extends TwoNodes
{
    private function monitor(?Node $n = null, array $opt = []): ClockMonitor
    {
        $n ??= $this->a;

        return new ClockMonitor($n->db, $opt === [] ? $n->k->opt : Options::fromArray($opt + ['node' => $n->name]));
    }

    public function testAResponseGivesTheOffsetAtTheMidpointOfTheRoundTrip(): void
    {
        $m = $this->monitor();
        $m->observeResponse('site2', 100.0, 100.2, 105.1);

        $c = $m->get('site2', 1_000_000);
        $this->assertEqualsWithDelta(5.0, $c['offset_s'], 0.001, 'the peer is 5 s ahead');
        $this->assertSame(200, $c['rtt_ms']);
    }

    public function testARequestTimestampGivesACoarseSignedOffset(): void
    {
        $m = $this->monitor();
        $m->observeRequest('site2', 1000, 997_000_000);   // the peer stamped 1000 s, we read 997 s

        $this->assertEqualsWithDelta(3.5, $m->get('site2', 997_000_000)['offset_s'], 0.001, 'plus the half second lost to truncation');
    }

    public function testTheSampleWithTheSmallestRoundTripWinsUntilItGoesStale(): void
    {
        $m = $this->monitor();
        $m->observe('site2', 1_000_000, 100_000, 10_000_000);
        $m->observe('site2', 3_000_000, 500_000, 20_000_000);   // a worse measurement: ignored

        $this->assertEqualsWithDelta(1.0, $m->get('site2', 20_000_000)['offset_s'], 0.001);

        $m->observe('site2', 3_000_000, 500_000, 10_000_000 + ClockMonitor::STALE_US + 1);   // the old one has expired

        $this->assertEqualsWithDelta(3.0, $m->get('site2', 10_000_000 + ClockMonitor::STALE_US + 1)['offset_s'], 0.001);
    }

    public function testAPeerIsUnreliableAboveClockMaxEitherWayAndUntilTheMeasurementExpires(): void
    {
        $m = $this->monitor();
        $this->assertFalse($m->unreliable('site2'), 'never measured: trusted');

        $m->observe('site2', 4_000_000, 1000, 1_000_000);
        $this->assertFalse($m->unreliable('site2', 1_000_000), '4 s is below the default clockMax of 5 s');

        $m->observe('site2', -6_000_000, 1000, 2_000_000);
        $this->assertTrue($m->unreliable('site2', 2_000_000), 'a clock 6 s behind is as bad as one 6 s ahead');
        $this->assertFalse($m->unreliable('site2', 2_000_000 + (ClockMonitor::EXPIRE_S + 1) * 1_000_000), 'an old measurement says nothing');
        $this->assertFalse($this->monitor(null, ['clockMax' => 0])->unreliable('site2', 2_000_000), 'clockMax 0 switches the guard off');
    }

    public function testMeasuringNeverBreaksARunWhenTheColumnsAreNotMigrated(): void
    {
        $this->a->db->query('ALTER TABLE sync_cursor DROP COLUMN clock_offset_us');
        $m = $this->monitor();

        $m->observe('site2', 9_000_000, 1000);
        $this->assertNull($m->get('site2'));
        $this->assertFalse($m->unreliable('site2'));
    }

    public function testATrustedClockLetsTheNewerEditWin(): void
    {
        $id = $this->a->insert(['name' => 'apple', 'note' => 'n0']);
        $this->sync();
        $bid = $this->b->idByUuid($this->a->uuidOf($id));

        $this->tick();
        $this->a->update($id, ['note' => 'from-A']);
        $this->tick(5000);
        $this->b->update($bid, ['note' => 'from-B']);

        $this->monitor()->observe('site2', 100_000, 1000);   // 0.1 s: fine
        $this->monitor($this->b)->observe('site1', -100_000, 1000);
        $this->sync();

        $this->assertSame('from-B', $this->a->row($id)['note']);
        $this->assertSame([], $this->a->conflicts('pending'));
    }

    public function testAnUntrustedClockEscalatesInsteadOfLettingTimeDecide(): void
    {
        $id = $this->a->insert(['name' => 'apple', 'note' => 'n0']);
        $this->sync();
        $bid = $this->b->idByUuid($this->a->uuidOf($id));

        $this->tick();
        $this->a->update($id, ['note' => 'from-A']);
        $this->tick(5000);
        $this->b->update($bid, ['note' => 'from-B']);

        $this->monitor()->observe('site2', 30_000_000, 1000);            // site2's clock is 30 s ahead of site1's
        $this->monitor($this->b)->observe('site1', -30_000_000, 1000);
        $this->sync();

        $this->assertCount(1, $this->a->conflicts('pending'), 'site1 does not guess');
        $this->assertSame('from-A', $this->a->row($id)['note'], 'nothing was overwritten');
        $this->assertCount(1, $this->b->conflicts('pending'));
        $this->assertSame('from-B', $this->b->row($bid)['note']);
    }

    public function testTimestampsWithinTheToleranceAreDecidedByTiebreakOnBothNodes(): void
    {
        $this->a = new Node('site1', 'site2', $this->tables(), ['clockTolerance' => 2.0]);
        $this->b = new Node('site2', 'site1', $this->tables(), ['clockTolerance' => 2.0]);

        $id = $this->a->insert(['name' => 'apple', 'note' => 'n0']);
        $this->sync();
        $bid = $this->b->idByUuid($this->a->uuidOf($id));

        $this->tick();
        $this->a->update($id, ['note' => 'from-A']);
        $this->tick(1_000_000);                                   // site2 wrote one second later
        $this->b->update($bid, ['note' => 'from-B']);
        $this->sync();

        $this->assertSame('from-A', $this->a->row($id)['note'], 'a second is within the tolerance: site1 ranks first');
        $this->assertSame('from-A', $this->b->row($bid)['note']);

        $this->tick(3_000_000);
        $this->a->update($id, ['note' => 'A-2']);
        $this->tick(3_000_000);                                   // clearly later
        $this->b->update($bid, ['note' => 'B-2']);
        $this->sync();

        $this->assertSame('B-2', $this->a->row($id)['note']);
        $this->assertSame('B-2', $this->b->row($bid)['note']);
    }
}
