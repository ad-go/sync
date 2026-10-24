<?php

declare(strict_types=1);

namespace AdGo\Sync\Tests;

/** A synchronised table that is shared with per-node data: only the rows matching `where` travel. */
final class RowFilterTest extends TwoNodes
{
    protected function tables(): array
    {
        return ['items' => ['policy' => 'tolerant', 'where' => ['note' => 'shared']]];
    }

    public function testOnlyMatchingRowsAreTrackedAndTransferred(): void
    {
        $this->a->db->table('items')->insertBatch([
            ['name' => 'site setting', 'note' => 'shared'],
            ['name' => 'node-local value', 'note' => 'private'],
        ]);

        $r = $this->a->k->scanner()->scan();
        $this->assertSame(1, $r['inserted']);

        $this->b->pullFrom($this->a);
        $this->assertSame(['site setting'], array_column($this->b->all(), 'name'));
    }

    public function testARowThatStartsMatchingIsPickedUpAndOneThatStopsIsNotDeletedOnThePeer(): void
    {
        $this->a->db->table('items')->insert(['name' => 'x', 'note' => 'private']);
        $this->a->k->scanner()->scan();
        $this->assertSame([], $this->a->k->meta->changesSince(0, 10));

        $this->a->db->table('items')->where('name', 'x')->update(['note' => 'shared']);
        $this->assertSame(1, $this->a->k->scanner()->scan()['inserted']);
        $this->b->pullFrom($this->a);
        $this->assertCount(1, $this->b->all());

        $this->tick();
        $this->a->db->table('items')->where('name', 'x')->update(['note' => 'private']); // no longer shared
        $r = $this->a->k->scanner()->scan();
        $this->assertSame(0, $r['deleted'], 'leaving the filter is not a deletion');
        $this->b->pullFrom($this->a);
        $this->assertCount(1, $this->b->all());
    }

    public function testRehashAndSeedRespectTheFilter(): void
    {
        foreach ([$this->a, $this->b] as $n) {
            $n->db->table('items')->insert(['id' => 1, 'name' => 'shared one', 'note' => 'shared']);
            $n->db->table('items')->insert(['id' => 2, 'name' => 'local', 'note' => 'private']);
        }

        $this->assertSame(1, $this->a->k->scanner()->seed());
        $this->assertSame(0, $this->a->k->scanner()->rehash(), 'nothing changed since seeding');
        $this->assertNull($this->a->k->meta->findByRow('items', 2));
    }
}
