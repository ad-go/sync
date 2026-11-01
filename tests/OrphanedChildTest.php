<?php

declare(strict_types=1);

namespace AdGo\Sync\Tests;

/** A child whose parent was deleted first must still be removable by a remote delete. */
final class OrphanedChildTest extends TwoNodes
{
    protected function tables(): array
    {
        return ['items' => ['policy' => 'tolerant', 'fk' => ['price' => 'items']]]; // price = id of the parent item
    }

    public function testDeletingAChildWhoseParentIsGoneDoesNotFail(): void
    {
        $p = $this->a->insert(['name' => 'parent', 'price' => null]);
        $c = $this->a->insert(['name' => 'child', 'price' => $p]);
        $this->b->pullFrom($this->a);
        $this->assertCount(2, $this->b->all());

        $this->tick();
        $this->b->db->table('items')->where('name', 'child')->update(['note' => 'edited here']);
        $this->b->k->scanner()->scan();
        $this->b->db->table('items')->where('name', 'parent')->delete();
        $this->b->k->scanner()->scan();

        $this->tick();
        $this->a->db->table('items')->where('id', $c)->delete();
        $this->a->k->scanner()->scan();

        $this->b->pullFrom($this->a);
        $this->assertSame([], array_column($this->b->all(), 'name'));
    }
}
