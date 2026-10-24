<?php

declare(strict_types=1);

namespace AdGo\Sync\Tasks;

use CodeIgniter\Tasks\Scheduler;
use CodeIgniter\Tasks\Task;
use AdGo\Sync\Libraries\Kernel;

/**
 * Integration with codeigniter4/tasks. In app/Config/Tasks.php:
 *
 *     public function init(Scheduler $schedule)
 *     {
 *         \AdGo\Sync\Tasks\SyncTasks::register($schedule);
 *
 *         // your own jobs run only on the node holding the cron lock:
 *         \AdGo\Sync\Tasks\SyncTasks::onCronHolder($schedule, 'reports:daily')->daily('02:00');
 *     }
 */
final class SyncTasks
{
    /** The sync jobs themselves run on BOTH nodes. */
    public static function register(Scheduler $schedule): void
    {
        $schedule->command('sync:auth-heartbeat')->everyMinute()->named('sync-heartbeat');
        $schedule->command('sync:pull')->everyMinute()->named('sync-pull');

        $kernel = Kernel::instance();

        if ($kernel->opt->passive) {
            $schedule->command('sync:push')->everyMinute()->named('sync-push');
        }
        $schedule->command('sync:files')->everyFiveMinutes()->named('sync-files');
        $schedule->command('sync:prune')->daily('03:30')->named('sync-prune');

        // Telegram messages wait in the queue; drain it once a minute (no permanent worker needed).
        $queue = (string) ($kernel->opt->telegram['queue'] ?? '');

        if ($queue !== '' && $kernel->telegram()->enabled()) {
            $schedule->command("queue:work {$queue} --stop-when-empty -max-time 45")->everyMinute()->named('sync-telegram-queue');
        }
    }

    /**
     * An Artisan-style spark command that runs only while this node holds the
     * `cron` lock (prevents double execution on the two nodes).
     */
    public static function onCronHolder(Scheduler $schedule, string $command): Task
    {
        return $schedule->call(static function () use ($command): void {
            if (Kernel::instance()->locks()->isHeld('cron')) {
                command($command);
            }
        })->named('cron-lock:' . $command);
    }
}
