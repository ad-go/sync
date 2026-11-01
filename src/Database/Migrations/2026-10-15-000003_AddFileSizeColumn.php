<?php

declare(strict_types=1);

namespace AdGo\Sync\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * `sync_files` (the index of the synchronised directory) also remembers each file's size, so that an unchanged file
 * (same mtime and size, indexed long enough ago) is not read and hashed again on every run. Fresh installs already
 * get the column from CreateSyncTables.
 */
class AddFileSizeColumn extends Migration
{
    public function up(): void
    {
        if (! $this->db->fieldExists('size', 'sync_files')) {
            $this->forge->addColumn('sync_files', ['size' => ['type' => 'BIGINT', 'default' => 0]]);
        }
    }

    public function down(): void
    {
        if ($this->db->fieldExists('size', 'sync_files')) {
            $this->forge->dropColumn('sync_files', 'size');
        }
    }
}
