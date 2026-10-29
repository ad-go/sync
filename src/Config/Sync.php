<?php

declare(strict_types=1);

namespace AdGo\Sync\Config;

use CodeIgniter\Config\BaseConfig;
use AdGo\Sync\Libraries\Options;

/**
 * Configuration of the sync package. Publish with `php spark sync:install`
 * (creates app/Config/Sync.php extending this class) and edit there.
 *
 * Everything here is plain PHP: the package never stores settings as JSON.
 */
class Sync extends BaseConfig
{
    /** Name of THIS node. Must differ on the two nodes (e.g. site1 / site2). */
    public string $node = 'site1';

    /** Database group holding BOTH the app tables and the package tables (must be the same connection). Null = default group. */
    public ?string $dbGroup = null;

    /** Name of the other node. */
    public string $peerNode = 'site2';

    /** Base URL of the peer, without the trailing slash and without the route prefix. */
    public string $peerUrl = 'https://peer.example.com';

    /**
     * More than one peer, or a peer behind NAT. Each entry: name => ['url' => 'https://…', 'passive' => false].
     * A PASSIVE peer cannot be reached: it pulls our journal and pushes its own (sync-api/push); we never connect
     * to it. Empty = the single peer above. Example of a hub with the classic peer and a NAT node:
     *   ['site1' => ['url' => 'https://beta1…'], 'app' => ['passive' => true]]
     *
     * @var array<string, array{url?: string, passive?: bool}>
     */
    public array $peers = [];

    /** This node sits behind NAT: it initiates every connection (pull + push) to its one reachable peer (the hub). */
    public bool $passive = false;

    /**
     * Forward what this node applies from a peer to its other peers (journal it again, tagged with the origin).
     * Needed on the hub of a passive node so that the third node sees the passive node's rows and vice versa.
     * Every applied change costs one journal entry; the origin gets it back once and finds it identical.
     */
    public bool $relay = false;

    /**
     * The URL under which the peers reach THIS node (what their `peers` entry says about it). Used by `sync:check` to
     * tell internet from LAN; empty on a passive node. Defaults to app.baseURL.
     */
    public string $selfUrl = '';

    /** Route prefix under which the sync API is exposed. */
    public string $routePrefix = 'sync-api';

    /** URL path of the conflict admin UI. */
    public string $conflictsPath = 'admin/conflicts';

    /** URL path of the settings admin page. */
    public string $settingsPath = 'admin/settings';

    /** URL path of the users admin pages. */
    public string $usersPath = 'admin/users';

    /**
     * URL (relative to base_url()) of the DataTables + Editor combined asset directory.
     * Must contain: datatables.min.js, datatables.min.css, key.js.
     * In the composed full-app build this is `assets/vendor/datatables`; override per host.
     */
    public string $dteAssetsUrl = 'assets/datatables';

    /**
     * Shared HMAC secret (same on both nodes). Set it in .env:
     *   sync.secret = <long random string>
     */
    public string $secret = '';

    /**
     * Deterministic tie-breaker order: in a tie the node listed first wins
     * (also the only node allowed to take the auth lock automatically).
     *
     * @var list<string>
     */
    public array $tiebreak = ['site1', 'site2'];

    /**
     * Application tables that are synchronised active-active.
     *
     * 'table' => [
     *     'pk'               => 'id',
     *     'policy'           => 'tolerant' | 'sensitive',
     *     'sensitive_fields' => ['price'],        // overlap here is always escalated
     *     'ignore_fields'    => ['updated_at'],   // never compared / merged
     *     'where'            => ['class' => ['Config\Site', 'Config\Shop']], // only these rows take part (a table shared with per-node data; a list = IN)
     *     'table'            => 'products',   // physical table, when it differs from the wire/journal name (the key)
     *     'fk'               => ['user_id' => 'users'], // FK to ANOTHER synced table (mapped via uuid)
     * ]
     *
     * Optional per table: 'scan' => false — sync:scan skips it (only writes through Syncable models and applied
     * remote changes are journaled; raw SQL writes are not detected). For tables too big to be read on every run.
     *
     * Optional per table: 'writers' => ['app'] — only changes originating on the listed nodes are accepted. REQUIRED
     * (one writer per table) for tables that travel through a relaying hub between three or more nodes.
     *
     * Foreign keys to tables that are only bootstrapped (e.g. Shield users)
     * need no mapping: those ids are identical on both nodes.
     *
     * @var array<string, array<string, mixed>>
     */
    public array $tables = [];

    /**
     * Tables of the official packages copied ONCE master -> peer by
     * `spark sync:bootstrap`, in dependency order (parents first).
     *
     * @var list<string>
     */
    public array $bootstrapTables = [
        'users',
        'auth_identities',
        'auth_groups_users',
        'auth_permissions_users',
        'settings',
    ];

    /**
     * Tables that are NEVER synchronised (strictly per node). The package
     * refuses to add them to $tables.
     *
     * @var list<string>
     */
    public array $excludedTables = Options::DEFAULT_EXCLUDED;

    /** Seconds without a heartbeat after which the auth/cron lock is considered abandoned. */
    public int $lockTtl = 120;

    /** Seconds of editing inactivity after which an automatic edit lease (taken by the `authWriteLock` filter) is freed. */
    public int $leaseTtl = 300;

    /** `sync:prune` deletes change-journal entries older than this many days, once the peer has applied them. */
    public int $keepJournalDays = 30;

    /** `sync:prune` deletes decided (not pending) conflicts older than this many days. */
    public int $keepConflictDays = 90;

    /** Changes requested from the peer per HTTP round trip. */
    public int $pullBatch = 200;

    /** Max clock skew / replay window for signed requests (seconds). */
    public int $replayWindow = 300;

    /** Clock difference to a peer (seconds) from which `sync:check` warns. */
    public float $clockWarn = 2.0;

    /**
     * Clock difference (seconds) above which "newest wins" is not trusted for that peer: tolerant tables go to
     * quarantine and file conflicts are decided by `tiebreak` instead of `mtime`. 0 disables the guard.
     */
    public float $clockMax = 5.0;

    /** Timestamps closer than this (seconds) count as simultaneous and are decided by `tiebreak`, the same on both nodes. */
    public float $clockTolerance = 2.0;

    /**
     * NTP servers `sync:ntp` measures this node's clock against (comma separated in .env:
     * `sync.ntpServers = pool.ntp.org,time.cloudflare.com`). Empty = no NTP. Needs outbound UDP port 123.
     *
     * @var list<string>
     */
    public array $ntpServers = [];

    /**
     * Stamp the changes this node records with the real time (local clock + the NTP offset from `sync:ntp`)
     * instead of the raw local clock, and tell the peers so, so that the clock guard measures what is left.
     * Off by default; needs `ntpServers` and the `sync:ntp` task. The system clock is never changed.
     */
    public bool $clockCorrect = false;

    /** Largest request body (bytes) the peer API accepts; 0 = unlimited. A pushed file is sent base64-encoded, 4 MiB becomes ~5.6 MB. */
    public int $maxBody = 8 * 1024 * 1024;

    /** Journal entries accepted in one `push` request (and the ceiling of `pullBatch`). */
    public int $maxPushChanges = 1000;

    /** Allow peers on plain `http://`. Everything but localhost is refused otherwise; rows, files and signatures would travel in clear. */
    public bool $allowInsecure = false;

    /** Run the change scanner automatically at the end of every request (for models without the Syncable trait). */
    public bool $scanOnShutdown = false;

    /**
     * File sync: 'dir' is the directory kept identical on both nodes (default WRITEPATH/share; set it per
     * node in .env: `sync.files.dir = /path/to/shared`, absolute, writable by the web user).
     * 'ignore' are fnmatch() patterns relative to it.
     *
     * @var array{dir: string, ignore: list<string>}
     */
    public array $files = [
        'dir'    => '',
        'ignore' => ['*.tmp', '.gitkeep'],
    ];

    /**
     * Telegram notifications / triage. Leave 'token' empty to disable.
     * NOTE: the Telegram Bot API itself speaks JSON (reply_markup, webhook
     * body); that is the only place where the package touches JSON and it is
     * isolated in Libraries\TelegramNotifier.
     *
     * 'allowed_users' = Telegram user ids (comma separated in .env) that may press the decision buttons;
     * empty = every member of the chat 'chat_id'.
     *
     * 'queue' = name of the codeigniter4/queue queue the messages go through
     * (drained by the `queue:work` task of SyncTasks); '' = send directly.
     *
     * @var array<string, string>
     */
    public array $telegram = [
        'token'          => '',
        'chat_id'        => '',
        'webhook_secret' => '',
        'admin_url'      => '',
        'allowed_users'  => '',
        'queue'          => 'telegram',
    ];

    /**
     * E-mail copy of every Telegram message. Leave 'to' empty to disable. Set in .env:
     *   sync.mail.to = ops@example.com   sync.mail.host = smtp.example.com   sync.mail.user = …   sync.mail.pass = …
     * 'crypto' = tls (STARTTLS, port 587) | ssl (port 465) | '' ; 'from' defaults to 'user'. Independent of Config\Email.
     *
     * @var array<string, string|int>
     */
    public array $mail = [
        'to'     => '',
        'host'   => '',
        'port'   => 587,
        'user'   => '',
        'pass'   => '',
        'crypto' => 'tls',
        'from'   => '',
    ];

    /**
     * Optional view (e.g. the application's menu partial, called with ['active' => 'conflicts']) shown at the
     * top of the conflict pages. Empty = nothing.
     */
    public string $menuView = '';

    /** Optional view printed inside <head> of the conflict pages, before their own styles (e.g. the application's CSS framework links). Empty = nothing. */
    public string $headView = '';

    /** The conflict list and detail views; the settings and users views. An application can substitute its own (same variables). */
    public string $conflictsIndexView = 'AdGo\Sync\Views\conflicts\index';

    public string $conflictsShowView = 'AdGo\Sync\Views\conflicts\show';

    public string $settingsIndexView = 'AdGo\Sync\Views\settings\index';

    public string $usersIndexView = 'AdGo\Sync\Views\users\index';

    public string $usersEditView = 'AdGo\Sync\Views\users\edit';

    /** Roles (Shield groups) allowed to use the conflicts/settings/users admin UI. */
    public string $adminPermission = 'admin.access';

    /** Roles (Shield groups) allowed to use the settings and users admin pages (a tighter subset of adminPermission, e.g. superadmin only). */
    public string $superPermission = 'admin.access';

    /**
     * Font choices offered on the Settings page: label => CSS `font-family` value. Stored (by key) in
     * `Config\Sync::$fontFamily` / setting('Sync.fontFamily').
     *
     * @var array<string, string>
     */
    public const FONTS = [
        'system' => 'system-ui,-apple-system,Segoe UI,Roboto,sans-serif',
        'serif'  => 'Georgia,Cambria,Times New Roman,serif',
        'mono'   => 'ui-monospace,Consolas,Menlo,monospace',
    ];

    /** Site name shown in the admin chrome, browser titles and 2FA QR codes/e-mails. Editable from the Settings page. */
    public string $siteName = 'Sync';

    /** Site logo filename stored in writable/share/ (e.g. sync-logo-abc123.png). Served via GET /sync-logo. Editable from the Settings page. */
    public string $logo = '';

    /** Accent colour (#rrggbb) of the admin chrome, or '' for the built-in default. Editable from the Settings page. */
    public string $accentColor = '';

    /** Key into FONTS, or '' for the built-in default. Editable from the Settings page. */
    public string $fontFamily = '';

    /** Base font size in pixels (12-20), or 0 for the built-in default. Editable from the Settings page. */
    public int $fontSize = 0;

    /** Largest uploaded logo accepted, in MiB. */
    public int $maxImageMb = 2;

    public function __construct()
    {
        parent::__construct();

        if ($this->selfUrl === '' && ! $this->passive) {
            $base = config('App')->baseURL ?? '';
            $this->selfUrl = is_string($base) ? rtrim($base, '/') : '';
        }

        if ($this->files['dir'] === '' && defined('WRITEPATH')) {
            $this->files['dir'] = WRITEPATH . 'share';
        }

        $ntp = env('sync.ntpServers') ?? env('sync_ntpServers');

        if (is_string($ntp) && $ntp !== '') {
            $this->ntpServers = array_values(array_filter(array_map('trim', explode(',', $ntp))));
        }

        // .env cannot express a nested array: `sync.peersJson = {"app":{"passive":true},"site2":{"url":"https://…"}}`
        $json = env('sync.peersJson') ?? env('sync_peersJson');

        if (is_string($json) && $json !== '') {
            $this->peers = (array) json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        }
    }
}
