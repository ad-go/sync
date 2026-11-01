<?php

declare(strict_types=1);

namespace AdGo\Sync\Tests;

require_once __DIR__ . '/TwoNodes.php';

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Config as DbConfig;
use AdGo\Sync\Database\SyncSchema;
use AdGo\Sync\Libraries\Kernel;
use AdGo\Sync\Libraries\Options;
use AdGo\Sync\Libraries\Support;

/** A table that has another physical name on one node (`table`), and `where` with a list of values. */
final class TableAliasTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        Support::freezeNow(1_000_000);
    }

    protected function tearDown(): void
    {
        Support::freezeNow(null);
    }

    private function kernel(string $node, string $peer, string $physical, array $cfg, ?BaseConnection &$db = null): Kernel
    {
        $db    = DbConfig::connect(['DBDriver' => 'SQLite3', 'database' => ':memory:', 'DBDebug' => true], false);
        $forge = DbConfig::forge($db);
        $forge->addField([
            'id'    => ['type' => 'INTEGER', 'auto_increment' => true],
            'class' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'name'  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable($physical);
        SyncSchema::up($forge);

        return new Kernel($db, Options::fromArray(['node' => $node, 'peerNode' => $peer, 'tiebreak' => ['site1', 'site2'], 'tables' => ['wms_items' => $cfg]]));
    }

    public function testAnotherPhysicalTableNameOnTheOtherNode(): void
    {
        $a = $this->kernel('site1', 'site2', 'products', ['table' => 'products'], $dbA);
        $b = $this->kernel('site2', 'site1', 'wms_mirror_products', [], $dbB);
        // the wire name is the key ('wms_items'); B has no `table`, so its physical table is the key... which does not exist:
        $dbB->query('ALTER TABLE wms_mirror_products RENAME TO wms_items');

        $dbA->table('products')->insert(['name' => 'one']);
        $a->scanner()->scan();

        $rec = $b->reconciler(static function (): void {
        });

        foreach ($a->meta->changesSince(0, 100) as $c) {
            $this->assertSame('wms_items', $c['table'], 'the journal carries the wire name');
            $rec->apply($c);
        }

        $this->assertSame('one', $dbB->table('wms_items')->get()->getRowArray()['name']);

        // and the other way: B changes it, A applies it to ITS physical table
        Support::freezeNow(Support::nowUs() + 1000);
        $dbB->table('wms_items')->update(['name' => 'two']);
        $b->scanner()->scan();
        $recA = $a->reconciler(static function (): void {
        });

        foreach ($b->meta->changesSince(0, 100) as $c) {
            $recA->apply($c);
        }

        $this->assertSame('two', $dbA->table('products')->get()->getRowArray()['name']);
        $this->assertSame('products', Options::fromArray(['node' => 'x', 'peerNode' => 'y', 'tables' => ['t' => ['table' => 'products']]])->table('t')['table']);
        $this->assertSame('t', Options::fromArray(['node' => 'x', 'peerNode' => 'y', 'tables' => ['t' => []]])->table('t')['table']);
    }

    public function testWhereWithAListTakesOnlyThoseRows(): void
    {
        $k = $this->kernel('site1', 'site2', 'wms_items', ['where' => ['class' => ['A', 'B']]], $db);

        foreach (['A', 'B', 'C', 'A'] as $i => $cls) {
            $db->table('wms_items')->insert(['class' => $cls, 'name' => "r$i"]);
        }

        $k->scanner()->scan();

        $this->assertCount(3, $k->meta->changesSince(0, 100), 'the rows of class C are not tracked');
    }

    /** A column that only the peer has (`ignore_fields`) is neither sent nor applied. */
    public function testIgnoredColumnOfThePeerIsNotApplied(): void
    {
        $mk = function (string $node, string $peer, bool $extra, array $cfg): array {
            $db    = DbConfig::connect(['DBDriver' => 'SQLite3', 'database' => ':memory:', 'DBDebug' => true], false);
            $forge = DbConfig::forge($db);
            $f     = ['id' => ['type' => 'INTEGER', 'auto_increment' => true], 'name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true]];

            if ($extra) {
                $f['portal_only'] = ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true];
            }

            $forge->addField($f);
            $forge->addKey('id', true);
            $forge->createTable('customers');
            SyncSchema::up($forge);

            return [new Kernel($db, Options::fromArray(['node' => $node, 'peerNode' => $peer, 'tiebreak' => ['site1', 'site2'], 'tables' => ['customers' => $cfg]])), $db];
        };
        // A (the portal) has `portal_only`; B (the WMS) does not and lists it in ignore_fields
        [$a, $dbA] = $mk('site1', 'site2', true, ['ignore_fields' => []]);
        [$b, $dbB] = $mk('site2', 'site1', false, ['ignore_fields' => ['portal_only']]);

        $dbA->table('customers')->insert(['name' => 'one', 'portal_only' => 'x']);
        $a->scanner()->scan();
        $rec = $b->reconciler(static function (): void {
        });

        foreach ($a->meta->changesSince(0, 100) as $c) {
            $rec->apply($c);
        }

        $this->assertSame('one', $dbB->table('customers')->get()->getRowArray()['name']);
        // what was applied is also what the next scan reads back: nothing to journal (no ping-pong)
        $this->assertSame(0, $b->scanner()->scan()['updated']);
    }
}
