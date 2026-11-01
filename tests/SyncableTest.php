<?php

declare(strict_types=1);

namespace AdGo\Sync\Tests;

use CodeIgniter\Model;
use AdGo\Sync\Libraries\Kernel;
use AdGo\Sync\Traits\Syncable;

final class SyncableTest extends TwoNodes
{
    protected function setUp(): void
    {
        parent::setUp();
        Kernel::use($this->a->k);
    }

    protected function tearDown(): void
    {
        Kernel::use(null);
        parent::tearDown();
    }

    private function model(): Model
    {
        return new class ($this->a->db) extends Model {
            use Syncable;

            protected $table          = 'items';
            protected $allowedFields  = ['name', 'price', 'note'];
            protected $skipValidation = true;
        };
    }

    public function testModelWritesAreCapturedWithoutAnyScan(): void
    {
        $m  = $this->model();
        $id = $m->insert(['name' => 'apple', 'price' => 10]);

        $meta = $this->a->k->meta->findByRow('items', $id);
        $this->assertNotNull($meta);
        $this->assertSame(1, (int) $meta['rev']);

        $m->update($id, ['price' => 11]);
        $this->assertSame(2, (int) $this->a->k->meta->findByRow('items', $id)['rev']);

        $m->delete($id, true);
        $tomb = $this->a->k->meta->findByUuid('items', $meta['uuid']);
        $this->assertNull($tomb['row_id']);
        $this->assertNotNull($tomb['deleted_at']);

        $ops = array_column($this->a->k->meta->changesSince(0, 10), 'op');
        $this->assertSame(['I', 'U', 'D'], $ops);
    }

    public function testCapturedChangesReachThePeerExactlyLikeScannedOnes(): void
    {
        $id = $this->model()->insert(['name' => 'pear', 'price' => 5]);
        $this->b->pullFrom($this->a);

        $bid = $this->b->idByUuid($this->a->uuidOf($id));
        $this->assertSame('pear', $this->b->row($bid)['name']);

        // the scanner must see nothing new: capture already recorded the same hash
        $this->assertSame(['inserted' => 0, 'updated' => 0, 'deleted' => 0, 'deferred' => 0], $this->a->k->scanner()->scan());
    }

    public function testSyncMetadataRollsBackWithTheRow(): void
    {
        $m = $this->model();

        try {
            $this->a->db->transBegin();
            $m->insert(['name' => 'ghost']);
            $this->a->db->transRollback();
        } catch (\Throwable) {
        }

        $this->assertSame(0, $this->a->db->table('items')->countAllResults());
        $this->assertSame(0, $this->a->db->table('sync_row_meta')->countAllResults(), 'no orphan uuid');
        $this->assertSame(0, $this->a->db->table('sync_changes')->countAllResults());
    }

    public function testUnconfiguredModelsAreIgnored(): void
    {
        $this->a->db->query('CREATE TABLE other (id INTEGER PRIMARY KEY AUTOINCREMENT, v TEXT)');
        $m = new class ($this->a->db) extends Model {
            use Syncable;

            protected $table          = 'other';
            protected $allowedFields  = ['v'];
            protected $skipValidation = true;
        };
        $m->insert(['v' => 'x']);

        $this->assertSame(0, $this->a->db->table('sync_row_meta')->countAllResults());
    }
}
