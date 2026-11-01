<?php

declare(strict_types=1);

namespace AdGo\Sync\Config;

use CodeIgniter\Config\BaseService;
use AdGo\Sync\Libraries\Kernel;

/**
 * Auto-discovered by CodeIgniter: `service('sync')` returns the shared Kernel of the package.
 */
class Services extends BaseService
{
    public static function sync(bool $getShared = true): Kernel
    {
        if ($getShared) {
            return static::getSharedInstance('sync');
        }

        return Kernel::instance();
    }
}
