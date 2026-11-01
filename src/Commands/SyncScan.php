<?php

declare(strict_types=1);

namespace AdGo\Sync\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use AdGo\Sync\Libraries\Kernel;

class SyncScan extends BaseCommand
{
    protected $group       = 'Sync';
    protected $name        = 'sync:scan';
    protected $description = 'Records local changes made outside the Syncable models (query builder, raw SQL, batch).';
    protected $usage       = 'sync:scan';
    protected $arguments   = [];
    protected $options     = [];

    public function run(array $params)
    {
        $s = Kernel::instance()->scanner()->scan();
        CLI::write(sprintf('inserted %d, updated %d, deleted %d, deferred %d', $s['inserted'], $s['updated'], $s['deleted'], $s['deferred']));

        return EXIT_SUCCESS;
    }
}
