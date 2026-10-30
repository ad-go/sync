<?php

declare(strict_types=1);

namespace AdGo\Sync\Libraries;

use CodeIgniter\Database\BaseConnection;
use Throwable;

/**
 * The file-system and system side of `sync:install`: .env, app/Config/Sync.php, app/Config/Tasks.php and the cron
 * entry that runs the scheduler. Every step reports what it did and never overwrites what the user already set.
 */
final class Installer
{
    /** @param (callable(string): array{0: int, 1: string})|null $shell test seam: runs a command, returns [exit code, output] */
    public function __construct(private readonly mixed $shell = null)
    {
    }

    // ---------------------------------------------------------------- .env

    /**
     * Sets each key that has no value yet (a commented `# key = ...` line is replaced, an existing value is kept).
     *
     * @param array<string, string> $values key => value, keys without the `sync.` prefix are used as given
     *
     * @return list<string> the keys written
     */
    public function ensureEnv(string $envFile, array $values, ?string $template = null): array
    {
        if (! is_file($envFile)) {
            file_put_contents($envFile, $template !== null && is_file($template) ? (string) file_get_contents($template) : '');
        }

        $env     = (string) file_get_contents($envFile);
        $written = [];

        foreach ($values as $key => $value) {
            $quoted = preg_quote($key, '/');

            if (preg_match('/^\s*' . $quoted . '\s*=\s*\S/m', $env) === 1) {
                continue;
            }

            $line = $key . ' = ' . $value;
            $env  = preg_match('/^\s*#?\s*' . $quoted . '\s*=.*$/m', $env) === 1
                ? preg_replace('/^\s*#?\s*' . $quoted . '\s*=.*$/m', addcslashes($line, '\\$'), $env, 1)
                : rtrim($env, "\r\n") . "\n" . $line . "\n";
            $written[] = $key;
        }

        if ($written !== []) {
            file_put_contents($envFile, $env);
        }

        return $written;
    }

    public static function newSecret(): string
    {
        return bin2hex(random_bytes(32));
    }

    // ------------------------------------------------------- Config\Sync

    /**
     * Every table of the database that may take part, parents before children.
     *
     * @return array{tables: array<string, array<string, mixed>>, shield: bool, skipped: array<string, string>}
     */
    public function discoverTables(BaseConnection $db, Options $opt): array
    {
        $prefix  = (string) $db->DBPrefix;
        $all     = array_map(static fn (string $t): string => $prefix !== '' && str_starts_with($t, $prefix) ? substr($t, strlen($prefix)) : $t, $db->listTables());
        $once    = array_diff($opt->bootstrapTables, array_keys(Options::SHIELD_USERS));
        $skipped = [];
        $tables  = [];

        foreach ($all as $t) {
            $why = match (true) {
                in_array($t, $opt->excluded, true)       => 'excluded (per node)',
                str_starts_with($t, 'sync_')             => 'package table',
                in_array($t, $once, true)                => 'copied once by sync:bootstrap',
                isset(Options::SHIELD_USERS[$t])         => '',
                default                                  => '',
            };

            if ($why !== '') {
                $skipped[$t] = $why;

                continue;
            }

            if (isset(Options::SHIELD_USERS[$t])) {
                $tables[$t] = Options::SHIELD_USERS[$t];

                continue;
            }

            $entry = $this->describe($db, $t, $prefix);

            if (is_string($entry)) {
                $skipped[$t] = $entry;

                continue;
            }

            $tables[$t] = $entry;
        }

        $names = array_keys($tables);

        foreach ($tables as $t => &$entry) {
            if (isset(Options::SHIELD_USERS[$t])) {
                continue;
            }

            $fk = $this->foreignKeys($db, $t, $names);

            if ($fk !== []) {
                $entry['fk'] = $fk;
            }
        }
        unset($entry);

        return ['tables' => $this->parentsFirst($tables), 'shield' => isset($tables['users']), 'skipped' => $skipped];
    }

    /** @return array<string, mixed>|string the table's entry, or the reason it cannot be synchronised */
    private function describe(BaseConnection $db, string $table, string $prefix): array|string
    {
        $keys   = [];
        $fields = [];

        foreach ($db->getFieldData($prefix . $table) as $f) {
            $fields[] = $f->name;

            if (! empty($f->primary_key)) {
                $keys[] = $f->name;
            }
        }

        if (count($keys) !== 1) {
            return $keys === [] ? 'no primary key' : 'composite primary key';
        }

        $entry = ['policy' => in_array('updated_at', $fields, true) ? 'tolerant' : 'sensitive'];

        if ($keys[0] !== 'id') {
            $entry['pk'] = $keys[0];
        }

        return $entry;
    }

    /**
     * @param list<string> $synced
     *
     * @return array<string, string> column => synchronised parent table
     */
    private function foreignKeys(BaseConnection $db, string $table, array $synced): array
    {
        $out = [];

        try {
            foreach ($db->getForeignKeyData((string) $db->DBPrefix . $table) as $fk) {
                $cols   = (array) $fk->column_name;
                $parent = (string) $fk->foreign_table_name;
                $prefix = (string) $db->DBPrefix;
                $parent = $prefix !== '' && str_starts_with($parent, $prefix) ? substr($parent, strlen($prefix)) : $parent;

                if (count($cols) === 1 && $parent !== $table && in_array($parent, $synced, true)) {
                    $out[(string) $cols[0]] = $parent;
                }
            }
        } catch (Throwable) {
        }

        return $out;
    }

    /**
     * @param array<string, array<string, mixed>> $tables
     *
     * @return array<string, array<string, mixed>>
     */
    private function parentsFirst(array $tables): array
    {
        $done = [];
        $todo = $tables;

        while ($todo !== []) {
            $progress = false;

            foreach ($todo as $t => $entry) {
                $deps = array_diff(array_unique(array_values($entry['fk'] ?? [])), [$t], array_keys($done));

                if ($deps === []) {
                    $done[$t] = $entry;
                    unset($todo[$t]);
                    $progress = true;
                }
            }

            if (! $progress) {
                return $done + $todo; // a reference cycle: keep the rest in database order
            }
        }

        return $done;
    }

    /** The `public array $tables = ...;` property for app/Config/Sync.php. */
    public function renderTables(array $discovered): string
    {
        $app = array_diff_key($discovered['tables'], Options::SHIELD_USERS);
        $php = '';

        foreach ($app as $name => $entry) {
            $php .= "        '{$name}' => " . $this->export($entry) . ",\n";
        }

        $body = $php === '' ? '[]' : "[\n{$php}    ]";

        return '    public array $tables = ' . ($discovered['shield'] ? '\\' . Options::class . '::SHIELD_USERS + ' : '') . $body . ';';
    }

    /** @param array<string, mixed> $entry */
    private function export(array $entry): string
    {
        $parts = [];

        foreach ($entry as $k => $v) {
            $parts[] = "'{$k}' => " . (is_array($v) ? $this->exportMap($v) : "'{$v}'");
        }

        return '[' . implode(', ', $parts) . ']';
    }

    /** @param array<string, string> $map */
    private function exportMap(array $map): string
    {
        $parts = [];

        foreach ($map as $k => $v) {
            $parts[] = "'{$k}' => '{$v}'";
        }

        return '[' . implode(', ', $parts) . ']';
    }

    /** The complete app/Config/Sync.php. */
    public function renderConfig(array $discovered, string $node, string $peerNode, string $peerUrl): string
    {
        $skipped = '';

        foreach ($discovered['skipped'] as $t => $why) {
            $skipped .= "     *   {$t}: {$why}\n";
        }

        return <<<PHP
            <?php

            namespace Config;

            use AdGo\\Sync\\Config\\Sync as BaseSync;

            class Sync extends BaseSync
            {
                // Node names, URL and secret are read from .env (sync.node, sync.peerNode, sync.peerUrl, sync.secret).

                /**
                 * Generated by `spark sync:install`; review the policies. 'tolerant' resolves concurrent edits of the same
                 * field by the newer updated_at, 'sensitive' quarantines them. Parents are listed before children.
                 * Not synchronised:
            {$skipped}     */
            {$this->renderTables($discovered)}
            }

            PHP;
    }

    // --------------------------------------------------------- Config\Tasks

    private const CALL = '\\AdGo\\Sync\\Tasks\\SyncTasks::register($schedule);';

    /** @return string 'created' | 'patched' | 'present' | 'manual' */
    public function ensureTasks(string $file): string
    {
        if (! is_file($file)) {
            file_put_contents($file, <<<'PHP'
                <?php

                namespace Config;

                use CodeIgniter\Tasks\Config\Tasks as BaseTasks;
                use CodeIgniter\Tasks\Scheduler;

                class Tasks extends BaseTasks
                {
                    public function init(Scheduler $schedule)
                    {
                        \AdGo\Sync\Tasks\SyncTasks::register($schedule);
                    }
                }

                PHP);

            return 'created';
        }

        $code = (string) file_get_contents($file);

        if (str_contains($code, 'SyncTasks::register')) {
            return 'present';
        }

        $patched = preg_replace('/(public\s+function\s+init\s*\(\s*Scheduler\s+\$schedule\s*\)[^{]*\{)/', "$1\n        " . addcslashes(self::CALL, '\\$') . "\n", $code, 1, $n);

        if ($n !== 1 || $patched === null) {
            return 'manual';
        }

        copy($file, $file . '.bak');
        file_put_contents($file, $patched);

        return 'patched';
    }

    // --------------------------------------------------------------- Config\Auth (TOTP action for superadmins)

    private const AUTH_PATTERN = "/'login'(\\s*)=>(\\s*)null,/";
    private const AUTH_REPLACE = "'login'$1=>$2\\AdGo\\Sync\\Authentication\\Actions\\Totp2FA::class,";

    /** @return string 'patched' | 'present' | 'missing' | 'manual' */
    public function ensureAuthAction(string $file): string
    {
        if (! is_file($file)) {
            return 'missing'; // app/Config/Auth.php is published by `php spark shield:setup`, not by this package
        }

        $code = (string) file_get_contents($file);

        if (str_contains($code, 'Totp2FA::class')) {
            return 'present';
        }

        $patched = preg_replace(self::AUTH_PATTERN, self::AUTH_REPLACE, $code, 1, $n);

        if ($n !== 1 || $patched === null) {
            return 'manual';
        }

        copy($file, $file . '.bak');
        file_put_contents($file, $patched);

        return 'patched';
    }

    // --------------------------------------------------------------- Config\App::$indexPage

    /** @return string 'patched' | 'present' | 'missing' | 'manual' */
    public function ensureIndexPage(string $file): string
    {
        if (! is_file($file)) {
            return 'missing';
        }

        $code = (string) file_get_contents($file);

        if (preg_match('/\$indexPage\s*=\s*\'\'/', $code) === 1) {
            return 'present';
        }

        $patched = preg_replace("/(\\\$indexPage\\s*=\\s*)'index\\.php'/", "$1''", $code, 1, $n);

        if ($n !== 1 || $patched === null) {
            return 'manual';
        }

        copy($file, $file . '.bak');
        file_put_contents($file, $patched);

        return 'patched';
    }

    // ----------------------------------------------------------------- cron

    /**
     * Whether the scheduler is started every minute, and if not, tries to add it.
     *
     * @return array{status: string, line: string} status: present | added | missing (could not add; `line` is what to add)
     */
    public function ensureCron(string $spark, bool $windows = false, string $php = PHP_BINARY): array
    {
        $php  = $php !== '' ? $php : 'php';
        $line = '* * * * * ' . $php . ' ' . $spark . ' tasks:run >> /dev/null 2>&1';

        if ($windows) {
            $task = 'CodeIgniter tasks:run';
            [$rc]  = $this->run('schtasks /Query /TN "' . $task . '"');

            if ($rc === 0) {
                return ['status' => 'present', 'line' => $line];
            }

            [$rc] = $this->run('schtasks /Create /F /SC MINUTE /MO 1 /TN "' . $task . '" /TR "\"' . $php . '\" \"' . $spark . '\" tasks:run"');

            return ['status' => $rc === 0 ? 'added' : 'missing', 'line' => '"' . $php . '" "' . $spark . '" tasks:run'];
        }

        [$rc, $current] = $this->run('crontab -l');

        if ($rc === 0 && preg_match('/^[^#\n]*\btasks:run\b/m', $current) === 1) {
            return ['status' => 'present', 'line' => $line];
        }

        if ($rc !== 0 && ! str_contains(strtolower($current), 'no crontab')) {
            return ['status' => 'missing', 'line' => $line]; // crontab unavailable (shared hosting without shell access)
        }

        $new = rtrim($rc === 0 ? $current : '', "\n") . "\n" . $line . "\n";
        $tmp = tempnam(sys_get_temp_dir(), 'cron');
        file_put_contents($tmp, ltrim($new, "\n"));
        [$rc2] = $this->run('crontab ' . escapeshellarg($tmp));
        @unlink($tmp);

        return ['status' => $rc2 === 0 ? 'added' : 'missing', 'line' => $line];
    }

    /** @return array{0: int, 1: string} */
    private function run(string $command): array
    {
        if ($this->shell !== null) {
            return ($this->shell)($command);
        }

        if (! function_exists('exec') || in_array('exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true)) {
            return [127, 'exec is disabled'];
        }

        $out = [];
        $rc  = 1;
        @exec($command . ' 2>&1', $out, $rc);

        return [$rc, implode("\n", $out)];
    }
}
