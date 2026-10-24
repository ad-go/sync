<?php

declare(strict_types=1);

namespace AdGo\Sync\Database\Migrations;

use CodeIgniter\Database\Migration;

/** `sync_cursor.clock_ntp_us`: the NTP correction a peer applies to its own stamps. Fresh installs get it from CreateSyncTables. */
class AddClockNtpColumn extends Migration
{
    public function up(): void
    {
        if (! $this->db->fieldExists('clock_ntp_us', 'sync_cursor')) {
            $this->forge->addColumn('sync_cursor', ['clock_ntp_us' => ['type' => 'BIGINT', 'null' => true]]);
        }
    }

    public function down(): void
    {
        if ($this->db->fieldExists('clock_ntp_us', 'sync_cursor')) {
            $this->forge->dropColumn('sync_cursor', 'clock_ntp_us');
        }
    }
}
