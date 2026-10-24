<?php

declare(strict_types=1);

namespace AdGo\Sync\Database\Migrations;

use CodeIgniter\Database\Migration;

/** `auth_write_lock` belongs to the sync package, not to Shield: it now carries the package prefix. */
class RenameWriteLock extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('auth_write_lock') && ! $this->db->tableExists('sync_write_lock')) {
            $this->forge->renameTable('auth_write_lock', 'sync_write_lock');
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('sync_write_lock') && ! $this->db->tableExists('auth_write_lock')) {
            $this->forge->renameTable('sync_write_lock', 'auth_write_lock');
        }
    }
}
