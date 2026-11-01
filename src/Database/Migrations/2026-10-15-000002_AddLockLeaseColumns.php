<?php

declare(strict_types=1);

namespace AdGo\Sync\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Edit leases: `auth_write_lock` (a table of this package, never an application table) learns the lock mode and
 * the published editing position. Fresh installs already get these columns from CreateSyncTables, so every
 * step checks first.
 */
class AddLockLeaseColumns extends Migration
{
    private const COLUMNS = [
        'mode'      => ['type' => 'VARCHAR', 'constraint' => 8, 'default' => 'manual'],
        'scope'     => ['type' => 'VARCHAR', 'constraint' => 128, 'null' => true],
        'edited_by' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
    ];

    /** The table was called auth_write_lock before the rename migration (000005); new installs create sync_write_lock. */
    private function table(): string
    {
        return $this->db->tableExists('sync_write_lock') ? 'sync_write_lock' : 'auth_write_lock';
    }

    public function up(): void
    {
        $t = $this->table();

        foreach (self::COLUMNS as $name => $def) {
            if (! $this->db->fieldExists($name, $t)) {
                $this->forge->addColumn($t, [$name => $def]);
            }
        }
    }

    public function down(): void
    {
        $t = $this->table();

        foreach (array_keys(self::COLUMNS) as $name) {
            if ($this->db->fieldExists($name, $t)) {
                $this->forge->dropColumn($t, $name);
            }
        }
    }
}
