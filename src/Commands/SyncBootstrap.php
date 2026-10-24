<?php

declare(strict_types=1);

namespace AdGo\Sync\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use AdGo\Sync\Libraries\Bootstrapper;
use AdGo\Sync\Libraries\Kernel;
use Throwable;

class SyncBootstrap extends BaseCommand
{
    protected $group       = 'Sync';
    protected $name        = 'sync:bootstrap';
    protected $description = "Copies the official packages' tables (users, settings...) ONCE from the master node into this node.";
    protected $usage       = 'sync:bootstrap [--force]';
    protected $arguments   = [];
    protected $options     = ['--force' => 'Overwrite non-empty tables'];

    public function run(array $params)
    {
        $kernel = Kernel::instance();

        if (CLI::prompt("This REPLACES the bootstrap tables on this node with {$kernel->opt->peerNode}'s data. Continue?", ['y', 'n']) !== 'y') {
            return EXIT_ERROR;
        }

        try {
            $r = (new Bootstrapper($kernel->db, $kernel->opt, $kernel->peer()))->run(CLI::getOption('force') !== null);
        } catch (Throwable $e) {
            CLI::error($e->getMessage());

            return EXIT_ERROR;
        }

        foreach ($r as $table => $n) {
            CLI::write(sprintf('%-28s %d row(s)', $table, $n));
        }

        return EXIT_SUCCESS;
    }
}
