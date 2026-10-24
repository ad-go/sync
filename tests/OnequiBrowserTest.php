<?php

declare(strict_types=1);

namespace AdGo\Sync\Tests;

use AdGo\Sync\Libraries\OnequiBrowser;
use InvalidArgumentException;

final class OnequiBrowserTest extends TwoNodes
{
    private function browser(): OnequiBrowser
    {
        return new OnequiBrowser($this->a->db);
    }

    public function testTablesListsKnownTablesWithRowCounts(): void
    {
        $this->a->insert(['name' => 'apple', 'price' => 10]);
        $this->a->insert(['name' => 'pear', 'price' => 20]);

        $names = array_column($this->browser()->tables(), 'name');
        $this->assertContains('items', $names);

        $byName = array_combine($names, array_column($this->browser()->tables(), 'rows'));
        $this->assertSame(2, $byName['items']);
    }

    public function testExists(): void
    {
        $b = $this->browser();
        $this->assertTrue($b->exists('items'));
        $this->assertFalse($b->exists('no_such_table'));
    }

    public function testPrimaryKeyOfItemsIsId(): void
    {
        $this->assertSame(['id'], $this->browser()->primaryKey('items'));
    }

    public function testColumnsThrowsOnUnknownTable(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->browser()->columns('no_such_table');
    }

    public function testRowsFiltersSortsAndPaginates(): void
    {
        $this->a->insert(['name' => 'aaa-fruit', 'price' => 10]);
        $this->a->insert(['name' => 'bbb-fruit', 'price' => 30]);
        $this->a->insert(['name' => 'ccc-veg', 'price' => 20]);

        $b = $this->browser();

        $all = $b->rows('items', 1, 10, 'price', 'asc', []);
        $this->assertSame(3, $all['total']);
        $this->assertSame(['aaa-fruit', 'ccc-veg', 'bbb-fruit'], array_column($all['rows'], 'name'));

        $filtered = $b->rows('items', 1, 10, null, 'asc', ['name' => 'fruit']);
        $this->assertSame(2, $filtered['total']);

        $page1 = $b->rows('items', 1, 2, 'id', 'asc', []);
        $page2 = $b->rows('items', 2, 2, 'id', 'asc', []);
        $this->assertCount(2, $page1['rows']);
        $this->assertCount(1, $page2['rows']);
        $this->assertSame(3, $page1['total']);

        // An unknown filter column and an unknown sort column are silently ignored rather than erroring.
        $ignored = $b->rows('items', 1, 10, 'no_such_column', 'asc', ['no_such_column' => 'x']);
        $this->assertSame(3, $ignored['total']);
    }

    public function testInsertUpdateDeleteRoundTrip(): void
    {
        $b = $this->browser();

        $b->insert('items', ['name' => 'apple', 'price' => 10, 'extra_field_ignored' => 'x']);
        $row = $this->a->db->table('items')->where('name', 'apple')->get()->getRowArray();
        $this->assertNotNull($row);
        $this->assertSame(10, (int) $row['price']);

        $b->update('items', ['id' => $row['id']], ['price' => 15]);
        $this->assertSame(15, (int) $b->find('items', ['id' => $row['id']])['price']);

        $b->delete('items', ['id' => $row['id']]);
        $this->assertNull($b->find('items', ['id' => $row['id']]));
    }

    public function testUpdateWithoutPrimaryKeyIsRefused(): void
    {
        $b = $this->browser();
        $this->expectException(InvalidArgumentException::class);
        $b->update('items', [], ['price' => 1]);
    }

    public function testDeleteWithoutPrimaryKeyIsRefused(): void
    {
        $b = $this->browser();
        $this->expectException(InvalidArgumentException::class);
        $b->delete('items', []);
    }

    public function testRunSelectReturnsRowsAndWriteReturnsAffectedCount(): void
    {
        $this->a->insert(['name' => 'apple', 'price' => 10]);
        $b = $this->browser();

        $select = $b->run('SELECT name, price FROM items WHERE price > 5');
        $this->assertSame(['name', 'price'], $select['columns']);
        $this->assertCount(1, $select['rows']);

        $update = $b->run("UPDATE items SET price = 99 WHERE name = 'apple'");
        $this->assertSame([], $update['columns']);
        $this->assertSame(1, $update['affected']);
        $this->assertSame(99, (int) $b->find('items', ['name' => 'apple'])['price']);
    }

    public function testRunInvalidSqlThrows(): void
    {
        $b = $this->browser();
        $this->expectException(\RuntimeException::class);
        $b->run('SELECT * FROM no_such_table');
    }
}
