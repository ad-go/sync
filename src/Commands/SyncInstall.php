<?php

declare(strict_types=1);

namespace AdGo\Sync\Commands;

use AdGo\Sync\Libraries\Assets;
use AdGo\Sync\Libraries\Installer;
use AdGo\Sync\Libraries\Kernel;
use AdGo\Sync\Libraries\Options;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use Config\Database;

class SyncInstall extends BaseCommand
{
    use ReadsOptions;

    protected $group       = 'Sync';
    protected $name        = 'sync:install';
    protected $description = 'Sets up .env (secret), app/Config/Sync.php (tables), app/Config/Tasks.php and the cron entry, creates the package tables and seeds existing rows.';
    protected $usage       = 'sync:install [--node NAME] [--peer-node NAME] [--peer-url URL] [--secret HEX] [--admin-email EMAIL] [--user-email EMAIL] [--no-migrate] [--no-seed] [--no-cron]';
    protected $arguments   = [];
    protected $options     = [
        '--node'       => 'Name of this node (written to .env when not set yet)',
        '--peer-node'  => 'Name of the other node',
        '--peer-url'   => 'Base URL of the other node',
        '--secret'     => 'Shared secret to use instead of generating one (pass the first node\'s secret to the others)',
        '--admin-email' => 'E-mail of the first superadmin to create with Shield (password from SYNC_INSTALL_PASSWORD)',
        '--admin-username' => 'Username of that superadmin (default: admin)',
        '--user-email'  => 'E-mail of a plain user (group user) to create with Shield (password from SYNC_INSTALL_PASSWORD)',
        '--user-username'  => 'Username of that user (default: user)',
        '--no-assets'  => 'Do not download the admin front-end files and the web fonts (run `sync:assets` later)',
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
        $this->authAction($installer);
        $this->loginRedirect($installer);
        $this->indexPage($installer);
        $this->users();

        if (! $this->hasFlag('no-assets')) {
            $this->assets();
        }

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

    private function authAction(Installer $installer): void
    {
        $result = $installer->ensureAuthAction(APPPATH . 'Config/Auth.php');

        match ($result) {
            'patched' => CLI::write('app/Config/Auth.php: TOTP 2FA registered for superadmins (backup: Auth.php.bak)', 'green'),
            'present' => CLI::write('app/Config/Auth.php already registers the TOTP 2FA action'),
            'missing' => CLI::write('app/Config/Auth.php not found yet; run `php spark shield:setup` first, then `php spark sync:install --no-migrate`', 'yellow'),
            default   => CLI::write("could not patch app/Config/Auth.php automatically; set \$actions['login'] = \\AdGo\\Sync\\Authentication\\Actions\\Totp2FA::class; by hand", 'yellow'),
        };
    }

    /**
     * Creates the first superadmin and/or a plain user with Shield, the same model the admin pages use. An account
     * whose username or e-mail already exists is left alone. The password comes from SYNC_INSTALL_PASSWORD, never
     * from the command line, so it does not end up in the shell history.
     */
    private function users(): void
    {
        $wanted = array_values(array_filter([
            ['group' => 'superadmin', 'email' => CLI::getOption('admin-email'), 'username' => CLI::getOption('admin-username') ?? 'admin'],
            ['group' => 'user', 'email' => CLI::getOption('user-email'), 'username' => CLI::getOption('user-username') ?? 'user'],
        ], static fn (array $w): bool => $w['email'] !== null));

        if ($wanted === []) {
            return;
        }

        $password = (string) (getenv('SYNC_INSTALL_PASSWORD') ?: '');

        if ($password === '') {
            CLI::write('SYNC_INSTALL_PASSWORD is not set: no user was created. Set it and run the install again.', 'red');

            return;
        }

        $users = model(UserModel::class);

        foreach ($wanted as $w) {
            if ($users->where('username', $w['username'])->first() !== null || $users->findByCredentials(['email' => $w['email']]) !== null) {
                CLI::write("{$w['email']}: a user with this e-mail or username exists; left as it is");
                continue;
            }

            $users->save(new User([
                'username' => $w['username'],
                'email'    => $w['email'],
                'password' => $password,
                'active'   => 1,
            ]));

            $user = $users->findById($users->getInsertID());
            $user->addGroup($w['group']);

            CLI::write("created {$w['username']} ({$w['email']}) in group {$w['group']}", 'green');
        }
    }

    /**
     * Downloads the pinned front-end files and the web fonts (SHA-256 checked). A failed download is reported and the
     * install goes on: the pages then use the CDN, and `php spark sync:assets` retries.
     */
    private function assets(): void
    {
        $root   = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR;
        $failed = 0;

        foreach (Assets::downloadPublic($root) as $path => $status) {
            $failed += str_starts_with($status, 'failed') ? 1 : 0;
        }

        Assets::writeFontsCss($root);

        if ($failed === 0) {
            CLI::write('public assets and web fonts are in place (public/assets)', 'green');
        } else {
            CLI::write("{$failed} asset(s) could not be downloaded; the pages fall back to the CDN. Run `php spark sync:assets` again later.", 'yellow');
        }
    }

    private function loginRedirect(Installer $installer): void
    {
        $url    = trim((string) config('Sync')->dashboardPath, '/');
        $result = $installer->ensureLoginRedirect(APPPATH . 'Config/Auth.php', $url);

        match ($result) {
            'patched' => CLI::write("app/Config/Auth.php: login now lands on '{$url}' (backup: Auth.php.redirect.bak)", 'green'),
            'present' => CLI::write('app/Config/Auth.php already sets a login redirect; left as it is'),
            'missing' => CLI::write('app/Config/Auth.php not found yet; run `php spark shield:setup` first', 'yellow'),
            default   => CLI::write("could not set the login redirect in app/Config/Auth.php; set \$redirects['login'] = '{$url}' by hand", 'yellow'),
        };
    }

    private function indexPage(Installer $installer): void
    {
        $result = $installer->ensureIndexPage(APPPATH . 'Config/App.php');

        match ($result) {
            'patched' => CLI::write('app/Config/App.php: $indexPage set to \'\' — clean URLs without index.php (backup: App.php.bak)', 'green'),
            'present' => CLI::write('app/Config/App.php already has $indexPage = \'\''),
            'missing' => CLI::write('app/Config/App.php not found; set $indexPage = \'\' by hand', 'yellow'),
            default   => CLI::write("could not patch app/Config/App.php automatically; set \$indexPage = ''; by hand", 'yellow'),
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
