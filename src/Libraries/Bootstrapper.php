<?php

declare(strict_types=1);

namespace AdGo\Sync\Libraries;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

/**
 * One-time copy of the official packages' tables (Shield users, Settings...)
 * from the master node into THIS node, in dependency order, with foreign key
 * checks disabled while loading. After this they are excluded from the
 * recurring sync; editing users is governed by the auth write-lock.
 */
class Bootstrapper
{
    private const PAGE = 500;

    public function __construct(
        private readonly BaseConnection $db,
        private readonly Options $opt,
        private readonly PeerClient $peer,
    ) {
    }

    /**
     * @return array<string, int> table => rows copied
     */
    public function run(bool $force = false): array
    {
        $tables = array_values(array_filter($this->opt->bootstrapTables, fn (string $t) => $this->db->tableExists($t)));

        if (! $force) {
            foreach ($tables as $t) {
                if ($this->db->table($t)->countAllResults() > 0) {
                    throw new RuntimeException("Table '{$t}' on this node is not empty; use --force to overwrite it.");
                }
            }
        }

        $copied = [];

        $this->db->disableForeignKeyChecks();

        try {
            // Children first when emptying, parents first when filling.
            foreach (array_reverse($tables) as $t) {
                $this->db->table($t)->emptyTable();
            }

            foreach ($tables as $t) {
                $offset = 0;
                $copied[$t] = 0;

                do {
                    $page = $this->peer->bootstrapPage($t, $offset, self::PAGE);

                    if ($page['rows'] !== []) {
                        $this->db->table($t)->insertBatch($page['rows']);
                    }

                    $offset += self::PAGE;
                    $copied[$t] += count($page['rows']);
                } while ($offset < $page['total']);

                $this->resetSequence($t);
            }
        } finally {
            $this->db->enableForeignKeyChecks();
        }

        return $copied;
    }

    /**
     * Server side: one page of a table that is allowed to be bootstrapped.
     *
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function page(string $table, int $offset, int $limit): array
    {
        if (! in_array($table, $this->opt->bootstrapTables, true) || ! $this->db->tableExists($table)) {
            throw new RuntimeException('Table is not bootstrappable.');
        }

        $limit = max(1, min($limit, 1000));
        $first = $this->db->getFieldNames($table)[0] ?? null;
        $b     = $this->db->table($table);

        if ($first !== null) {
            $b->orderBy($first, 'ASC');
        }

        return [
            'rows'  => $b->limit($limit, max(0, $offset))->get()->getResultArray(),
            'total' => $this->db->table($table)->countAllResults(),
        ];
    }

    /** PostgreSQL does not advance sequences on explicit-id inserts. */
    private function resetSequence(string $table): void
    {
        if (! str_contains(strtolower($this->db->DBDriver), 'postgre')) {
            return;
        }

        try {
            $this->db->query("SELECT setval(pg_get_serial_sequence('\"{$table}\"', 'id'), COALESCE((SELECT MAX(id) FROM \"{$table}\"), 1))");
        } catch (Throwable) {
            // table without a serial id column
        }
    }
}
