# Integration with CodeIgniter and its official packages

The package follows the framework's own extension points, so installing it needs no changes to core files.

| Concern | Mechanism |
|---|---|
| Routes | Route discovery (`Config/Routes.php`): the signed peer API, the Telegram webhook, the conflict UI |
| Filters | `Config\Registrar::Filters()` registers the aliases `syncHmac` and `authWriteLock` |
| Queue job handler | `Config\Registrar::Queue()` registers `TelegramJob` |
| Services | `Config\Services::sync()`: `service('sync')` returns the shared `Kernel` |
| Migrations | Namespace migrations: `php spark migrate -n 'AdGo\Sync'` |
| Commands | `BaseCommand` subclasses, grouped under `Sync` |
| Language | `Language/<locale>/Sync.php`, read with `lang('Sync.<key>')` |
| Config | `config('Sync')`, publishable and overridable from `.env` |

## Shield

* **Users and groups** replicate like any table: add `+ Options::SHIELD_USERS` to `$tables`. Primary keys
  stay local; `auth_identities.user_id` and `auth_groups_users.user_id` travel through uuids. Login
  bookkeeping (`last_active`, `last_used_at`, `updated_at`) is ignored so a login is not a change.
* **Existing users** must be adopted before the first scan with `php spark sync:install`, which seeds
  deterministic uuids. Scanning first would give each user a random uuid on each node.
* **Other Shield tables** (permissions, settings) can be copied once with `php spark sync:bootstrap`.
* **Never synchronised:** `auth_logins`, `auth_token_logins`, `auth_remember_tokens`, sessions.
* **Edit lock.** The `authWriteLock` filter takes a lease while a user or group is edited, so only one
  node edits at a time (see [OPERATIONS.md](OPERATIONS.md#editing-users-the-auth-lock)). The editor name comes
  from `auth()->user()`.
* **Conflict UI** sits behind Shield's `session` and `permission:<adminPermission>` filters; decisions are
  attributed to the logged-in user.

## Tasks

```php
public function init(Scheduler $schedule)
{
    \AdGo\Sync\Tasks\SyncTasks::register($schedule);

    \AdGo\Sync\Tasks\SyncTasks::onCronHolder($schedule, 'reports:daily')->daily('02:00');
}
```

`register()` schedules `sync:auth-heartbeat` and `sync:pull` every minute, `sync:push` on passive nodes,
`sync:files` every five minutes, `sync:prune` daily, and the alert queue worker when alerts are queued.
`onCronHolder()` runs your own command only on the node that holds the `cron` lock, so a job never runs
twice. The Tasks scheduler runs from the normal single cron entry (`php spark tasks:run`).

## Queue

Telegram and e-mail alerts are pushed to the queue named by `sync.telegram.queue` (default `telegram`) so a
slow or failing service never delays a sync run, and failed deliveries are retried (6 tries, 60 s apart).
`SyncTasks::register()` drains the queue every minute with `queue:work --stop-when-empty`, so no permanent
worker is needed. Credentials are never stored in the queue; the job reads them from the configuration when
it runs. If the queue is unavailable, or `queue` is empty, messages are sent directly.

## Email

Every alert is also mailed when `sync.mail.to` and `sync.mail.host` are set. Delivery uses CodeIgniter's
`Email` service. Settings that `sync.mail` does not define fall back to `Config\Email`, so one SMTP account
can serve the application and the package.

## Settings

The shared `settings` table can take part in synchronisation through a `where` filter, so that per-node
settings stay local:

```php
'settings' => ['where' => ['class' => ['Config\Site', 'Config\Shop']]],
```

Settings written by the package straight to the table are picked up by the scanner. After saving one,
call `service('sync')->scanner()->scan()` to publish it at once.

## Models

* `use \AdGo\Sync\Traits\Syncable;` journals `insert`, `update` and `delete` of a model in the same
  transaction as the registry row, so no orphan uuid is possible. It hooks the model's event dispatcher and
  coexists with the model's own callbacks. Soft deletes replicate `deleted_at` as data; a purge becomes a
  tombstone.
* Extending `\AdGo\Sync\Models\SyncableModel` has the same effect.
* Tables written by other means are covered by `php spark sync:scan` (also run by every `sync:pull`).

## Language

Messages come from `Language/en/Sync.php` and `Language/de/Sync.php`. Copy a file to
`app/Language/<locale>/Sync.php` to override or add a language. Alerts use the locale current when the
message is built: the request's locale on the web, `Config\App::$defaultLocale` in CLI, workers and tasks.
Give all nodes the same default locale, since they post to one chat.
