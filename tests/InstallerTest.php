<?php

declare(strict_types=1);

namespace AdGo\Sync\Tests;

use AdGo\Sync\Libraries\Installer;
use AdGo\Sync\Libraries\Options;
use CodeIgniter\Database\Config as DbConfig;
use PHPUnit\Framework\TestCase;

final class InstallerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/sync-installer-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (array_diff(scandir($this->dir) ?: [], ['.', '..']) as $f) {
            unlink($this->dir . '/' . $f);
        }

        rmdir($this->dir);
    }

    public function testTheSecretIsGeneratedOnceAndExistingValuesAreKept(): void
    {
        $env = $this->dir . '/.env';
        file_put_contents($env, "CI_ENVIRONMENT = production\n# sync.secret = \nsync.node = beta\n");
        $i = new Installer();

        $this->assertSame(['sync.secret', 'sync.peerNode'], $i->ensureEnv($env, ['sync.secret' => Installer::newSecret(), 'sync.node' => 'x', 'sync.peerNode' => 'hub']));
        $text = (string) file_get_contents($env);
        $this->assertMatchesRegularExpression('/^sync\.secret = [0-9a-f]{64}$/m', $text);
        $this->assertStringContainsString('sync.node = beta', $text, 'a value that is already set is never replaced');
        $this->assertStringContainsString('sync.peerNode = hub', $text);
        $this->assertSame(1, substr_count($text, 'sync.secret'), 'the commented line was replaced, not duplicated');

        $this->assertSame([], $i->ensureEnv($env, ['sync.secret' => 'other']), 'running again changes nothing');
    }

    public function testEnvIsCreatedFromTheTemplate(): void
    {
        file_put_contents($this->dir . '/env', "CI_ENVIRONMENT = production\n");
        (new Installer())->ensureEnv($this->dir . '/.env', ['sync.secret' => 'abc'], $this->dir . '/env');

        $this->assertSame("CI_ENVIRONMENT = production\nsync.secret = abc\n", file_get_contents($this->dir . '/.env'));
    }

    public function testTablesAreDiscoveredWithKeysForeignKeysAndOrder(): void
    {
        $db = DbConfig::connect(['DBDriver' => 'SQLite3', 'database' => ':memory:', 'DBDebug' => true], false);
        $db->query('PRAGMA foreign_keys = ON');
        $db->query('CREATE TABLE products (id INTEGER PRIMARY KEY, category_id INTEGER, name TEXT, updated_at TEXT, FOREIGN KEY (category_id) REFERENCES categories(id))');
        $db->query('CREATE TABLE categories (id INTEGER PRIMARY KEY, name TEXT)');
        $db->query('CREATE TABLE slugs (slug TEXT PRIMARY KEY, product_id INTEGER, FOREIGN KEY (product_id) REFERENCES products(id))');
        $db->query('CREATE TABLE no_key (a TEXT)');
        $db->query('CREATE TABLE pair (a INTEGER, b INTEGER, PRIMARY KEY (a, b))');
        $db->query('CREATE TABLE queue_jobs (id INTEGER PRIMARY KEY)');
        $db->query('CREATE TABLE migrations (id INTEGER PRIMARY KEY)');
        $db->query('CREATE TABLE sync_cursor (id INTEGER PRIMARY KEY)');
        $db->query('CREATE TABLE settings (id INTEGER PRIMARY KEY)');

        $opt = Options::fromArray(['node' => 'a', 'peerNode' => 'b', 'bootstrapTables' => ['users', 'settings']]);
        $d   = (new Installer())->discoverTables($db, $opt);

        $this->assertSame(['categories', 'products', 'slugs'], array_keys($d['tables']), 'parents before children');
        $this->assertSame('tolerant', $d['tables']['products']['policy'], 'updated_at allows last-writer-wins');
        $this->assertSame('sensitive', $d['tables']['categories']['policy']);
        $this->assertSame(['category_id' => 'categories'], $d['tables']['products']['fk']);
        $this->assertSame('slug', $d['tables']['slugs']['pk']);
        $this->assertSame('no primary key', $d['skipped']['no_key']);
        $this->assertSame('composite primary key', $d['skipped']['pair']);
        $this->assertSame('excluded (per node)', $d['skipped']['queue_jobs']);
        $this->assertSame('package table', $d['skipped']['sync_cursor']);
        $this->assertSame('copied once by sync:bootstrap', $d['skipped']['settings']);
        $this->assertFalse($d['shield']);
    }

    public function testShieldUsersComeFirstAndTheGeneratedConfigIsValidPhp(): void
    {
        $db = DbConfig::connect(['DBDriver' => 'SQLite3', 'database' => ':memory:', 'DBDebug' => true], false);
        $db->query('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT)');
        $db->query('CREATE TABLE auth_identities (id INTEGER PRIMARY KEY, user_id INTEGER)');
        $db->query('CREATE TABLE auth_groups_users (id INTEGER PRIMARY KEY, user_id INTEGER, "group" TEXT)');
        $db->query('CREATE TABLE posts (id INTEGER PRIMARY KEY, user_id INTEGER, FOREIGN KEY (user_id) REFERENCES users(id))');

        $i    = new Installer();
        $d    = $i->discoverTables($db, Options::fromArray(['node' => 'a', 'peerNode' => 'b', 'bootstrapTables' => ['users', 'auth_identities', 'auth_groups_users']]));
        $code = $i->renderConfig($d, 'a', 'b', 'https://b.example');

        $this->assertTrue($d['shield']);
        $this->assertSame(['user_id' => 'users'], $d['tables']['posts']['fk']);
        $this->assertStringContainsString('SHIELD_USERS + [', $code);
        $this->assertStringContainsString("'posts' =>", $code);
        $this->assertNotEmpty(token_get_all($code, TOKEN_PARSE), 'the generated file parses');
    }

    public function testTasksIsCreatedPatchedAndLeftAlone(): void
    {
        $i    = new Installer();
        $file = $this->dir . '/Tasks.php';

        $this->assertSame('created', $i->ensureTasks($file));
        $this->assertStringContainsString('SyncTasks::register($schedule)', (string) file_get_contents($file));
        $this->assertSame('present', $i->ensureTasks($file));

        file_put_contents($file, "<?php\nclass Tasks\n{\n    public function init(Scheduler \$schedule)\n    {\n        \$schedule->command('foo')->daily();\n    }\n}\n");
        $this->assertSame('patched', $i->ensureTasks($file));
        $code = (string) file_get_contents($file);
        $this->assertStringContainsString("SyncTasks::register(\$schedule);\n", $code);
        $this->assertStringContainsString("\$schedule->command('foo')", $code, 'existing tasks are kept');
        $this->assertFileExists($file . '.bak');
        $this->assertNotEmpty(token_get_all($code, TOKEN_PARSE));

        file_put_contents($file, "<?php\nclass Tasks {}\n");
        $this->assertSame('manual', $i->ensureTasks($file));
    }

    public function testCronIsDetectedOrAdded(): void
    {
        $calls = [];
        $make  = static function (array $script) use (&$calls): Installer {
            return new Installer(static function (string $cmd) use (&$calls, $script): array {
                $calls[] = $cmd;

                foreach ($script as $prefix => $answer) {
                    if (str_starts_with($cmd, $prefix)) {
                        return $answer;
                    }
                }

                return [1, ''];
            });
        };

        $r = $make(['crontab -l' => [0, "MAILTO=x\n* * * * * php /srv/app/spark tasks:run\n"]])->ensureCron('/srv/app/spark', false, 'php');
        $this->assertSame('present', $r['status']);

        $calls = [];
        $r     = $make(['crontab -l' => [1, 'no crontab for www'], 'crontab ' => [0, '']])->ensureCron('/srv/app/spark', false, '/usr/bin/php');
        $this->assertSame('added', $r['status']);
        $this->assertSame('* * * * * /usr/bin/php /srv/app/spark tasks:run >> /dev/null 2>&1', $r['line']);

        $r = $make(['crontab -l' => [127, 'exec is disabled']])->ensureCron('/srv/app/spark', false, 'php');
        $this->assertSame('missing', $r['status'], 'no crontab available: the line is handed to the user');

        $r = $make(['crontab -l' => [0, "# * * * * * php spark tasks:run\n"], 'crontab ' => [0, '']])->ensureCron('/srv/app/spark', false, 'php');
        $this->assertSame('added', $r['status'], 'a commented line does not count');
    }
}
