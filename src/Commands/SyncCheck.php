<?php

declare(strict_types=1);

namespace AdGo\Sync\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use AdGo\Sync\Libraries\Kernel;
use AdGo\Sync\Libraries\Reachability;
use Throwable;

class SyncCheck extends BaseCommand
{
    protected $group       = 'Sync';
    protected $name        = 'sync:check';
    protected $description = 'Validates how reachable this node is (internet / lan / punch / relay) against how it is configured.';
    protected $usage       = 'sync:check [peer] [--alert]';
    protected $arguments   = ['peer' => 'Check only this peer (default: every peer)'];
    protected $options     = ['--alert' => 'Send a Telegram / e-mail alert when the verdict turns to FAIL, and when it recovers (for cron)'];

    use ReadsOptions;

    public function run(array $params)
    {
        $kernel = Kernel::instance();

        try {
            $r = $kernel->reachability()->run($params[0] ?? null);
        } catch (Throwable $e) {
            CLI::error('check failed: ' . $e->getMessage());

            return EXIT_ERROR;
        }

        $o = $kernel->opt;
        CLI::write(sprintf('node %s (%s)%s', $o->node, $o->passive ? 'passive / punch' : 'dial-in', $o->selfUrl !== '' ? ' at ' . $o->selfUrl : ''), 'light_cyan');

        foreach ($r['peers'] as $p) {
            CLI::newLine();
            CLI::write(sprintf('peer %s  %s  [%s]', $p['peer'], $p['peerUrl'] ?: '(passive)', $p['peerClass']), 'white');

            $out = $p['outbound'];
            CLI::write('  outbound  ' . ($out === null ? 'n/a (the peer dials in)' : ($out['ok']
                ? sprintf('ok    %d ms, clock %+.2f s, seen by the peer as %s', $out['rtt_ms'], $out['skew_s'], $out['egress'] ?: '?')
                : 'FAILED')));

            $in = $p['inbound'];
            CLI::write('  inbound   ' . ($in === null ? 'n/a' : ($in['ok'] ? sprintf('ok    %d ms (the peer reached us at its URL for this node)', $in['rtt_ms']) : 'FAILED')));
            CLI::write(sprintf('  mode      declared %s, observed %s%s', $p['declared'], $p['observed'], isset($p['via']) ? ' via ' . $p['via'] : ''));

            foreach ($p['notes'] as $n) {
                CLI::write('  - ' . $n, 'yellow');
            }

            $this->verdict($p['verdict'], '  ');
        }

        if ($r['relayed'] !== []) {
            CLI::newLine();
            CLI::write('behind relays', 'white');

            foreach ($r['relayed'] as $x) {
                CLI::write(
                    sprintf('  %-12s via %-10s %-6s %s', $x['node'], $x['via'], $x['mode'], $x['ok']
                        ? ($x['seen'] !== null ? "ok    last contact {$x['seen']} s ago" : sprintf('ok    %d ms from the relay, clock %+.2f s', $x['rtt_ms'], $x['skew_s']))
                        : 'FAILED ' . $x['error']),
                    $x['ok'] ? 'green' : 'yellow',
                );
            }
        }

        if ($r['ntp'] !== null) {
            CLI::newLine();
            CLI::write(sprintf(
                'ntp       %s%s',
                $r['ntp']['offset_s'] === null ? '' : sprintf('clock %+.3f s from real time, measured %d s ago; ', -$r['ntp']['offset_s'], $r['ntp']['age_s']),
                $r['ntp']['note'] . ($r['ntp']['correct'] ? ' [stamps corrected]' : ''),
            ), $r['ntp']['warn'] ? 'yellow' : 'green');
        }

        CLI::newLine();
        $this->verdict($r['verdict'], '');

        if ($this->hasFlag('alert')) {
            $this->alert($kernel, $r);
        }

        return $r['verdict'] === Reachability::FAIL ? EXIT_ERROR : EXIT_SUCCESS;
    }

    /** One message per change (down, recovered), so a cron run every few minutes does not repeat itself. */
    private function alert(Kernel $kernel, array $r): void
    {
        $file = WRITEPATH . 'sync-check.state';
        $was  = is_file($file) ? trim((string) file_get_contents($file)) : Reachability::OK;
        $now  = $r['verdict'] === Reachability::FAIL ? Reachability::FAIL : Reachability::OK;

        if ($now === $was) {
            return;
        }

        $text = 'sync:check ' . ($now === Reachability::FAIL ? 'FAILED' : 'recovered');

        foreach ($r['peers'] as $p) {
            if ($p['verdict'] === Reachability::FAIL) {
                $text .= "
" . $p['peer'] . ': ' . implode('; ', $p['notes'] ?: ['unreachable']);
            }
        }

        if ($kernel->telegram()->send('[' . $kernel->opt->node . '] ' . $text)) {
            file_put_contents($file, $now);
        }
    }

    private function verdict(string $v, string $indent): void
    {
        CLI::write($indent . 'verdict: ' . strtoupper($v), ['ok' => 'green', 'warn' => 'yellow', 'fail' => 'red'][$v] ?? 'white');
    }
}
