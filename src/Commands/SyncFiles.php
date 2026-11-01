<?php

declare(strict_types=1);

namespace AdGo\Sync\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use AdGo\Sync\Libraries\Kernel;
use Throwable;

class SyncFiles extends BaseCommand
{
    use RunsExclusively;

    protected $group       = 'Sync';
    protected $name        = 'sync:files';
    protected $description = 'Synchronises the shared files directory with the peer (differences only).';
    protected $usage       = 'sync:files';
    protected $arguments   = [];
    protected $options     = [];

    public function run(array $params)
    {
        return $this->exclusively('files', $this->work(...));
    }

    private function work(): int
    {
        try {
            $r = Kernel::instance()->files()->run();
        } catch (Throwable $e) {
            CLI::error('file sync failed: ' . $e->getMessage());

            return EXIT_ERROR;
        }

        CLI::write(sprintf('downloaded %d, pushed %d, deleted %d, directories %d, conflicts %d, kept local %d', $r['downloaded'], $r['pushed'], $r['deleted'], $r['dirs'], $r['conflicts'], $r['kept']));

        return EXIT_SUCCESS;
    }
}
