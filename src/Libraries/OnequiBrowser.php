<?php

declare(strict_types=1);

namespace AdGo\Sync\Libraries;

use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Adminer-style read/write access to one connection's tables: list tables, list/filter/sort rows, insert, update,
 * delete, and run an arbitrary statement. Driver-agnostic (MySQL, PostgreSQL, SQLite — anything CI4's query
 * builder supports); used by Controllers/OnequiAdmin.
 *
 * Every table/column name is checked against the connection's own metadata before it reaches a query, since the
 * query builder cannot bind an identifier the way it binds a value.
 */
final class OnequiBrowser
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    /** @return list<array{name: string, rows: int}> */
    public function tables(): array
    {
        $names = $this->db->listTables();
        sort($names);

        return array_map(
            fn (string $name): array => ['name' => $name, 'rows' => (int) $this->db->table($name)->countAllResults(false)],
            $names,
        );
    }

    public function exists(string $table): bool
    {
        return in_array($table, $this->db->listTables(), true);
    }

    /** @return list<object{name: string, type: string, primary_key?: int}> raw field metadata, as the driver returns it */
    public function columns(string $table): array
    {
        $this->assertTable($table);

        return $this->db->getFieldData($table);
    }

    /** @return list<string> */
    public function primaryKey(string $table): array
    {
        $pk = [];

        foreach ($this->columns($table) as $c) {
            if (! empty($c->primary_key)) {
                $pk[] = $c->name;
            }
        }

        return $pk;
    }

    /**
     * @param array<string, string> $filters column => needle (substring match); unknown columns are ignored
     * @return array{rows: list<array<string, mixed>>, total: int, columns: list<object>}
     */
    public function rows(string $table, int $page, int $perPage, ?string $sort, string $dir, array $filters): array
    {
        $this->assertTable($table);
        $columns = $this->columns($table);
        $names   = array_map(static fn (object $c): string => $c->name, $columns);

        $count = $this->db->table($table);

        foreach ($filters as $col => $needle) {
            if ($needle === '' || ! in_array($col, $names, true)) {
                continue;
            }

            $count->like($col, $needle);
        }

        $total = (int) $count->countAllResults(false);

        $perPage = max(1, min(500, $perPage));
        $page    = max(1, $page);

        $builder = $this->db->table($table);

        foreach ($filters as $col => $needle) {
            if ($needle === '' || ! in_array($col, $names, true)) {
                continue;
            }

            $builder->like($col, $needle);
        }

        if ($sort !== null && in_array($sort, $names, true)) {
            $builder->orderBy($sort, strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC');
        }

        $builder->limit($perPage, ($page - 1) * $perPage);

        return ['rows' => $builder->get()->getResultArray(), 'total' => $total, 'columns' => $columns];
    }

    /**
     * @param array<string, mixed> $pk
     * @return array<string, mixed>|null
     */
    public function find(string $table, array $pk): ?array
    {
        if ($pk === []) {
            return null;
        }

        $this->assertTable($table);
        $builder = $this->db->table($table);

        foreach ($this->onlyKnownColumns($table, $pk) as $col => $val) {
            $builder->where($col, $val);
        }

        return $builder->get()->getRowArray();
    }

    /** @param array<string, mixed> $data */
    public function insert(string $table, array $data): void
    {
        $this->assertTable($table);
        $clean = $this->onlyKnownColumns($table, $data);

        if ($clean === []) {
            throw new InvalidArgumentException('Nothing to insert.');
        }

        $this->db->table($table)->insert($clean);
    }

    /**
     * @param array<string, mixed> $pk
     * @param array<string, mixed> $data
     */
    public function update(string $table, array $pk, array $data): void
    {
        if ($pk === []) {
            throw new InvalidArgumentException('Missing primary key.');
        }

        $this->assertTable($table);
        $builder = $this->db->table($table);

        foreach ($this->onlyKnownColumns($table, $pk) as $col => $val) {
            $builder->where($col, $val);
        }

        $clean = $this->onlyKnownColumns($table, $data);

        if ($clean !== []) {
            $builder->update($clean);
        }
    }

    /** @param array<string, mixed> $pk */
    public function delete(string $table, array $pk): void
    {
        if ($pk === []) {
            throw new InvalidArgumentException('Missing primary key.');
        }

        $this->assertTable($table);
        $builder = $this->db->table($table);

        foreach ($this->onlyKnownColumns($table, $pk) as $col => $val) {
            $builder->where($col, $val);
        }

        $builder->delete();
    }

    /**
     * Runs one arbitrary statement against the connection. A SELECT-like statement returns its rows; anything
     * else (INSERT/UPDATE/DELETE/DDL) returns the affected row count.
     *
     * @return array{columns: list<string>, rows: list<array<string, mixed>>, affected: int}
     */
    public function run(string $sql): array
    {
        try {
            $result = $this->db->query($sql);
        } catch (Throwable $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }

        if ($result === false) {
            $err = $this->db->error();

            throw new RuntimeException((string) ($err['message'] ?? 'Query failed.'));
        }

        if (! is_object($result) || ! method_exists($result, 'getFieldNames')) {
            return ['columns' => [], 'rows' => [], 'affected' => $this->db->affectedRows()];
        }

        $rows = $result->getResultArray();

        return ['columns' => $result->getFieldNames(), 'rows' => $rows, 'affected' => count($rows)];
    }

    private function assertTable(string $table): void
    {
        if (! $this->exists($table)) {
            throw new InvalidArgumentException("Unknown table: {$table}");
        }
    }

    /**
     * Drops any key that is not really a column of $table (defensive against stray form fields).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function onlyKnownColumns(string $table, array $data): array
    {
        $names = array_map(static fn (object $c): string => $c->name, $this->columns($table));

        return array_intersect_key($data, array_flip($names));
    }
}
