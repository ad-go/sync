<?php

declare(strict_types=1);

namespace AdGo\Sync\Commands;

use CodeIgniter\CLI\CLI;
use AdGo\Sync\Libraries\Kernel;
use AdGo\Sync\Libraries\Support;

/**
 * For scheduled commands: two overlapping runs (two cron entries, cron + HTTP tick, a slow run) must not do the
 * same work twice. The second one says so and exits successfully.
 */
trait RunsExclusively
{
    /**
     * @param callable(): int $work returns the exit code
     */
    private function exclusively(string $lock, callable $work): int
    {
        $code = EXIT_SUCCESS;

        if (! Support::exclusive($lock, static function () use ($work, &$code): void {
            $code = $work();
        })) {
            CLI::write("another sync:{$lock} is already running; skipped.", 'yellow');
        }

        return $code;
    }

    /** The "scan first" step shared by sync:pull and sync:push. */
    private function scanFirst(Kernel $kernel): void
    {
        $scan = $kernel->scanner()->scan();
        CLI::write(sprintf('scan: +%d ~%d -%d (deferred %d)', $scan['inserted'], $scan['updated'], $scan['deleted'], $scan['deferred']));
    }
}
