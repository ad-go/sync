<?php

declare(strict_types=1);

namespace AdGo\Sync\Tests;

/** The scan walks the tables in chunks (deletions too) and a table can opt out of it. */
final class ScanOptionsTest extends TwoNodes
{
    protected function tables(): array
    {
        return ['items' => ['policy' => 'tolerant']];
    }

    public function testDeletionsAreFoundAcrossSeveralChunks(): void
    {
        $rows = [];

        for ($i = 1; $i <= 1300; $i++) {
            $rows[] = ['name' => 'n' . $i, 'note' => ''];
        }

        $this->a->db->table('items')->insertBatch($rows);
        $this->assertSame(1300, $this->a->k->scanner()->scan()['inserted']);

        $this->a->db->table('items')->whereIn('id', [3, 600, 1299])->delete();
        $this->assertSame(3, $this->a->k->scanner()->scan()['deleted']);
        $this->assertSame(0, $this->a->k->scanner()->scan()['deleted'], 'a deletion is journaled once');
    }
}
