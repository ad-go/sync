<?php

declare(strict_types=1);

namespace AdGo\Sync\Jobs;

use CodeIgniter\Queue\BaseJob;
use AdGo\Sync\Libraries\Kernel;

/**
 * Delivers one Telegram API call. The bot token is NOT stored in the queue:
 * it is read from the configuration when the job runs.
 *
 * Data: ['method' => 'sendMessage', 'params' => array<string, string>]
 */
class TelegramJob extends BaseJob
{
    /** Key in Config\Queue::$jobHandlers (registered by AdGo\Sync\Config\Registrar). */
    public const NAME = 'sync-telegram';

    protected int $retryAfter = 60;
    protected int $tries      = 6;

    public function process(): void
    {
        Kernel::instance()->telegram()->deliver((string) $this->data['method'], (array) $this->data['params']);
    }
}
