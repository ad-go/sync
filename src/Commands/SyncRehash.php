<?php

declare(strict_types=1);

namespace AdGo\Sync\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use AdGo\Sync\Libraries\Kernel;

class SyncRehash extends BaseCommand
{
    protected $group       = 'Sync';
    protected $name        = 'sync:rehash';
    protected $description = 'After adding/dropping a column of a synced table: refresh the recorded row state without journaling changes.';
    protected $usage       = 'sync:rehash';
    protected $arguments   = [];
    protected $options     = [];

    public function run(array $params)
    {
        CLI::write('Only valid when the new column holds the same value on BOTH nodes (NULL / default).', 'yellow');
        CLI::write(sprintf('rows refreshed: %d', Kernel::instance()->scanner()->rehash()));

        return EXIT_SUCCESS;
    }
}
