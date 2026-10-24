<?php

declare(strict_types=1);

namespace AdGo\Sync\Commands;

use AdGo\Sync\Libraries\Installer;
use AdGo\Sync\Libraries\Kernel;
use AdGo\Sync\Libraries\Options;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

class SyncInstall extends BaseCommand
{
    use ReadsOptions;

    protected $group       = 'Sync';
    protected $name        = 'sync:install';
    protected $description = 'Sets up .env (secret), app/Config/Sync.php (tables), app/Config/Tasks.php and the cron entry, creates the package tables and seeds existing rows.';
    protected $usage       = 'sync:install [--node NAME] [--peer-node NAME] [--peer-url URL] [--secret HEX] [--no-migrate] [--no-seed] [--no-cron]';
    protected $arguments   = [];
    protected $options     = [
        '--node'       => 'Name of this node (written to .env when not set yet)',
        '--peer-node'  => 'Name of the other node',
        '--peer-url'   => 'Base URL of the other node',
        '--secret'     => 'Shared secret to use instead of generating one (pass the first node\'s secret to the others)',
        '--no-migrate' => 'Skip the migrations',
        '--no-seed'    => 'Skip seeding metadata for existing rows',
        '--no-cron'    => 'Do not check or create the cron entry for tasks:run',
    ];

    public function run(array $params)
    {
        $installer = new Installer();
        $this->env($installer);

        $this->migrate();

        $options = Options::fromConfig(config('Sync'));
        $this->config($installer, $options);
        $this->tasks($installer);

        if (! $this->hasFlag('no-seed')) {
            Kernel::use(null);
            $n = Kernel::instance()->scanner()->seed();
            CLI::write("metadata created for {$n} existing row(s) (deterministic uuids; assumes both databases hold the same data now)");
        }

        if (! $this->hasFlag('no-cron')) {
            $this->cron($installer);
        }

        return EXIT_SUCCESS;
    }

    private function env(Installer $installer): void
    {
        $generated = $this->option('secret') ?? Installer::newSecret();
        $values    = ['sync.secret' => (string) $generated];

        foreach (['node' => 'sync.node', 'peer-node' => 'sync.peerNode', 'peer-url' => 'sync.peerUrl'] as $opt => $key) {
            if (($v = $this->option($opt)) !== null && $v !== '') {
                $values[$key] = $v;
            }
        }

        $written = $installer->ensureEnv(ROOTPATH . '.env', $values, ROOTPATH . 'env');

        foreach ($written as $key) {
            CLI::write('.env: ' . $key . ($key === 'sync.secret' ? ' generated (copy the same value to the other nodes: --secret=...)' : ' set'), 'green');
        }

        if ($written === []) {
            CLI::write('.env already holds the sync settings');
        }

        foreach (['sync.node', 'sync.peerNode', 'sync.peerUrl'] as $key) {
            if (! in_array($key, $written, true) && (string) env($key, '') === '') {
                CLI::write("set {$key} in .env (or pass the option) before the first sync", 'yellow');
            }
        }
    }

    private function migrate(): void
    {
        if ($this->hasFlag('no-migrate')) {
            return;
        }

        /** @var \CodeIgniter\Database\MigrationRunner $runner */
        $runner = service('migrations');
        $runner->setNamespace('AdGo\Sync');
        $runner->latest();
        CLI::write('package tables created', 'green');
    }

    private function config(Installer $installer, Options $options): void
    {
        $target     = APPPATH . 'Config/Sync.php';
        $discovered = $installer->discoverTables(Database::connect(config('Sync')->dbGroup), $options);
        $count      = count($discovered['tables']);

        if (! is_file($target)) {
            file_put_contents($target, $installer->renderConfig($discovered, $options->node, $options->peerNode, $options->peerUrl));
            CLI::write("published {$target} with {$count} table(s)", 'green');
        } else {
            $missing = array_diff(array_keys($discovered['tables']), array_keys($options->tables));
            CLI::write('config already published: ' . $target);

            if ($missing !== []) {
                CLI::write('tables not in $tables yet: ' . implode(', ', $missing), 'yellow');
                CLI::write("add them (see the proposal below) and run `php spark sync:install --no-migrate`:\n" . $installer->renderTables($discovered), 'yellow');
            }
        }

        foreach ($discovered['skipped'] as $table => $why) {
            if ($why === 'no primary key' || $why === 'composite primary key') {
                CLI::write("table {$table} not synchronised: {$why}", 'yellow');
            }
        }
    }

    private function tasks(Installer $installer): void
    {
        $result = $installer->ensureTasks(APPPATH . 'Config/Tasks.php');

        match ($result) {
            'created' => CLI::write('created app/Config/Tasks.php with SyncTasks::register()', 'green'),
            'patched' => CLI::write('app/Config/Tasks.php: SyncTasks::register() added (backup: Tasks.php.bak)', 'green'),
            'present' => CLI::write('app/Config/Tasks.php already registers the sync tasks'),
            default   => CLI::write('add \AdGo\Sync\Tasks\SyncTasks::register($schedule); to init() in app/Config/Tasks.php', 'yellow'),
        };
    }

    private function cron(Installer $installer): void
    {
        $r = $installer->ensureCron(ROOTPATH . 'spark', DIRECTORY_SEPARATOR === '\\');

        match ($r['status']) {
            'present' => CLI::write('cron: tasks:run is already scheduled'),
            'added'   => CLI::write('cron: tasks:run added (every minute)', 'green'),
            default   => CLI::write("cron: could not be created automatically; add this to the crontab (or the host's cron panel):\n  " . $r['line'], 'yellow'),
        };
    }
}
