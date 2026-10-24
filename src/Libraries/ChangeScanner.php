<?php

declare(strict_types=1);

namespace AdGo\Sync\Libraries;

use CodeIgniter\Database\BaseConnection;
use Generator;
use RuntimeException;

/**
 * Detects local writes that bypassed the Syncable trait (query builder, raw
 * SQL, batch operations, other tools) by comparing every row of the synced
 * tables with the hash recorded in sync_row_meta.
 *
 * Also seeds metadata for rows that existed before the package was installed
 * (seed(): deterministic uuids, so both nodes agree without any traffic).
 */
class ChangeScanner
{
    private const CHUNK = 500;

    public function __construct(
        private readonly BaseConnection $db,
        private readonly Options $opt,
        private readonly MetaStore $meta,
        private readonly RowGateway $rows,
    ) {
    }

    /**
     * @return array{inserted: int, updated: int, deleted: int, deferred: int}
     */
    public function scan(): array
    {
        $stat = ['inserted' => 0, 'updated' => 0, 'deleted' => 0, 'deferred' => 0];

        $this->rows->forgetParents();

        foreach (array_keys($this->opt->tables) as $table) {
            if (! $this->opt->table($table)['scan']) {
                continue;
            }

            $pk = $this->opt->table($table)['pk'];

            foreach ($this->chunks($table) as [$chunk, $known]) {
                foreach ($chunk as $raw) {
                    $id = $raw[$pk];

                    try {
                        $wire = $this->rows->toWire($table, $raw);
                    } catch (RuntimeException) {
                        $stat['deferred']++; // parent not tracked yet; next scan

                        continue;
                    }

                    $meta = $known[(string) $id] ?? null;

                    if ($meta === null) {
                        Support::tx($this->db, fn () => $this->meta->recordLocal($table, $id, 'I', $wire));
                        $stat['inserted']++;
                    } elseif ($meta['row_hash'] !== Support::hashRow($wire)) {
                        Support::tx($this->db, fn () => $this->meta->recordLocal($table, $id, 'U', $wire));
                        $stat['updated']++;
                    }
                }
            }

            // Rows with metadata but no longer in the table were deleted locally.
            // (in chunks: a table of millions of rows must not be held in memory)
            $phys = $this->opt->table($table)['table'];
            $last = null;

            while (true) {
                $b = $this->db->table('sync_row_meta')->select('row_id')->where('table_name', $table)->where('row_id IS NOT NULL');

                if ($last !== null) {
                    $b->where('row_id >', $last);
                }

                $ids = array_column($b->orderBy('row_id', 'ASC')->limit(self::CHUNK)->get()->getResultArray(), 'row_id');

                if ($ids === []) {
                    break;
                }

                $present = array_flip(array_map('strval', array_column($this->db->table($phys)->select($pk)->whereIn($pk, $ids)->get()->getResultArray(), $pk)));

                foreach ($ids as $id) {
                    if (! isset($present[(string) $id])) {
                        Support::tx($this->db, fn () => $this->meta->recordLocal($table, $id, 'D', null));
                        $stat['deleted']++;
                    }
                }

                $last = end($ids);
            }
        }

        return $stat;
    }

    /**
     * After a column was added to (or dropped from) a synchronised table: re-records how every tracked row looks
     * now WITHOUT counting it as a change (no revision bump, nothing journaled). Otherwise the next scan would see
     * every row as modified and journal all of them. Only correct when the new column holds the same value on
     * both nodes (NULL / its default); run it on each node right after the migration, before the next sync:pull.
     *
     * @return int rows whose recorded state was refreshed
     */
    public function rehash(): int
    {
        $n = 0;

        $this->rows->forgetParents();

        foreach (array_keys($this->opt->tables) as $table) {
            $pk = $this->opt->table($table)['pk'];

            foreach ($this->chunks($table) as [$chunk, $known]) {
                foreach ($chunk as $raw) {
                    $meta = $known[(string) $raw[$pk]] ?? null;

                    if ($meta === null) {
                        continue; // never tracked: the next scan records it as a new row
                    }

                    try {
                        $wire = $this->rows->toWire($table, $raw);
                    } catch (RuntimeException) {
                        continue;
                    }

                    $hash = Support::hashRow($wire);

                    if ($meta['row_hash'] !== $hash) {
                        $this->db->table('sync_row_meta')->where(['table_name' => $table, 'uuid' => $meta['uuid']])->update([
                            'row_hash'  => $hash,
                            'base_blob' => Support::pack($wire),
                        ]);
                        $n++;
                    }
                }
            }
        }

        return $n;
    }

    /**
     * Gives rows that existed before installation a deterministic uuid and a
     * base snapshot. Assumes both databases hold the same data at this point.
     * Nothing is journaled.
     *
     * @return int number of rows seeded
     */
    public function seed(): int
    {
        $n = 0;

        $this->rows->forgetParents();

        foreach (array_keys($this->opt->tables) as $table) {
            $pk = $this->opt->table($table)['pk'];

            foreach ($this->chunks($table) as [$chunk, $known]) {
                foreach ($chunk as $raw) {
                    if (isset($known[(string) $raw[$pk]])) {
                        continue;
                    }

                    try {
                        $wire = $this->rows->toWire($table, $raw);
                    } catch (RuntimeException) {
                        continue; // parent seeded later in the config order: run seed again
                    }

                    $this->meta->saveState($table, Support::seedUuid($table, $raw[$pk]), [
                        'row_id'      => $raw[$pk],
                        'updated_at'  => 0,
                        'rev'         => 0,
                        'peer_rev'    => 0,
                        'node_origin' => $this->opt->node,
                        'row_hash'    => Support::hashRow($wire),
                        'base_blob'   => Support::pack($wire),
                    ]);
                    $n++;
                }
            }
        }

        return $n;
    }

    /**
     * The rows of a synchronised table that take part in the sync (all of them, or only those matching the table's
     * `where` filter, e.g. the `Config\Site` rows of Settings' shared table), in chunks of CHUNK rows. Each chunk
     * comes with the metadata of its rows and the parents' uuids already loaded: two queries per chunk instead of
     * two per row.
     *
     * @return Generator<int, array{0: list<array<string, mixed>>, 1: array<string, array<string, mixed>>}>
     */
    private function chunks(string $table): Generator
    {
        $cfg  = $this->opt->table($table);
        $pk   = $cfg['pk'];
        $last = null;

        while (true) {
            $b = $this->db->table($cfg['table']);

            // value => equality; list => IN (...): e.g. ['class' => ['Config\Site', 'Config\Shop']]
            foreach ($cfg['where'] as $col => $val) {
                is_array($val) ? $b->whereIn($col, $val) : $b->where($col, $val);
            }

            if ($last !== null) {
                $b->where($pk . ' >', $last);
            }

            $chunk = $b->orderBy($pk, 'ASC')->limit(self::CHUNK)->get()->getResultArray();

            if ($chunk === []) {
                return;
            }

            $this->rows->preloadParents($table, $chunk);

            yield [$chunk, $this->meta->lightByRows($table, array_column($chunk, $pk))];

            $last = end($chunk)[$pk];
        }
    }
}
