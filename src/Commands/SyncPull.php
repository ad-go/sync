<?php

declare(strict_types=1);

namespace AdGo\Sync\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use AdGo\Sync\Libraries\Kernel;
use Throwable;

class SyncPull extends BaseCommand
{
    use RunsExclusively;

    protected $group       = 'Sync';
    protected $name        = 'sync:pull';
    protected $description = "Detects local out-of-band changes, then pulls and reconciles the peer's changes.";
    protected $usage       = 'sync:pull';
    protected $arguments   = [];
    protected $options     = [];

    public function run(array $params)
    {
        return $this->exclusively('pull', $this->work(...));
    }

    private function work(): int
    {
        $kernel = Kernel::instance();

        $this->scanFirst($kernel);

        try {
            $r = $kernel->puller()->run();
        } catch (Throwable $e) {
            CLI::error('pull failed: ' . $e->getMessage());

            return EXIT_ERROR;
        }

        $parts = [];

        foreach ($r['outcomes'] as $outcome => $n) {
            $parts[] = "{$outcome}={$n}";
        }

        CLI::write('pulled ' . $r['pulled'] . ' change(s): ' . ($parts === [] ? 'none' : implode(', ', $parts)));

        if ($r['stopped'] !== null) {
            CLI::write('stopped early (will resume): ' . $r['stopped'], 'yellow');
        }

        $pending = $kernel->db->table('sync_conflicts')->where('status', 'pending')->countAllResults();

        if ($pending > 0) {
            CLI::write("{$pending} conflict(s) waiting for a decision.", 'yellow');
        }

        return EXIT_SUCCESS;
    }
}
