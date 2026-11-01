<?php

declare(strict_types=1);

namespace AdGo\Sync\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use AdGo\Sync\Libraries\Kernel;
use Throwable;

/** On a passive node (behind NAT): scans local changes and pushes the journal to the hub. */
class SyncPush extends BaseCommand
{
    use RunsExclusively;

    protected $group       = 'Sync';
    protected $name        = 'sync:push';
    protected $description = 'Pushes this node\'s change journal to its hub (passive nodes only).';
    protected $usage       = 'sync:push';
    protected $arguments   = [];
    protected $options     = [];

    public function run(array $params)
    {
        return $this->exclusively('push', $this->work(...));
    }

    private function work(): int
    {
        $kernel = Kernel::instance();

        if (! $kernel->opt->passive) {
            CLI::write('This node is not passive (sync.passive = false): its peers pull it, nothing to push.');

            return EXIT_SUCCESS;
        }

        $this->scanFirst($kernel);

        try {
            $r = $kernel->pusher()->run();
        } catch (Throwable $e) {
            CLI::error('push failed: ' . $e->getMessage());

            return EXIT_ERROR;
        }

        CLI::write("pushed {$r['pushed']} change(s); the hub has applied up to seq {$r['applied']}");

        if ($r['stopped'] !== null) {
            CLI::write('stopped early (will resume): ' . $r['stopped'], 'yellow');
        }

        return EXIT_SUCCESS;
    }
}
