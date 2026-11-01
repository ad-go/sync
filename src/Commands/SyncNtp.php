<?php

declare(strict_types=1);

namespace AdGo\Sync\Commands;

use AdGo\Sync\Libraries\Kernel;
use AdGo\Sync\Libraries\NtpClock;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SyncNtp extends BaseCommand
{
    protected $group       = 'Sync';
    protected $name        = 'sync:ntp';
    protected $description = "Measures this node's clock against the NTP servers of sync.ntpServers and keeps the result (the offset used by sync.clockCorrect).";
    protected $usage       = 'sync:ntp [server ...]';
    protected $arguments   = ['server' => 'NTP servers to ask instead of sync.ntpServers'];
    protected $options     = [];

    public function run(array $params)
    {
        $servers = $params !== [] ? array_values($params) : Kernel::instance()->opt->ntpServers;

        if ($servers === []) {
            CLI::error('no NTP server: set sync.ntpServers (e.g. pool.ntp.org) or pass one as an argument');

            return EXIT_ERROR;
        }

        $ntp = new NtpClock();
        $r   = $ntp->measure($servers);

        foreach ($servers as $host) {
            $x = $r['servers'][$host] ?? 'no answer';
            CLI::write(sprintf('  %-28s %s', $host, is_array($x) ? sprintf('clock %+.3f s from the server, %d ms', -$x['offset'], round($x['rtt'] * 1000)) : $x), is_array($x) ? 'green' : 'yellow');
        }

        if ($r['offset'] === null) {
            CLI::error('no server answered (is outbound UDP port 123 open?)');

            return EXIT_ERROR;
        }

        $ntp->save($r['offset']);
        CLI::write(sprintf('this clock is %+.3f s from real time (median of %d); %s', -$r['offset'], $r['answers'], Kernel::instance()->opt->clockCorrect ? 'stamps are corrected by it' : 'sync.clockCorrect is off, nothing is corrected'));

        return EXIT_SUCCESS;
    }
}
