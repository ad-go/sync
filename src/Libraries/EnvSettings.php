<?php

declare(strict_types=1);

namespace AdGo\Sync\Libraries;

/**
 * The variables of `.env` that may be edited from the settings page (a whitelist: database access, the
 * encryption key, the shared sync secret and the node names stay in the file). Each entry says how its value is
 * validated; secrets are never shown, only whether they are set.
 *
 * type: url | baseurl | bool | int | path | token | text | secret
 */
class EnvSettings
{
    /** @var array<string, array<string, mixed>> */
    public const FIELDS = [
        // --- application
        'app.baseURL' => ['group' => 'Application', 'label' => 'Site address', 'type' => 'baseurl', 'required' => true,
            'hint' => 'https://…/ with the trailing slash. A wrong address breaks redirects and links.'],
        'app.forceGlobalSecureRequests' => ['group' => 'Application', 'label' => 'Force HTTPS', 'type' => 'bool', 'required' => true,
            'hint' => 'Redirect every request to https.'],
        'app.tickToken' => ['group' => 'Application', 'label' => 'Scheduler URL token', 'type' => 'secret', 'min' => 16, 'max' => 64, 'generate' => true,
            'hint' => 'Part of /sync-tick/<token>, called by the external cron. After a change the cron URL must be updated too.'],
        // --- sync
        'sync.peerUrl' => ['group' => 'Synchronisation', 'label' => 'Other node (URL)', 'type' => 'url', 'required' => true,
            'hint' => 'Address of the other site, without trailing slash. A wrong value stops the synchronisation.'],
        'sync.files.dir' => ['group' => 'Synchronisation', 'label' => 'Synchronised files directory', 'type' => 'path',
            'hint' => 'Absolute path, writable by the web server (default: writable/sync-files). Pictures and other synchronised files live here.'],
        'sync.leaseTtl' => ['group' => 'Synchronisation', 'label' => 'Edit lease (seconds)', 'type' => 'int', 'min' => 30, 'max' => 3600, 'hint' => 'Idle time after which an edit lock on users is freed.'],
        'sync.lockTtl' => ['group' => 'Synchronisation', 'label' => 'Manual lock (seconds)', 'type' => 'int', 'min' => 30, 'max' => 3600, 'hint' => 'Heartbeat timeout of the auth / cron locks.'],
        'sync.pullBatch' => ['group' => 'Synchronisation', 'label' => 'Changes per request', 'type' => 'int', 'min' => 10, 'max' => 1000, 'hint' => 'Journal entries fetched from the other node at once.'],
        'sync.replayWindow' => ['group' => 'Synchronisation', 'label' => 'Clock tolerance (seconds)', 'type' => 'int', 'min' => 30, 'max' => 3600, 'hint' => 'Largest clock difference between the nodes accepted for signed requests.'],
        'sync.keepJournalDays' => ['group' => 'Synchronisation', 'label' => 'Keep journal (days)', 'type' => 'int', 'min' => 1, 'max' => 3650, 'hint' => 'sync:prune removes older entries the other node has applied.'],
        'sync.keepConflictDays' => ['group' => 'Synchronisation', 'label' => 'Keep decided conflicts (days)', 'type' => 'int', 'min' => 1, 'max' => 3650, 'hint' => ''],
        // --- database (a change is tried against the database BEFORE it is written, see DbCheck)
        'database.default.DBDriver' => ['group' => 'Database', 'label' => 'Driver', 'type' => 'choice', 'options' => ['MySQLi', 'Postgre', 'SQLite3'], 'required' => true,
            'hint' => 'MySQLi for MySQL / MariaDB.'],
        'database.default.hostname' => ['group' => 'Database', 'label' => 'Host', 'type' => 'host',
            'hint' => 'localhost (unix socket), an IP address or a host name. Not used by SQLite3.'],
        'database.default.port' => ['group' => 'Database', 'label' => 'Port', 'type' => 'int', 'min' => 1, 'max' => 65535, 'hint' => '3306 for MySQL, 5432 for PostgreSQL. Not used while the host is localhost (unix socket).'],
        'database.default.database' => ['group' => 'Database', 'label' => 'Database name', 'type' => 'text', 'pattern' => '~^[A-Za-z0-9_$./:\\\\-]{1,200}$~', 'required' => true,
            'hint' => 'For SQLite3 the file name.'],
        'database.default.username' => ['group' => 'Database', 'label' => 'User', 'type' => 'text', 'pattern' => '~^[A-Za-z0-9_.@$-]{1,64}$~', 'hint' => ''],
        'database.default.password' => ['group' => 'Database', 'label' => 'Password', 'type' => 'secret', 'free' => true,
            'hint' => 'Cannot contain quotes, backslash, # or ${ (they cannot be stored in .env safely).'],
        'database.default.DBPrefix' => ['group' => 'Database', 'label' => 'Table prefix', 'type' => 'text', 'pattern' => '~^[A-Za-z0-9_]{1,16}$~', 'hint' => 'Empty = none. Changing it makes the application look for other tables.'],
        'database.default.charset' => ['group' => 'Database', 'label' => 'Character set', 'type' => 'text', 'pattern' => '~^[A-Za-z0-9_]{1,32}$~', 'hint' => 'utf8mb4 recommended.'],
        'database.default.DBCollat' => ['group' => 'Database', 'label' => 'Collation', 'type' => 'text', 'pattern' => '~^[A-Za-z0-9_]{1,64}$~', 'hint' => ''],
        // --- telegram
        'sync.telegram.token' => ['group' => 'Telegram alerts', 'label' => 'Bot token', 'type' => 'secret', 'pattern' => '/^\d{6,12}:[A-Za-z0-9_-]{30,50}$/', 'hint' => 'Empty = alerts off.'],
        'sync.telegram.chat_id' => ['group' => 'Telegram alerts', 'label' => 'Chat id', 'type' => 'text', 'pattern' => '/^-?\d{1,20}$/', 'hint' => 'Numeric chat or group id.'],
        'sync.telegram.webhook_secret' => ['group' => 'Telegram alerts', 'label' => 'Webhook secret', 'type' => 'secret', 'pattern' => '/^[A-Za-z0-9_-]{8,128}$/', 'hint' => 'Checked on the bot webhook.'],
        'sync.telegram.admin_url' => ['group' => 'Telegram alerts', 'label' => 'Admin link in alerts', 'type' => 'url', 'hint' => 'URL of the conflict admin, e.g. https://…/sync-admin/conflicts'],
        'sync.telegram.queue' => ['group' => 'Telegram alerts', 'label' => 'Queue name', 'type' => 'text', 'pattern' => '/^[a-z0-9_-]{1,32}$/', 'hint' => 'Messages wait in this queue (default telegram); empty file value = default.'],
    ];

    /**
     * Normalises and checks one submitted value.
     *
     * @param array<string, mixed> $f a FIELDS entry
     *
     * @return array{0: ?string, 1: ?string} [value to store (null = remove the variable), error]
     */
    public static function check(string $key, string $raw, array $f): array
    {
        $v = trim($raw);

        if ($v === '') {
            return ! empty($f['required']) ? [null, "{$f['label']} cannot be empty."] : [null, null];
        }

        switch ($f['type']) {
            case 'bool':
                $b = strtolower($v);

                return in_array($b, ['true', 'false'], true) ? [$b, null] : [null, "{$f['label']} must be true or false."];

            case 'int':
                return ctype_digit($v) && (int) $v >= $f['min'] && (int) $v <= $f['max'] ? [(string) (int) $v, null]
                    : [null, "{$f['label']} must be a number between {$f['min']} and {$f['max']}."];

            case 'baseurl':
            case 'url':
                $p = parse_url($v);
                $ok = $p !== false && isset($p['scheme'], $p['host']) && in_array($p['scheme'], ['http', 'https'], true)
                    && ! isset($p['user']) && ! isset($p['pass']) && preg_match('#^[A-Za-z0-9._~:/?&=%+,@!*-]+$#', $v) === 1
                    && ($p['scheme'] === 'https' || in_array($p['host'], ['localhost', '127.0.0.1'], true));

                if (! $ok) {
                    return [null, "{$f['label']} must be an https:// address."];
                }

                if ($f['type'] === 'baseurl') {
                    return [rtrim($v, '/') . '/', null];
                }

                return [$key === 'sync.peerUrl' ? rtrim($v, '/') : $v, null];

            case 'path':
                if (preg_match('~^(/|[A-Za-z]:[\\\\/])~', $v) !== 1 || str_contains($v, '..') || preg_match('~^[A-Za-z0-9_./:\\\\ -]+$~', $v) !== 1) {
                    return [null, "{$f['label']} must be an absolute path without .. or special characters."];
                }

                $v = rtrim($v, '/\\');

                if (! is_dir($v) && ! @mkdir($v, 0775, true) && ! is_dir($v)) {
                    return [null, "{$f['label']}: the directory does not exist and cannot be created."];
                }

                return is_writable($v) ? [$v, null] : [null, "{$f['label']}: the directory is not writable by the web server."];

            case 'choice':
                return in_array($v, $f['options'], true) ? [$v, null] : [null, "{$f['label']} must be one of: " . implode(', ', $f['options']) . '.'];

            case 'host':
                $ok = $v === 'localhost' || filter_var($v, FILTER_VALIDATE_IP) !== false
                    || preg_match('/^[A-Za-z0-9]([A-Za-z0-9.-]{0,251}[A-Za-z0-9])?$/', $v) === 1;

                return $ok ? [$v, null] : [null, "{$f['label']} must be localhost, an IP address or a host name."];

            case 'secret':
            case 'text':
                if (! empty($f['free'])) {
                    return strlen($v) <= 128 && preg_match('/[\x00-\x1f\x7f]/', $v) !== 1 ? [$v, null] : [null, "{$f['label']} is too long or has control characters."];
                }

                if (isset($f['pattern'])) {
                    return preg_match($f['pattern'], $v) === 1 ? [$v, null] : [null, "{$f['label']} does not look right."];
                }

                if (isset($f['min']) && (strlen($v) < $f['min'] || strlen($v) > $f['max'] || preg_match('/^[A-Za-z0-9]+$/', $v) !== 1)) {
                    return [null, "{$f['label']} must have {$f['min']}-{$f['max']} letters and digits."];
                }

                return [$v, null];
        }

        return [null, 'Unsupported field.'];
    }
}
