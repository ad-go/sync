# ad-go/sync

Active-active synchronisation of CodeIgniter 4 applications over HTTPS: two or more nodes keep their
databases and a shared directory identical, including nodes behind NAT. No JSON on the wire, no schema
changes to existing tables, no SSH or root needed.

* **Database sync.** A change journal and a per-row registry (uuid, revision counters, last agreed state)
  drive a three-way reconciliation: fast-forward, field-level merge, last-writer-wins for tolerant tables,
  quarantine for sensitive ones.
* **File sync.** A directory (with sub-directories) is mirrored with a manifest, SHA-256 verification and
  atomic writes; concurrent edits keep both versions.
* **Topologies.** Two dial-in nodes, a hub with passive nodes behind NAT (outbound-only), and relaying hubs.
* **Operations.** Conflict admin UI, Telegram and e-mail alerts, a reachability check (`sync:check`), and a
  distributed edit lock for Shield users. For database admin, see the separate
  [`ad-go/syncui`](https://github.com/ad-go/syncui) package (no dependency on this one).
* **Built on the official packages.** [Shield](https://github.com/codeigniter4/shield),
  [Tasks](https://github.com/codeigniter4/tasks), [Queue](https://github.com/codeigniter4/queue),
  CodeIgniter's Email, Migrations, Language and Services.

Messages (alerts, conflict UI) are available in English and German (`src/Language`); an application can add
or override a language.

Requires PHP 8.2+ and CodeIgniter 4.7. Tested with Shield 1.4, Tasks 1.0, Queue 1.0.
Portable across MySQL/MariaDB, PostgreSQL and SQLite (query builder and Forge only).

## Quick start

On every node:

```bash
composer require ad-go/sync
php spark sync:install --node site1 --peer-node site2 --peer-url https://www.example-b.com
```

`sync:install` does the whole set-up and can be re-run safely; it never overwrites what is already set:

| Step | What it does |
|---|---|
| `.env` | Creates it from `env` if missing and writes `sync.secret` (64 random hex characters) plus the node, peer and URL you pass. On the other nodes pass the first node's secret: `--secret <value>`. |
| `app/Config/Sync.php` | Published with `$tables` filled from the database: every table that is not excluded (queue, sessions, login logs, Tasks logs, migrations, package tables) and not copied once (settings, permissions), with its primary key, a `tolerant` policy when the table has `updated_at` (otherwise `sensitive`), foreign keys to other synchronised tables, parents first. Shield users, identities and group memberships come from `Options::SHIELD_USERS`. Tables without a single-column primary key are listed as skipped. |
| `app/Config/Tasks.php` | Created, or patched (a `.bak` is kept), so that `init()` calls `SyncTasks::register($schedule)`. |
| Package tables | Migrated, and existing rows are seeded with deterministic uuids. Both databases must hold the same data at that moment. |
| Cron | Looks for a `tasks:run` entry (`crontab -l`, or a Windows scheduled task) and adds `* * * * * php /path/spark tasks:run` when missing. Where the host gives no shell access, it prints the line to add in the hosting panel. |

Options: `--no-migrate`, `--no-seed`, `--no-cron`. Review the generated policies before the first sync.
When `Config\Sync` already exists, the command only proposes the tables that are missing from it.

If you prefer to do it by hand, the pieces are:

```ini
sync.secret   = <64+ random characters, identical on all nodes>
sync.node     = site1
sync.peerNode = site2
sync.peerUrl  = https://www.example-b.com
```

```php
public array $tables = [
    'categories' => ['policy' => 'tolerant'],
    'products'   => [
        'policy'           => 'tolerant',
        'sensitive_fields' => ['price'],
        'fk'               => ['category_id' => 'categories'],
    ],
];
```

Journal writes made through models by adding the trait:

```php
class ProductModel extends \CodeIgniter\Model
{
    use \AdGo\Sync\Traits\Syncable;
}
```

The scheduler entry is added by `sync:install`; by hand it is, in `app/Config/Tasks.php`:

```php
public function init(Scheduler $schedule)
{
    \AdGo\Sync\Tasks\SyncTasks::register($schedule);
}
```

with the usual single cron entry `* * * * * php /path/to/spark tasks:run`.

Exempt the API from CSRF (`'csrf' => ['except' => ['sync-api/*']]`) and serve it over HTTPS only.

## Documentation

| Document | Contents |
|---|---|
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Data model, reconciliation algorithm, transport, file sync |
| [docs/CONFIGURATION.md](docs/CONFIGURATION.md) | Every `.env` key and table option |
| [docs/INTEGRATION.md](docs/INTEGRATION.md) | Shield, Tasks, Queue, Email, Settings, Services, Language |
| [docs/TELEGRAM.md](docs/TELEGRAM.md) | Creating the Telegram bot, chat id, webhook, e-mail copy, troubleshooting |
| [docs/OPERATIONS.md](docs/OPERATIONS.md) | Commands, NAT and relays, reachability check, locks, conflicts, housekeeping |
| [docs/SYNC-DB.md](docs/SYNC-DB.md) | Illustrated walkthrough of the database sync |
| [docs/SYNC-FILES.md](docs/SYNC-FILES.md) | Illustrated walkthrough of the file sync |
| [docs/audit.html](docs/audit.html) | Formal specification of the wire protocol (envelope, messages, test vectors), threat model and known weaknesses (F-1 ...) |

The commands are `sync:install`, `sync:bootstrap`, `sync:pull`, `sync:push`, `sync:scan`, `sync:files`,
`sync:check`, `sync:ntp`, `sync:rehash`, `sync:prune`, `sync:notify` and `sync:auth-heartbeat`; see
[OPERATIONS.md](docs/OPERATIONS.md#commands).

## Tests

```bash
composer install
composer test
```

The suite runs against in-memory SQLite. The real HTTPS round trip, the Telegram and SMTP calls and the
admin views are exercised by hand.

## Limits

* Two nodes cannot be strictly consistent under a network partition without a third voter; the design
  minimises, logs and quarantines conflicts instead of hiding them.
* Last-writer-wins depends on the nodes' clocks: the package measures each peer's clock, treats near-simultaneous
  edits as ties and escalates conflicts when a clock is more than `clockMax` off, and can stamp changes with NTP-corrected
  time (`sync.clockCorrect`), but keep NTP running and mark data where that matters as `sensitive`.
* Fields in `ignore_fields` are not transferred and need database defaults.
* Out-of-band writes (query builder, raw SQL, `insertBatch`) are found by the scanner, not instantly.

## License

MIT
