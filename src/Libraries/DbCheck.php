<?php

declare(strict_types=1);

namespace AdGo\Sync\Libraries;

use Config\Database as DbConfig;
use Throwable;

/**
 * Tries the database settings a superadmin is about to write to `.env`, BEFORE they are written: a wrong host or
 * password would lock everybody out, including the settings page that could fix it.
 *
 * It connects exactly the way the application will (the current `database.default` configuration with the proposed
 * values on top), then checks that the target is really this application's database: it has the application's
 * tables and it contains the account of the person making the change.
 */
class DbCheck
{
    /** `.env` suffix => key of the CodeIgniter database configuration array. */
    private const MAP = [
        'DBDriver' => 'DBDriver', 'hostname' => 'hostname', 'port' => 'port', 'database' => 'database',
        'username' => 'username', 'password' => 'password', 'DBPrefix' => 'DBPrefix', 'charset' => 'charset', 'DBCollat' => 'DBCollat',
    ];

    private const TABLES = ['migrations', 'users', 'auth_identities', 'sync_row_meta'];

    /**
     * @param array<string, string> $env the whole `.env` content AFTER the change (name => value)
     *
     * @return string|null null when the database works and is the right one, otherwise a message for the user
     */
    public static function test(array $env, string $accountEmail): ?string
    {
        $cfg = (array) (new DbConfig())->default;

        foreach (self::MAP as $suffix => $key) {
            $name = 'database.default.' . $suffix;

            if (array_key_exists($name, $env)) {
                $cfg[$key] = $key === 'port' ? (int) $env[$name] : $env[$name];
            }
        }

        $cfg['DBDebug'] = true;
        $cfg['DSN']     = '';

        @set_time_limit(40);

        // SQLite creates a missing file when it connects: look first, do not create anything
        if (($cfg['DBDriver'] ?? '') === 'SQLite3' && $cfg['database'] !== ':memory:') {
            $path = str_contains((string) $cfg['database'], DIRECTORY_SEPARATOR) ? (string) $cfg['database'] : WRITEPATH . $cfg['database'];

            if (! is_file($path)) {
                return 'The SQLite database file does not exist.';
            }
        }

        try {
            $db = DbConfig::connect($cfg, false);
            $db->initialize();
            $db->query('SELECT 1');

            foreach (self::TABLES as $t) {
                if (! $db->tableExists($t)) {
                    $db->close();

                    return "That database does not look like this application's: the table '{$t}' is missing.";
                }
            }

            $has = $db->table('auth_identities')->where(['type' => 'email_password', 'secret' => $accountEmail])->countAllResults();
            $db->close();

            if ($has === 0) {
                return 'Your own account (' . $accountEmail . ') does not exist in that database: you would not be able to sign in again.';
            }
        } catch (Throwable $e) {
            $m = $e->getMessage();

            foreach (array_filter([$cfg['password'] ?? '']) as $secret) {
                $m = str_replace((string) $secret, '***', $m);
            }

            return 'Cannot connect with these database settings: ' . mb_substr($m, 0, 200);
        }

        return null;
    }
}
