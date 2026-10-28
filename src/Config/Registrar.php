<?php

declare(strict_types=1);

namespace AdGo\Sync\Config;

use AdGo\Sync\Filters\AuthWriteLock;
use AdGo\Sync\Filters\SyncHmac;
use AdGo\Sync\Jobs\TelegramJob;

/**
 * Auto-discovered by CodeIgniter: registers the filter aliases of the package.
 */
class Registrar
{
    /**
     * @return array<string, mixed>
     */
    public static function Filters(): array
    {
        $prefix = trim((string) config('Sync')->routePrefix, '/');

        return [
            'aliases' => [
                'syncHmac'      => SyncHmac::class,
                'authWriteLock' => AuthWriteLock::class,
            ],
            'globals' => [
                'before' => [
                    'csrf' => ['except' => [$prefix . '/*']],
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<string, class-string>>
     */
    public static function Queue(): array
    {
        return ['jobHandlers' => [TelegramJob::NAME => TelegramJob::class]];
    }
}
