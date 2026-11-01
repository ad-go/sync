<?php

declare(strict_types=1);

namespace AdGo\Sync\Database;

use CodeIgniter\Database\Forge;

/**
 * Single definition of the package's OWN tables. Used by the migration and by
 * the tests. Timestamps are BIGINT microseconds since epoch: portable and
 * sub-second precise on MySQL, PostgreSQL and SQLite. No JSON columns.
 */
final class SyncSchema
{
    /** Transactions are required: never rely on the server's default engine (MyISAM on some shared hosts). */
    private const MYSQL_ATTRIBUTES = ['ENGINE' => 'InnoDB'];

    public const TABLES = ['sync_row_meta', 'sync_changes', 'sync_conflicts', 'sync_cursor', 'sync_write_lock', 'sync_files', 'sync_nonce'];

    /**
     * @param string $driver the connection's DBDriver (e.g. 'MySQLi'); the ENGINE option only exists on MySQL/MariaDB
     */
    public static function up(Forge $forge, string $driver = ''): void
    {
        $attr = stripos($driver, 'mysql') !== false ? self::MYSQL_ATTRIBUTES : [];

        // Maps local row -> logical uuid + versioning.
        $forge->addField([
            'id'          => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'table_name'  => ['type' => 'VARCHAR', 'constraint' => 128],
            'row_id'      => ['type' => 'BIGINT', 'null' => true],            // LOCAL pk; NULL for tombstones
            'uuid'        => ['type' => 'CHAR', 'constraint' => 36],
            'updated_at'  => ['type' => 'BIGINT', 'default' => 0],            // microseconds
            'rev'         => ['type' => 'INT', 'unsigned' => true, 'default' => 0],       // my write counter
            'peer_rev'    => ['type' => 'INT', 'unsigned' => true, 'default' => 0],       // peer's counter I have integrated
            'node_origin' => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => ''],
            'deleted_at'  => ['type' => 'BIGINT', 'null' => true],
            'row_hash'    => ['type' => 'CHAR', 'constraint' => 40, 'null' => true],      // hash of the row as last recorded
            'base_blob'   => ['type' => 'TEXT', 'null' => true],              // serialize(): row at last agreement
        ]);
        $forge->addKey('id', true);
        $forge->addUniqueKey(['table_name', 'uuid'], 'sync_row_meta_uuid');
        $forge->addUniqueKey(['table_name', 'row_id'], 'sync_row_meta_row');
        $forge->addKey(['table_name', 'updated_at'], false, false, 'sync_row_meta_upd');
        $forge->createTable('sync_row_meta', true, $attr);

        // Append-only change journal.
        $forge->addField([
            'seq'         => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'table_name'  => ['type' => 'VARCHAR', 'constraint' => 128],
            'uuid'        => ['type' => 'CHAR', 'constraint' => 36],
            'op'          => ['type' => 'CHAR', 'constraint' => 1],           // I / U / D
            'rev'         => ['type' => 'INT', 'unsigned' => true],
            'seen'        => ['type' => 'INT', 'unsigned' => true, 'default' => 0],       // peer_rev at the time of writing
            'authoritative' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0], // conflict resolution
            'changed_at'  => ['type' => 'BIGINT'],
            'node_origin' => ['type' => 'VARCHAR', 'constraint' => 32],
            'payload'     => ['type' => 'TEXT', 'null' => true],              // serialize(): row (FKs as uuids) or NULL for D
            'origin'      => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true], // relayed from this peer (NULL = own write)
        ]);
        $forge->addKey('seq', true);
        $forge->addKey(['table_name', 'uuid'], false, false, 'sync_changes_row');
        $forge->createTable('sync_changes', true, $attr);

        // Conflict quarantine (and log of auto-resolved conflicts).
        $forge->addField([
            'id'          => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'table_name'  => ['type' => 'VARCHAR', 'constraint' => 128],
            'uuid'        => ['type' => 'CHAR', 'constraint' => 36],
            'local_node'  => ['type' => 'VARCHAR', 'constraint' => 32],
            'remote_node' => ['type' => 'VARCHAR', 'constraint' => 32],
            'local_blob'  => ['type' => 'TEXT', 'null' => true],              // serialize(row|null)
            'remote_blob' => ['type' => 'TEXT', 'null' => true],
            'base_blob'   => ['type' => 'TEXT', 'null' => true],
            'fields'      => ['type' => 'VARCHAR', 'constraint' => 1000, 'default' => ''], // divergent fields, comma separated
            'local_rev'   => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'remote_rev'  => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'local_ts'    => ['type' => 'BIGINT', 'default' => 0],
            'remote_ts'   => ['type' => 'BIGINT', 'default' => 0],
            'severity'    => ['type' => 'VARCHAR', 'constraint' => 16],        // safe | dangerous
            'resolved_as' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true], // auto-merge | auto-lww | <node> | merge | ignore
            'detected_at' => ['type' => 'BIGINT'],
            'status'      => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'pending'], // pending | resolved | ignored
            'resolved_by' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'resolved_at' => ['type' => 'BIGINT', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->addKey(['status', 'detected_at'], false, false, 'sync_conflicts_status');
        $forge->addKey(['table_name', 'uuid'], false, false, 'sync_conflicts_row');
        $forge->createTable('sync_conflicts', true, $attr);

        // Per-peer position in the peer's change journal (a table, not a file).
        $forge->addField([
            'peer_node' => ['type' => 'VARCHAR', 'constraint' => 32],
            'last_seq'  => ['type' => 'BIGINT', 'unsigned' => true, 'default' => 0],
            'last_ts'   => ['type' => 'BIGINT', 'default' => 0],
        ] + self::clockColumns());
        $forge->addKey('peer_node', true);
        $forge->createTable('sync_cursor', true, $attr);

        // Distributed write locks ('auth' for user editing, 'cron' for the scheduler).
        $forge->addField([
            'lock_kind'    => ['type' => 'VARCHAR', 'constraint' => 16],
            'holder'       => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'acquired_at'  => ['type' => 'BIGINT', 'default' => 0],
            'heartbeat_at' => ['type' => 'BIGINT', 'default' => 0],
            'ttl_seconds'  => ['type' => 'INT', 'default' => 120],
            'mode'         => ['type' => 'VARCHAR', 'constraint' => 8, 'default' => 'manual'],   // manual | lease
            'scope'        => ['type' => 'VARCHAR', 'constraint' => 128, 'null' => true],        // what is being edited, e.g. users:12
            'edited_by'    => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
        ]);
        $forge->addKey('lock_kind', true);
        $forge->createTable('sync_write_lock', true, $attr);

        // Index of the synchronised directory as of the last agreement.
        $forge->addField([
            'path_hash' => ['type' => 'CHAR', 'constraint' => 40],            // sha1(path): short, fixed-size key
            'path'    => ['type' => 'VARCHAR', 'constraint' => 1024],
            'sha256'  => ['type' => 'CHAR', 'constraint' => 64, 'null' => true], // NULL = deleted
            'mtime'   => ['type' => 'BIGINT', 'default' => 0],
            'size'    => ['type' => 'BIGINT', 'default' => 0],
            'seen_at' => ['type' => 'BIGINT', 'default' => 0],
        ]);
        $forge->addKey('path_hash', true);
        $forge->createTable('sync_files', true, $attr);

        self::nonce($forge, $driver);
    }

    /**
     * Measured clock of a peer: peer minus this node in microseconds, the round trip it was measured with, and
     * when (microseconds since the epoch); `clock_ntp_us` is the correction the peer says it applies to its own
     * stamps (null: none).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function clockColumns(): array
    {
        return [
            'clock_offset_us' => ['type' => 'BIGINT', 'null' => true],
            'clock_rtt_us'    => ['type' => 'BIGINT', 'null' => true],
            'clock_at'        => ['type' => 'BIGINT', 'null' => true],
            'clock_ntp_us'    => ['type' => 'BIGINT', 'null' => true],
        ];
    }

    /** Nonces of accepted requests: the primary key makes "first use wins" atomic, whatever the cache does. */
    public static function nonce(Forge $forge, string $driver = ''): void
    {
        $forge->addField([
            'nonce_hash' => ['type' => 'CHAR', 'constraint' => 64],           // sha256(nonce)
            'seen_at'    => ['type' => 'BIGINT', 'default' => 0],            // Unix seconds
        ]);
        $forge->addKey('nonce_hash', true);
        $forge->addKey('seen_at');
        $forge->createTable('sync_nonce', true, stripos($driver, 'mysql') !== false ? self::MYSQL_ATTRIBUTES : []);
    }

    public static function down(Forge $forge): void
    {
        foreach (array_reverse(self::TABLES) as $table) {
            $forge->dropTable($table, true);
        }
    }
}
