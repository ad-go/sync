<?php

declare(strict_types=1);

namespace AdGo\Sync\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * `sync_changes.origin`: on a relaying hub, a journal entry written for a change that came from another peer
 * carries the name of that peer (NULL for the node's own writes). Fresh installs get the column from
 * CreateSyncTables. Additive: two-node set-ups never write it.
 */
class AddJournalOrigin extends Migration
{
    public function up(): void
    {
        if (! $this->db->fieldExists('origin', 'sync_changes')) {
            $this->forge->addColumn('sync_changes', ['origin' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true]]);
        }
    }

    public function down(): void
    {
        if ($this->db->fieldExists('origin', 'sync_changes')) {
            $this->forge->dropColumn('sync_changes', 'origin');
        }
    }
}
