<?php

declare(strict_types=1);

namespace AdGo\Sync\Tests;

use CodeIgniter\Database\Config as DbConfig;

final class RehashTest extends TwoNodes
{
    private function addColumnEverywhere(): void
    {
        foreach ([$this->a, $this->b] as $n) {
            DbConfig::forge($n->db)->addColumn('items', ['image' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true]]);
        }
    }

    public function testWithoutRehashEveryRowLooksChangedAfterAddingAColumn(): void
    {
        $this->a->insert(['name' => 'one']);
        $this->a->insert(['name' => 'two']);
        $this->sync();
        $before = count($this->a->k->meta->changesSince(0, 100));

        $this->addColumnEverywhere();
        $r = $this->a->k->scanner()->scan();

        $this->assertSame(2, $r['updated']);
        $this->assertSame($before + 2, count($this->a->k->meta->changesSince(0, 100)));
    }

    public function testRehashRefreshesTheRecordedStateWithoutJournalingAnything(): void
    {
        $this->a->insert(['name' => 'one']);
        $this->a->insert(['name' => 'two']);
        $this->sync();
        $this->addColumnEverywhere();
        $journal = count($this->a->k->meta->changesSince(0, 100));
        $rev     = (int) $this->a->k->meta->findByRow('items', 1)['rev'];

        $this->assertSame(2, $this->a->k->scanner()->rehash());
        $this->assertSame(0, $this->a->k->scanner()->rehash(), 'idempotent');

        $r = $this->a->k->scanner()->scan();
        $this->assertSame(['inserted' => 0, 'updated' => 0, 'deleted' => 0, 'deferred' => 0], $r);
        $this->assertSame($journal, count($this->a->k->meta->changesSince(0, 100)));
        $this->assertSame($rev, (int) $this->a->k->meta->findByRow('items', 1)['rev']);

        // and the new column then travels like any other field
        $this->tick();
        $this->a->update(1, ['image' => 'items/x.jpg']);
        $this->b->pullFrom($this->a);
        $this->assertSame('items/x.jpg', $this->b->row(1)['image']);
        $this->assertSame([], $this->b->conflicts());
    }

    public function testRehashLeavesUntrackedRowsToTheNextScan(): void
    {
        $this->a->db->table('items')->insert(['name' => 'never scanned']);

        $this->assertSame(0, $this->a->k->scanner()->rehash());
        $this->assertSame(1, $this->a->k->scanner()->scan()['inserted']);
    }
}
