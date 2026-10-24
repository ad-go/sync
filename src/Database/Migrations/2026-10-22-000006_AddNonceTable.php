<?php

declare(strict_types=1);

namespace AdGo\Sync\Database\Migrations;

use AdGo\Sync\Database\SyncSchema;
use CodeIgniter\Database\Migration;

/** Replay protection that does not depend on the application cache. */
class AddNonceTable extends Migration
{
    public function up(): void
    {
        SyncSchema::nonce($this->forge, (string) $this->db->DBDriver);
    }

    public function down(): void
    {
        $this->forge->dropTable('sync_nonce', true);
    }
}
