<?php

declare(strict_types=1);

namespace AdGo\Sync\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use AdGo\Sync\Libraries\Kernel;
use AdGo\Sync\Libraries\Pruner;

class SyncPrune extends BaseCommand
{
    protected $group       = 'Sync';
    protected $name        = 'sync:prune';
    protected $description = 'Deletes old change-journal entries (already applied by the peer) and old decided conflicts.';
    protected $usage       = 'sync:prune';
    protected $arguments   = [];
    protected $options     = [];

    public function run(array $params)
    {
        $k = Kernel::instance();
        $peers = [];

        foreach ($k->opt->activePeers() as $name) {
            $peers[$name] = $k->peer($name);
        }

        $r = (new Pruner($k->db, $k->opt, null, $peers))->run();

        CLI::write(sprintf('journal entries deleted: %d, decided conflicts deleted: %d', $r['journal'], $r['conflicts']));

        if ($r['skipped'] !== null) {
            CLI::write('journal not pruned: ' . $r['skipped'], 'yellow');
        }

        return EXIT_SUCCESS;
    }
}
