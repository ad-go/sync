<?php

declare(strict_types=1);

namespace AdGo\Sync\Tests;

/** A local change to a row that arrived from the peer leaves with a revision above the one that arrived. */
final class RevAboveAppliedTest extends TwoNodes
{
    protected function tables(): array
    {
        return ['items' => ['policy' => 'tolerant']];
    }

    public function testLocalDeleteOfAnAppliedRowOutranksTheAppliedRevision(): void
    {
        $id = $this->a->insert(['name' => 'x', 'note' => '']);
        $this->b->pullFrom($this->a);
        $this->assertCount(1, $this->b->all());

        $this->tick();
        $this->b->db->table('items')->where('name', 'x')->delete();
        $this->b->k->scanner()->scan();

        $last = array_values(array_filter($this->b->k->meta->changesSince(0, 50), static fn ($c) => $c['op'] === 'D'))[0];
        $this->assertGreaterThan(1, (int) $last['rev'], 'rev 1 would equal the revision of the insert that arrived');

        $this->a->pullFrom($this->b);
        $this->assertSame([], $this->a->all());
    }
}
