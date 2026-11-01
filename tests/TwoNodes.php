<?php

declare(strict_types=1);

namespace AdGo\Sync\Tests;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Config as DbConfig;
use AdGo\Sync\Database\SyncSchema;
use AdGo\Sync\Libraries\Kernel;
use AdGo\Sync\Libraries\Options;
use AdGo\Sync\Libraries\Support;

/** One simulated node: its own in-memory SQLite database + Kernel. */
final class Node
{
    public Kernel $k;
    public BaseConnection $db;

    /** @var list<array<string, mixed>> */
    public array $escalated = [];

    /**
     * @param array<string, mixed> $tables
     */
    public function __construct(public string $name, string $peer, array $tables, array $extra = [])
    {
        $this->db = DbConfig::connect([
            'DBDriver' => 'SQLite3',
            'database' => ':memory:',
            'DBDebug'  => true,
        ], false);

        $forge = DbConfig::forge($this->db);
        $forge->addField([
            'id'    => ['type' => 'INTEGER', 'auto_increment' => true],
            'name'  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'price' => ['type' => 'INTEGER', 'null' => true],
            'note'  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('items');
        SyncSchema::up($forge);

        $this->k = new Kernel($this->db, Options::fromArray([
            'node'     => $name,
            'peerNode' => $peer,
            'tiebreak' => ['site1', 'site2'],
            'tables'   => $tables,
        ] + $extra + ['clockTolerance' => 0]));
    }

    /** @param array<string, mixed> $row */
    public function insert(array $row): int
    {
        $this->db->table('items')->insert($row);
        $id = (int) $this->db->insertID();
        $this->k->scanner()->scan();

        return $id;
    }

    /** @param array<string, mixed> $row */
    public function update(int $id, array $row): void
    {
        $this->db->table('items')->where('id', $id)->update($row);
        $this->k->scanner()->scan();
    }

    public function delete(int $id): void
    {
        $this->db->table('items')->where('id', $id)->delete();
        $this->k->scanner()->scan();
    }

    /** @return array<string, mixed>|null */
    public function row(int $id): ?array
    {
        return $this->db->table('items')->where('id', $id)->get()->getRowArray();
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->db->table('items')->orderBy('name')->get()->getResultArray();
    }

    public function idByUuid(string $uuid): ?int
    {
        $m = $this->k->meta->findByUuid('items', $uuid);

        return $m === null || $m['row_id'] === null ? null : (int) $m['row_id'];
    }

    public function uuidOf(int $id): string
    {
        return (string) $this->k->meta->findByRow('items', $id)['uuid'];
    }

    /**
     * Pull everything $from has journaled (simulates sync:pull).
     *
     * @return list<array{outcome: string}>
     */
    public function pullFrom(self $from): array
    {
        $rec = $this->k->reconciler(function (array $c): void {
            $this->escalated[] = $c;
        });
        $out = [];
        $cur = $this->k->meta->cursor($from->name);

        foreach ($from->k->meta->changesSince($cur, 1000) as $change) {
            $out[] = $rec->apply($change);
            $cur   = $change['seq'];
        }

        $this->k->meta->setCursor($from->name, $cur);

        return $out;
    }

    public function resolver(?\AdGo\Sync\Libraries\PeerClient $peer = null): \AdGo\Sync\Libraries\ConflictResolver
    {
        return new \AdGo\Sync\Libraries\ConflictResolver($this->db, $this->k->opt, $this->k->meta, $this->k->reconciler(), $peer);
    }

    /** @return list<array<string, mixed>> */
    public function conflicts(?string $status = null): array
    {
        $b = $this->db->table('sync_conflicts');

        if ($status !== null) {
            $b->where('status', $status);
        }

        return $b->get()->getResultArray();
    }
}

abstract class TwoNodes extends \PHPUnit\Framework\TestCase
{
    protected Node $a;
    protected Node $b;

    /**
     * @return array<string, mixed>
     */
    protected function tables(): array
    {
        return ['items' => ['policy' => 'tolerant', 'sensitive_fields' => ['price']]];
    }

    protected function setUp(): void
    {
        Support::freezeNow(1_000_000);
        $this->a = new Node('site1', 'site2', $this->tables());
        $this->b = new Node('site2', 'site1', $this->tables());
    }

    protected function tearDown(): void
    {
        Support::freezeNow(null);
    }

    protected function tick(int $us = 1000): void
    {
        Support::freezeNow(Support::nowUs() + $us);
    }

    protected function sync(): void
    {
        $this->a->pullFrom($this->b);
        $this->b->pullFrom($this->a);
        $this->a->pullFrom($this->b);
        $this->b->pullFrom($this->a);
    }
}
