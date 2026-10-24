<?php

declare(strict_types=1);

namespace AdGo\Sync\Commands;

use AdGo\Sync\Libraries\Kernel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SyncNotify extends BaseCommand
{
    protected $group       = 'Sync';
    protected $name        = 'sync:notify';
    protected $description = 'Sends a message to Telegram and to the alert mailbox (to test the bot and the SMTP settings).';
    protected $usage       = 'sync:notify <text>';
    protected $arguments   = ['text' => 'The message'];
    protected $options     = [];

    public function run(array $params)
    {
        $notifier = Kernel::instance()->telegram();

        if (! $notifier->enabled()) {
            CLI::error('neither Telegram (sync.telegram.token, chat_id) nor e-mail (sync.mail.to, host) is configured');

            return EXIT_ERROR;
        }

        $text = implode(' ', $params) !== '' ? implode(' ', $params) : 'test message';
        $ok   = $notifier->send('[' . Kernel::instance()->opt->node . '] ' . $text);

        CLI::write($ok ? 'queued or sent (queued messages leave with the next tasks:run)' : 'could not be sent, see writable/logs', $ok ? 'green' : 'red');

        return $ok ? EXIT_SUCCESS : EXIT_ERROR;
    }
}
