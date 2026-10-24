<?php

declare(strict_types=1);

namespace AdGo\Sync\Tests;

/** A table with scan off is not read by sync:scan. */
final class ScanOffTest extends TwoNodes
{
    protected function tables(): array
    {
        return ['items' => ['policy' => 'tolerant', 'scan' => false]];
    }

    public function testATableWithScanOffIsNotRead(): void
    {
        $this->a->db->table('items')->insert(['name' => 'raw', 'note' => '']);

        $this->assertSame(0, $this->a->k->scanner()->scan()['inserted']);
    }
}
