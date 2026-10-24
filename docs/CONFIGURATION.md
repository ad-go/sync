# Configuration

`php spark sync:install` publishes `app/Config/Sync.php`, which extends `AdGo\Sync\Config\Sync`. Scalar
options can be set from `.env` (`sync.<option>`); nested ones use a dotted key (`sync.files.dir`,
`sync.mail.host`, `sync.telegram.token`).

## Node and peers

| Option | `.env` key | Meaning |
|---|---|---|
| `node` | `sync.node` | Name of this node. Unique per node. |
| `peerNode`, `peerUrl` | `sync.peerNode`, `sync.peerUrl` | The primary peer and its base URL (no route prefix, no trailing slash). |
| `peers` | `sync.peersJson` | Several peers: `{"app":{"passive":true},"site2":{"url":"https://..."}}`. A passive peer is never contacted; it pulls and pushes by itself. |
| `passive` | `sync.passive` | This node is behind NAT: it starts every connection (pull and push) towards its hub. |
| `relay` | `sync.relay` | Forward what this node applies to its other peers (hub of a passive node). |
| `selfUrl` | `sync.selfUrl` | URL under which peers reach this node. Used by `sync:check`. Defaults to `app.baseURL` on non-passive nodes. |
| `secret` | `sync.secret` | Shared HMAC secret, identical on all nodes. Treat it like a database password. |
| `tiebreak` | | Node order used in ties and for automatic lock takeover. Replaced by the alphabetical order of the two node names when it does not name both. |
| `routePrefix` | | URL prefix of the peer API (default `sync-api`). |
| `dbGroup` | | Database group that holds both application and package tables. |
| `replayWindow` | | Accepted clock skew of signed requests, in seconds (default 300). |
| `allowInsecure` | `sync.allowInsecure` | Allow peers on plain `http://` (default `false`). Without it the client refuses every URL except `https://` and localhost: rows, files and signatures would travel in clear. |
| `clockWarn` | `sync.clockWarn` | Clock difference to a peer (seconds, default 2) from which `sync:check` warns. |
| `clockMax` | `sync.clockMax` | Clock difference (seconds, default 5) above which "newest wins" is not trusted for that peer: conflicts in tolerant tables are quarantined and file conflicts are decided by `tiebreak`. 0 disables the guard. |
| `clockTolerance` | `sync.clockTolerance` | Timestamps closer than this (seconds, default 2) count as simultaneous and are decided by `tiebreak`. 0 = strictly by time. |
| `maxBody` | `sync.maxBody` | Largest request body the peer API accepts, in bytes (default 8 MiB; 0 = unlimited). A 4 MiB file is about 5.6 MB once base64-encoded. |
| `maxPushChanges` | `sync.maxPushChanges` | Journal entries accepted in one `push` (default 1000, also the ceiling of `pullBatch`). |

## Tables

```php
public array $tables = [
    'products' => [
        'policy'           => 'tolerant',
        'sensitive_fields' => ['price', 'stock'],
        'ignore_fields'    => ['updated_at'],
        'where'            => ['class' => ['Config\Site', 'Config\Shop']],
        'table'            => 'products',
        'pk'               => 'id',
        'fk'               => ['category_id' => 'categories'],
        'writers'          => ['app'],
        'scan'             => true,
    ],
];
```

| Key | Default | Meaning |
|---|---|---|
| `policy` | `sensitive` | `tolerant`: concurrent edits of the same field are resolved last-writer-wins. `sensitive`: they are quarantined and alerted. |
| `sensitive_fields` | `[]` | Fields whose overlap is always escalated, even in a tolerant table. |
| `ignore_fields` | `[]` | Never compared, merged or sent. They must have database defaults. |
| `where` | `[]` | Only rows matching these column/value pairs take part. A list means `IN (...)`. |
| `table` | key | Physical table, when it differs from the name used on the wire. |
| `pk` | `id` | Primary key column. |
| `fk` | `[]` | Foreign keys to other synchronised tables, translated through uuids. List parents before children. |
| `writers` | any | Only changes originating on these nodes are accepted. Required, one writer per table, for tables travelling through a relay between three or more nodes. |
| `scan` | `true` | `false`: `sync:scan` skips the table. Only writes through `Syncable` models and applied remote changes are journaled. For tables too large to read on every run. |

Foreign keys to tables that are only bootstrapped (Shield `users`) need no mapping: those ids are identical
on every node. `Options::SHIELD_USERS` provides ready-made entries for users, identities and group
memberships.

`excludedTables` lists tables that are never synchronised (queue jobs, sessions, login logs, Tasks logs,
migrations); the package refuses to add them to `$tables`.

## Files

```ini
sync.files.dir = /var/www/shared
```

`files` is `['dir' => ..., 'ignore' => [...]]`. `dir` defaults to `WRITEPATH/sync-files`; `ignore` holds
`fnmatch()` patterns relative to it (default `*.tmp`, `.gitkeep`).

## Alerts

```ini
sync.telegram.token          = 123:ABC
sync.telegram.chat_id        = -100123
sync.telegram.webhook_secret = <random>
sync.telegram.admin_url      = https://a.example/sync-admin/conflicts
sync.telegram.allowed_users  = 111111111,222222222   # empty: every member of the chat may decide
sync.telegram.queue          = telegram        # empty: send directly

sync.mail.to     = ops@example.com            # every Telegram message is also mailed
sync.mail.host   = smtp.example.com
sync.mail.port   = 587
sync.mail.crypto = tls                        # tls (STARTTLS), ssl or empty
sync.mail.user   = alerts@example.com
sync.mail.pass   = ...
sync.mail.from   = alerts@example.com         # defaults to user
```

Mail settings that `sync.mail` does not define are taken from `Config\Email`. Telegram and mail are
independent: either can be used alone.

## Timing and retention

| Option | Default | Meaning |
|---|---|---|
| `lockTtl` | 120 | Seconds without a heartbeat before a manual lock counts as abandoned. |
| `leaseTtl` | 300 | Seconds of editing inactivity before an automatic edit lease is freed. |
| `keepJournalDays` | 30 | `sync:prune` removes applied journal entries older than this. |
| `keepConflictDays` | 90 | `sync:prune` removes decided conflicts older than this. |
| `pullBatch` | 200 | Changes requested per round trip (at most 1000). |
| `scanOnShutdown` | `false` | Run the scanner at the end of every request, for models without the trait. |

## User interface

`conflictsPath` (default `sync-admin/conflicts`) and `adminPermission` (default `admin.access`, a Shield
permission) control the conflict UI. `menuView`, `headView`, `conflictsIndexView` and `conflictsShowView`
let the application wrap the pages in its own layout.
