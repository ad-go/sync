<?php

declare(strict_types=1);

namespace AdGo\Sync\Libraries;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/**
 * Reads and writes application rows through the query builder (never through
 * Models, so applying remote data cannot re-trigger local capture) and
 * translates foreign keys between local ids and uuids.
 */
class RowGateway
{
    public function __construct(
        private readonly BaseConnection $db,
        private readonly Options $opt,
        private readonly MetaStore $meta,
    ) {
    }

    /**
     * Current local row, FKs translated to uuids, ignored fields removed,
     * primary key removed (it is local-only).
     *
     * @return array<string, mixed>|null
     */
    public function export(string $table, int|string $rowId): ?array
    {
        $cfg = $this->cfg($table);
        $row = $this->db->table($cfg['table'])->where($cfg['pk'], $rowId)->get()->getRowArray();

        return $row === null ? null : $this->toWire($table, $row);
    }

    /** @var array<string, array<string, string>> parent table => local id => uuid (only hits are cached; uuids never change) */
    private array $parentUuids = [];

    /**
     * Resolves the parent uuids of a whole page of rows with one query per parent table, so that toWire() does
     * not need one lookup per row. Call forgetParents() when a scan starts.
     *
     * @param list<array<string, mixed>> $rows raw local rows
     */
    public function preloadParents(string $table, array $rows): void
    {
        foreach ($this->cfg($table)['fk'] as $col => $parent) {
            $need = [];

            foreach ($rows as $r) {
                if (isset($r[$col]) && $r[$col] !== '' && ! isset($this->parentUuids[$parent][(string) $r[$col]])) {
                    $need[(string) $r[$col]] = $r[$col];
                }
            }

            foreach ($this->meta->lightByRows($parent, array_values($need)) as $id => $m) {
                $this->parentUuids[$parent][$id] = (string) $m['uuid'];
            }
        }
    }

    public function forgetParents(): void
    {
        $this->parentUuids = [];
    }

    /**
     * @param array<string, mixed> $row raw local row
     *
     * @return array<string, mixed>
     */
    public function toWire(string $table, array $row): array
    {
        $cfg = $this->cfg($table);
        unset($row[$cfg['pk']]);

        foreach ($cfg['ignore_fields'] as $f) {
            unset($row[$f]);
        }

        foreach ($cfg['fk'] as $col => $parent) {
            if (isset($row[$col]) && $row[$col] !== '') {
                $uuid = $this->parentUuids[$parent][(string) $row[$col]] ?? $this->meta->findByRow($parent, $row[$col])['uuid'] ?? null;

                if ($uuid === null) {
                    throw new RuntimeException("Row {$parent}#{$row[$col]} referenced by {$table}.{$col} has no sync metadata yet.");
                }

                $row[$col] = $uuid;
            }
        }

        ksort($row);

        return $row;
    }

    /**
     * Wire row -> values for the local table. Throws MissingParent when a
     * referenced parent has not arrived yet (the puller then retries later).
     *
     * @param array<string, mixed> $wire
     *
     * @return array<string, mixed>
     */
    public function fromWire(string $table, array $wire): array
    {
        $cfg = $this->cfg($table);

        // only columns this table has, never its primary key (local-only): a peer cannot choose ids or add fields
        $known = array_flip($this->db->getFieldNames($cfg['table']));

        if (array_diff_key($wire, $known) !== []) {
            $this->db->resetDataCache(); // a column may have been added since the list was read
            $known = array_flip($this->db->getFieldNames($cfg['table']));
        }

        $wire = array_intersect_key($wire, $known);
        unset($wire[$cfg['pk']]);

        // ignored columns are neither sent nor applied (the peer may have columns this table does not have)
        foreach ($cfg['ignore_fields'] as $f) {
            unset($wire[$f]);
        }

        foreach ($cfg['fk'] as $col => $parent) {
            if (isset($wire[$col]) && $wire[$col] !== '') {
                $m = $this->meta->findByUuid($parent, (string) $wire[$col]);

                if ($m === null || $m['row_id'] === null) {
                    throw new MissingParent("{$parent} {$wire[$col]} not present yet for {$table}.{$col}");
                }

                $wire[$col] = $m['row_id'];
            }
        }

        return $wire;
    }

    /**
     * @param array<string, mixed> $wire
     */
    public function insert(string $table, array $wire): int|string
    {
        $this->db->table($this->cfg($table)['table'])->insert($this->fromWire($table, $wire));

        return $this->db->insertID();
    }

    /**
     * @param array<string, mixed> $wire
     */
    public function update(string $table, int|string $rowId, array $wire): void
    {
        $cfg = $this->cfg($table);
        $this->db->table($cfg['table'])->where($cfg['pk'], $rowId)->update($this->fromWire($table, $wire));
    }

    public function delete(string $table, int|string $rowId): void
    {
        $cfg = $this->cfg($table);
        $this->db->table($cfg['table'])->where($cfg['pk'], $rowId)->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function cfg(string $table): array
    {
        $cfg = $this->opt->table($table);

        if ($cfg === null) {
            throw new RuntimeException("Table '{$table}' is not configured for sync.");
        }

        return $cfg;
    }
}
