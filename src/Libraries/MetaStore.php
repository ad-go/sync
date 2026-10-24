<?php

declare(strict_types=1);

namespace AdGo\Sync\Libraries;

use CodeIgniter\Database\BaseConnection;

/**
 * Abstraction over sync_row_meta / sync_changes / sync_cursor: maps local rows
 * to logical uuids, bumps revisions and appends to the change journal.
 * Callers are responsible for wrapping calls in a transaction together with
 * the write to the application table.
 */
class MetaStore
{
    public function __construct(
        private readonly BaseConnection $db,
        private readonly Options $opt,
    ) {
    }

    /** @return array<string, mixed>|null */
    public function findByUuid(string $table, string $uuid): ?array
    {
        return $this->db->table('sync_row_meta')->where(['table_name' => $table, 'uuid' => $uuid])->get()->getRowArray();
    }

    /** @return array<string, mixed>|null */
    public function findByRow(string $table, int|string $rowId): ?array
    {
        return $this->db->table('sync_row_meta')->where(['table_name' => $table, 'row_id' => $rowId])->get()->getRowArray();
    }

    /**
     * Light batch lookup for the scanner: one query for many rows, without the heavy `base_blob`.
     *
     * @param list<int|string> $rowIds
     *
     * @return array<string, array{row_id: int|string, uuid: string, row_hash: ?string}> keyed by (string) row id
     */
    public function lightByRows(string $table, array $rowIds): array
    {
        if ($rowIds === []) {
            return [];
        }

        $out  = [];
        $rows = $this->db->table('sync_row_meta')->select('row_id, uuid, row_hash')->where('table_name', $table)->whereIn('row_id', $rowIds)->get()->getResultArray();

        foreach ($rows as $r) {
            $out[(string) $r['row_id']] = $r;
        }

        return $out;
    }

    /**
     * Records a LOCAL write: creates/updates the meta row, bumps `rev` and
     * appends to the journal. `$row` has FK columns already translated to
     * uuids (see RowGateway::export()).
     *
     * @param array<string, mixed>|null $row null for deletes
     *
     * @return array<string, mixed>|null the meta row after the write, null if nothing was recorded
     */
    public function recordLocal(string $table, int|string $rowId, string $op, ?array $row, ?string $uuid = null): ?array
    {
        $now  = Support::nowUs();
        $meta = $this->findByRow($table, $rowId);

        if ($meta === null && $uuid !== null) {
            $meta = $this->findByUuid($table, $uuid);
        }

        if ($meta === null) {
            if ($op === 'D') {
                return null; // deleting something never tracked: nothing to propagate
            }

            $meta = [
                'table_name'  => $table,
                'row_id'      => $rowId,
                'uuid'        => $uuid ?? Support::uuid4(),
                'updated_at'  => $now,
                'rev'         => 1,
                'peer_rev'    => 0,
                'node_origin' => $this->opt->node,
                'deleted_at'  => null,
                'row_hash'    => Support::hashRow($row),
                'base_blob'   => null,
            ];
            $this->db->table('sync_row_meta')->insert($meta);
            $meta['id'] = $this->db->insertID();
            $op         = 'I';
        } else {
            // never below the peer's counter: a row that arrived with revision r must leave with a revision above r, or a relaying hub
            // that already integrated r from another node would take the change for a duplicate
            $rev    = max((int) $meta['rev'], (int) $meta['peer_rev']) + 1;
            $update = [
                'updated_at'  => $now,
                'rev'         => $rev,
                'node_origin' => $this->opt->node,
                'deleted_at'  => $op === 'D' ? $now : null,
                'row_hash'    => Support::hashRow($row),
                'row_id'      => $op === 'D' ? null : $rowId,
            ];
            $this->db->table('sync_row_meta')->where('id', $meta['id'])->update($update);
            $meta = array_merge($meta, $update);
        }

        $this->journal($table, (string) $meta['uuid'], $op, (int) $meta['rev'], (int) $meta['peer_rev'], $now, $row);

        return $meta;
    }

    /**
     * @param array<string, mixed>|null $row
     */
    public function journal(string $table, string $uuid, string $op, int $rev, int $seen, int $at, ?array $row, bool $authoritative = false, ?string $origin = null): void
    {
        $this->db->table('sync_changes')->insert([
            'origin'        => $origin,
            'table_name'    => $table,
            'uuid'          => $uuid,
            'op'            => $op,
            'rev'           => $rev,
            'seen'          => $seen,
            'authoritative' => $authoritative ? 1 : 0,
            'changed_at'    => $at,
            'node_origin'   => $this->opt->node,
            'payload'       => $row === null ? null : Support::pack($row),
        ]);
    }

    /**
     * Writes a meta row that mirrors state received from the peer (no journal entry).
     *
     * @param array<string, mixed> $fields
     */
    public function saveState(string $table, string $uuid, array $fields): void
    {
        $existing = $this->findByUuid($table, $uuid);

        $existing === null ? $this->insertState($table, $uuid, $fields) : $this->updateState((int) $existing['id'], $fields);
    }

    /**
     * Same as saveState() for a row the caller has just established to be unknown.
     *
     * @param array<string, mixed> $fields
     */
    public function insertState(string $table, string $uuid, array $fields): void
    {
        $this->db->table('sync_row_meta')->insert($fields + [
            'table_name'  => $table,
            'uuid'        => $uuid,
            'row_id'      => null,
            'updated_at'  => Support::nowUs(),
            'rev'         => 0,
            'peer_rev'    => 0,
            'node_origin' => $this->opt->peerNode,
            'deleted_at'  => null,
            'row_hash'    => null,
            'base_blob'   => null,
        ]);
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function updateState(int $metaId, array $fields): void
    {
        $this->db->table('sync_row_meta')->where('id', $metaId)->update($fields);
    }

    /** @return list<array<string, mixed>> */
    public function changesSince(int $seq, int $limit): array
    {
        $rows = $this->db->table('sync_changes')->where('seq >', $seq)->orderBy('seq', 'ASC')->limit($limit)->get()->getResultArray();

        $out = [];

        foreach ($rows as $r) {
            $out[] = [
                'seq'           => (int) $r['seq'],
                'table'         => $r['table_name'],
                'uuid'          => $r['uuid'],
                'op'            => $r['op'],
                'rev'           => (int) $r['rev'],
                'seen'          => (int) $r['seen'],
                'authoritative' => (int) $r['authoritative'] === 1,
                'changed_at'    => (int) $r['changed_at'],
                'node'          => $r['node_origin'],
                'origin'        => $r['origin'] ?? null,
                'row'           => $r['payload'] === null ? null : Support::unpack($r['payload']),
            ];
        }

        return $out;
    }

    /**
     * Relay (hub only): the state just agreed with a peer is journaled again as this node's own revision, tagged
     * with the peer it came from, so the other peers receive it. Bumps `rev` like a local write.
     *
     * @param array<string, mixed>|null $state
     */
    public function relay(string $table, string $uuid, ?array $state, string $origin): void
    {
        $meta = $this->findByUuid($table, $uuid);

        if ($meta === null) {
            return;
        }

        $rev = (int) $meta['rev'] + 1;
        $now = Support::nowUs();
        $this->db->table('sync_row_meta')->where('id', $meta['id'])->update(['rev' => $rev]);
        $this->journal($table, $uuid, $state === null ? 'D' : 'U', $rev, (int) $meta['peer_rev'], $now, $state, false, $origin);
    }

    public function cursor(string $peer): int
    {
        $row = $this->db->table('sync_cursor')->where('peer_node', $peer)->get()->getRowArray();

        return $row === null ? 0 : (int) $row['last_seq'];
    }

    public function setCursor(string $peer, int $seq): void
    {
        $data = ['last_seq' => $seq, 'last_ts' => Support::nowUs()];

        $this->db->table('sync_cursor')->where('peer_node', $peer)->update($data);

        // MySQL reports 0 affected rows for an identical update, so only a missing row means "insert"
        if ($this->db->affectedRows() === 0 && $this->db->table('sync_cursor')->where('peer_node', $peer)->countAllResults() === 0) {
            $this->db->table('sync_cursor')->insert($data + ['peer_node' => $peer]);
        }
    }
}
