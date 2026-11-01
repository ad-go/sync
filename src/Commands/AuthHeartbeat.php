<?php

declare(strict_types=1);

namespace AdGo\Sync\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use AdGo\Sync\Libraries\Kernel;
use AdGo\Sync\Libraries\Support;

class AuthHeartbeat extends BaseCommand
{
    use ReadsOptions;

    protected $group       = 'Sync';
    protected $name        = 'sync:auth-heartbeat';
    protected $description = 'Renews the auth/cron write-locks held by this node (run from cron every minute).';
    protected $usage       = 'sync:auth-heartbeat [--acquire=auth|cron] [--release=auth|cron] [--confirm]';
    protected $arguments   = [];
    protected $options     = [
        '--acquire' => 'Take the given lock',
        '--release' => 'Release the given lock',
        '--confirm' => 'Explicit human confirmation for a takeover when the peer is unreachable',
    ];

    public function run(array $params)
    {
        $locks = Kernel::instance()->locks();

        $release = $this->option('release');

        if (is_string($release)) {
            $locks->release($release);
            CLI::write("released {$release}");

            return EXIT_SUCCESS;
        }

        $acquire = $this->option('acquire');

        if (is_string($acquire)) {
            $r = $locks->acquire($acquire, $this->hasFlag('confirm'));
            CLI::write($r['ok'] ? "{$acquire} lock held by this node" : "{$acquire} lock NOT acquired: " . ($r['reason'] ?? '?'), $r['ok'] ? 'green' : 'red');

            return $r['ok'] ? EXIT_SUCCESS : EXIT_ERROR;
        }

        Support::exclusive('heartbeat', static function () use ($locks): void {
            foreach (['auth', 'cron'] as $kind) {
                if ($locks->isHeld($kind)) {
                    $locks->renewManual($kind); // leases live on edit activity, not on the clock
                } elseif ($kind === 'cron' && $locks->holder('cron') === null) {
                    $locks->acquire('cron'); // the scheduler lock is taken automatically when it is free
                }
            }
        });

        return EXIT_SUCCESS;
    }
}
