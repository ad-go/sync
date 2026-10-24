<?php

declare(strict_types=1);

namespace AdGo\Sync\Database\Migrations;

use AdGo\Sync\Database\SyncSchema;
use CodeIgniter\Database\Migration;

/** `sync_cursor.clock_*`: the measured clock offset of each peer. Fresh installs get the columns from CreateSyncTables. */
class AddClockColumns extends Migration
{
    public function up(): void
    {
        foreach (SyncSchema::clockColumns() as $name => $def) {
            if (! $this->db->fieldExists($name, 'sync_cursor')) {
                $this->forge->addColumn('sync_cursor', [$name => $def]);
            }
        }
    }

    public function down(): void
    {
        foreach (array_keys(SyncSchema::clockColumns()) as $name) {
            if ($this->db->fieldExists($name, 'sync_cursor')) {
                $this->forge->dropColumn('sync_cursor', $name);
            }
        }
    }
}
