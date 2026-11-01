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
        'app.baseURL' => ['group' => 'Application', 'label' => 'Env.label.app_baseURL', 'type' => 'baseurl', 'required' => true,
            'hint' => 'Env.hint.app_baseURL'],
        'app.forceGlobalSecureRequests' => ['group' => 'Application', 'label' => 'Env.label.app_forceGlobalSecureRequests', 'type' => 'bool', 'required' => true,
            'hint' => 'Env.hint.app_forceGlobalSecureRequests'],
        'app.tickToken' => ['group' => 'Application', 'label' => 'Env.label.app_tickToken', 'type' => 'secret', 'min' => 16, 'max' => 64, 'generate' => true,
            'hint' => 'Env.hint.app_tickToken'],
        // --- sync
        'sync.peerUrl' => ['group' => 'Synchronisation', 'label' => 'Env.label.sync_peerUrl', 'type' => 'url', 'required' => true,
            'hint' => 'Env.hint.sync_peerUrl'],
        'sync.files.dir' => ['group' => 'Synchronisation', 'label' => 'Env.label.sync_files_dir', 'type' => 'path',
            'hint' => 'Env.hint.sync_files_dir'],
        'sync.leaseTtl' => ['group' => 'Synchronisation', 'label' => 'Env.label.sync_leaseTtl', 'type' => 'int', 'min' => 30, 'max' => 3600, 'default' => 300, 'hint' => 'Env.hint.sync_leaseTtl'],
        'sync.lockTtl' => ['group' => 'Synchronisation', 'label' => 'Env.label.sync_lockTtl', 'type' => 'int', 'min' => 30, 'max' => 3600, 'default' => 120, 'hint' => 'Env.hint.sync_lockTtl'],
        'sync.pullBatch' => ['group' => 'Synchronisation', 'label' => 'Env.label.sync_pullBatch', 'type' => 'int', 'min' => 10, 'max' => 1000, 'default' => 200, 'hint' => 'Env.hint.sync_pullBatch'],
        'sync.replayWindow' => ['group' => 'Synchronisation', 'label' => 'Env.label.sync_replayWindow', 'type' => 'int', 'min' => 30, 'max' => 3600, 'default' => 300, 'hint' => 'Env.hint.sync_replayWindow'],
        'sync.keepJournalDays' => ['group' => 'Synchronisation', 'label' => 'Env.label.sync_keepJournalDays', 'type' => 'int', 'min' => 1, 'max' => 3650, 'default' => 30, 'hint' => 'Env.hint.sync_keepJournalDays'],
        'sync.keepConflictDays' => ['group' => 'Synchronisation', 'label' => 'Env.label.sync_keepConflictDays', 'type' => 'int', 'min' => 1, 'max' => 3650, 'default' => 90, 'hint' => ''],
        // --- database (a change is tried against the database BEFORE it is written, see DbCheck)
        'database.default.DBDriver' => ['group' => 'Database', 'label' => 'Env.label.database_default_DBDriver', 'type' => 'choice', 'options' => ['MySQLi', 'Postgre', 'SQLite3'], 'required' => true,
            'hint' => 'Env.hint.database_default_DBDriver'],
        'database.default.hostname' => ['group' => 'Database', 'label' => 'Env.label.database_default_hostname', 'type' => 'host',
            'hint' => 'Env.hint.database_default_hostname'],
        'database.default.port' => ['group' => 'Database', 'label' => 'Env.label.database_default_port', 'type' => 'int', 'min' => 1, 'max' => 65535, 'hint' => 'Env.hint.database_default_port'],
        'database.default.database' => ['group' => 'Database', 'label' => 'Env.label.database_default_database', 'type' => 'text', 'pattern' => '~^[A-Za-z0-9_$./:\\\\-]{1,200}$~', 'required' => true,
            'hint' => 'Env.hint.database_default_database'],
        'database.default.username' => ['group' => 'Database', 'label' => 'Env.label.database_default_username', 'type' => 'text', 'pattern' => '~^[A-Za-z0-9_.@$-]{1,64}$~', 'hint' => ''],
        'database.default.password' => ['group' => 'Database', 'label' => 'Env.label.database_default_password', 'type' => 'secret', 'free' => true,
            'hint' => 'Env.hint.database_default_password'],
        // --- telegram
        'sync.telegram.token' => ['group' => 'Telegram alerts', 'label' => 'Env.label.sync_telegram_token', 'type' => 'secret', 'pattern' => '/^\d{6,12}:[A-Za-z0-9_-]{30,50}$/', 'hint' => 'Env.hint.sync_telegram_token'],
        'sync.telegram.chat_id' => ['group' => 'Telegram alerts', 'label' => 'Env.label.sync_telegram_token', 'type' => 'text', 'pattern' => '/^-?\d{1,20}$/', 'hint' => 'Env.hint.sync_telegram_token'],
        'sync.telegram.webhook_secret' => ['group' => 'Telegram alerts', 'label' => 'Env.label.sync_telegram_token', 'type' => 'secret', 'pattern' => '/^[A-Za-z0-9_-]{8,128}$/', 'hint' => 'Env.hint.sync_telegram_token'],
        'sync.telegram.admin_url' => ['group' => 'Telegram alerts', 'label' => 'Env.label.sync_telegram_token', 'type' => 'url', 'hint' => 'Env.hint.sync_telegram_token'],
        // --- e-mail alerts (sync.mail.*); independent of CI4's own Config\Email
        'sync.mail.to'     => ['group' => 'Telegram alerts', 'label' => 'Env.label.sync_telegram_token', 'type' => 'text', 'pattern' => '/^[^@\s]+@[^@\s]+$/', 'hint' => 'Env.hint.sync_telegram_token'],
        'sync.mail.from'   => ['group' => 'Telegram alerts', 'label' => 'Env.label.sync_telegram_token', 'type' => 'text', 'pattern' => '/^[^@\s]+@[^@\s]+$/', 'hint' => 'Env.hint.sync_telegram_token'],
        'sync.mail.host'   => ['group' => 'Telegram alerts', 'label' => 'Env.label.sync_telegram_token', 'type' => 'host', 'hint' => ''],
        'sync.mail.port'   => ['group' => 'Telegram alerts', 'label' => 'Env.label.sync_telegram_token', 'type' => 'int', 'min' => 1, 'max' => 65535, 'default' => 587, 'hint' => 'Env.hint.sync_telegram_token'],
        'sync.mail.crypto' => ['group' => 'Telegram alerts', 'label' => 'Env.label.sync_mail_crypto', 'type' => 'choice', 'options' => ['tls', 'ssl', ''], 'hint' => 'Env.hint.sync_mail_crypto'],
        'sync.mail.user'   => ['group' => 'Telegram alerts', 'label' => 'Env.label.sync_mail_crypto', 'type' => 'text', 'hint' => ''],
        'sync.mail.pass'   => ['group' => 'Telegram alerts', 'label' => 'Env.label.sync_mail_crypto', 'type' => 'secret', 'hint' => ''],
    ];

    /**
     * Normalises and checks one submitted value.
     *
     * @param array<string, mixed> $f a FIELDS entry
     *
     * @return array{0: ?string, 1: ?string} [value to store (null = remove the variable), error]
     */
    /**
     * The longest value the field accepts (the limit validation enforces), used to size its input on the settings
     * page: an int by its largest digits, a secret by its max, anything else by the common 128-character limit.
     */
    public static function maxChars(array $f): int
    {
        return match ($f['type']) {
            'int'    => strlen((string) ($f['max'] ?? 9999)),
            'secret' => (int) ($f['max'] ?? 128),
            default  => 128,
        };
    }

        /** A translated text of a field (its label or hint is a lang() key in FIELDS). */
    public static function text(string $key): string
    {
        return $key === '' ? '' : \AdGo\Sync\Libraries\Lang::text($key);
    }

    public static function check(string $key, string $raw, array $f): array
    {
        $v     = trim($raw);
        $label = self::text($f['label']);

        if ($v === '') {
            /* Allow empty string when it is a valid choice (e.g. crypto = '' means no encryption). */
            if ($f['type'] === 'choice' && in_array('', $f['options'] ?? [], true)) {
                return ['', null];
            }

            return ! empty($f['required']) ? [null, \AdGo\Sync\Libraries\Lang::text('Env.err.empty', [$label])] : [null, null];
        }

        switch ($f['type']) {
            case 'bool':
                $b = strtolower($v);

                return in_array($b, ['true', 'false'], true) ? [$b, null] : [null, \AdGo\Sync\Libraries\Lang::text('Env.err.bool', [$label])];

            case 'int':
                return ctype_digit($v) && (int) $v >= $f['min'] && (int) $v <= $f['max'] ? [(string) (int) $v, null]
                    : [null, \AdGo\Sync\Libraries\Lang::text('Env.err.range', [$label, (string) $f['min'], (string) $f['max']])];

            case 'baseurl':
            case 'url':
                $p = parse_url($v);
                $ok = $p !== false && isset($p['scheme'], $p['host']) && in_array($p['scheme'], ['http', 'https'], true)
                    && ! isset($p['user']) && ! isset($p['pass']) && preg_match('#^[A-Za-z0-9._~:/?&=%+,@!*-]+$#', $v) === 1
                    && ($p['scheme'] === 'https' || in_array($p['host'], ['localhost', '127.0.0.1'], true));

                if (! $ok) {
                    return [null, \AdGo\Sync\Libraries\Lang::text('Env.err.https', [$label])];
                }

                if ($f['type'] === 'baseurl') {
                    return [rtrim($v, '/') . '/', null];
                }

                return [$key === 'sync.peerUrl' ? rtrim($v, '/') : $v, null];

            case 'path':
                if (preg_match('~^(/|[A-Za-z]:[\\\\/])~', $v) !== 1 || str_contains($v, '..') || preg_match('~^[A-Za-z0-9_./:\\\\ -]+$~', $v) !== 1) {
                    return [null, \AdGo\Sync\Libraries\Lang::text('Env.err.path', [$label])];
                }

                // Windows separators are stored as '/': a backslash cannot be written to .env, and Windows accepts '/'.
                $v = rtrim(str_replace('\\', '/', $v), '/');

                if (! is_dir($v) && ! @mkdir($v, 0775, true) && ! is_dir($v)) {
                    return [null, \AdGo\Sync\Libraries\Lang::text('Env.err.mkdir', [$label])];
                }

                return is_writable($v) ? [$v, null] : [null, \AdGo\Sync\Libraries\Lang::text('Env.err.writable', [$label])];

            case 'choice':
                return in_array($v, $f['options'], true) ? [$v, null] : [null, \AdGo\Sync\Libraries\Lang::text('Env.err.choice', [$label, implode(', ', $f['options'])])];

            case 'host':
                $ok = $v === 'localhost' || filter_var($v, FILTER_VALIDATE_IP) !== false
                    || preg_match('/^[A-Za-z0-9]([A-Za-z0-9.-]{0,251}[A-Za-z0-9])?$/', $v) === 1;

                return $ok ? [$v, null] : [null, \AdGo\Sync\Libraries\Lang::text('Env.err.host', [$label])];

            case 'secret':
            case 'text':
                if (! empty($f['free'])) {
                    return strlen($v) <= 128 && preg_match('/[\x00-\x1f\x7f]/', $v) !== 1 ? [$v, null] : [null, \AdGo\Sync\Libraries\Lang::text('Env.err.long', [$label])];
                }

                if (isset($f['pattern'])) {
                    return preg_match($f['pattern'], $v) === 1 ? [$v, null] : [null, \AdGo\Sync\Libraries\Lang::text('Env.err.pattern', [$label])];
                }

                if (isset($f['min']) && (strlen($v) < $f['min'] || strlen($v) > $f['max'] || preg_match('/^[A-Za-z0-9]+$/', $v) !== 1)) {
                    return [null, \AdGo\Sync\Libraries\Lang::text('Env.err.letters', [$label, (string) $f['min'], (string) $f['max']])];
                }

                return [$v, null];
        }

        return [null, 'Unsupported field.'];
    }
}
