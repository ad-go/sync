<?php

declare(strict_types=1);

namespace AdGo\Sync\Models;

use CodeIgniter\Model;
use AdGo\Sync\Traits\Syncable;

/**
 * Alternative to the trait for apps that prefer inheritance:
 * `class ProductModel extends \AdGo\Sync\Models\SyncableModel`.
 */
abstract class SyncableModel extends Model
{
    use Syncable;
}
