<?php

declare(strict_types=1);

namespace AdGo\Sync\Traits;

use AdGo\Sync\Libraries\Kernel;
use AdGo\Sync\Libraries\Support;

/**
 * Makes a CodeIgniter Model record its writes for synchronisation.
 *
 *     class ProductModel extends \CodeIgniter\Model
 *     {
 *         use \AdGo\Sync\Traits\Syncable;
 *         protected $table = 'products';   // must be listed in Config\Sync::$tables
 *     }
 *
 * No registration of callbacks is needed: the trait hooks into the model's own
 * event dispatcher (`trigger()`), so it cannot be forgotten or overridden by a
 * model's `$afterInsert` arrays. insert()/update()/delete() are wrapped in a
 * transaction so the application row and its sync metadata commit (or roll
 * back) together: no orphan uuids.
 *
 * Not covered by the model events: insertBatch/updateBatch, raw SQL and the
 * query builder. `spark sync:pull` runs the change scanner, which picks those
 * up (see Libraries\ChangeScanner).
 */
trait Syncable
{
    public function insert($row = null, bool $returnID = true)
    {
        return Support::tx($this->db, fn () => parent::insert($row, $returnID));
    }

    public function update($id = null, $row = null): bool
    {
        return Support::tx($this->db, fn () => parent::update($id, $row));
    }

    public function delete($id = null, bool $purge = false)
    {
        return Support::tx($this->db, fn () => parent::delete($id, $purge));
    }

    /**
     * @param array<string, mixed> $eventData
     *
     * @return array<string, mixed>
     */
    protected function trigger(string $event, array $eventData)
    {
        if ($event === 'afterInsert' || $event === 'afterUpdate' || $event === 'afterDelete') {
            $this->syncCapture($event, $eventData);
        }

        return parent::trigger($event, $eventData);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function syncCapture(string $event, array $data): void
    {
        $kernel = Kernel::instance();

        if ($kernel->opt->table($this->table) === null) {
            return;
        }

        $table = $this->table;

        if ($event === 'afterInsert') {
            $ids = [$data['id'] ?? $this->db->insertID()];
            $op  = 'I';
        } else {
            $ids = $data['id'] ?? [];
            $ids = is_array($ids) ? $ids : [$ids];
            $op  = 'U';

            // A hard (purge) delete is a tombstone. A soft delete just updates deleted_at,
            // which travels as ordinary data.
            if ($event === 'afterDelete' && (! $this->useSoftDeletes || ! empty($data['purge']))) {
                $op = 'D';
            }
        }

        foreach ($ids as $id) {
            if ($id === null || $id === '' || $id === 0 || $id === '0') {
                continue;
            }

            $row = $op === 'D' ? null : $kernel->rows->export($table, $id);

            if ($op !== 'D' && $row === null) {
                continue;
            }

            $kernel->meta->recordLocal($table, $id, $op, $row);
            $kernel->markChanged();
        }
    }
}
