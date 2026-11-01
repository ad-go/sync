<?php

declare(strict_types=1);

namespace AdGo\Sync\Database\Migrations;

use CodeIgniter\Database\Migration;
use AdGo\Sync\Database\SyncSchema;

/**
 * Creates ONLY the package's own tables. `down()` drops them again, which
 * returns the database exactly to its previous state (no existing table is
 * ever altered).
 */
class CreateSyncTables extends Migration
{
    public function up(): void
    {
        SyncSchema::up($this->forge, (string) $this->db->DBDriver);
    }

    public function down(): void
    {
        SyncSchema::down($this->forge);
    }
}
