# Operations

## Commands

| Command | Purpose |
|---|---|
| `sync:install` | Set up `.env` (secret), `Config\Sync` (tables), `Config\Tasks` and the cron entry, migrate, seed the registry with deterministic uuids |
| `sync:bootstrap` | One-time copy of Shield/Settings tables from the peer (`--force` overwrites non-empty tables) |
| `sync:pull` | Scan local changes, then pull and reconcile the peer's journal |
| `sync:push` | Passive nodes: deliver the local journal to the hub |
| `sync:scan` | Record out-of-band local changes only |
| `sync:files` | Synchronise the shared directory |
| `sync:notify <text>` | Send a test message to Telegram and to the alert mailbox |
| `sync:check [peer] [--alert]` | Validate reachability (internet, lan, punch, relay); exit code 1 on failure; `--alert` mails/messages on a change |
| `sync:ntp [server ...]` | Measure this node's clock against NTP (`sync.ntpServers`, or the servers given) and keep the median; scheduled every 15 minutes by `SyncTasks::register()` when servers are configured |
| `sync:rehash` | Refresh recorded row state after a column was added or dropped, journaling nothing |
| `sync:prune` | Delete old journal entries and decided conflicts |
| `sync:auth-heartbeat` | Renew, acquire or release the `auth` and `cron` locks |

Concurrent runs of `pull`, `push`, `files` and `auth-heartbeat` are serialised with a file lock in
`writable/sync-locks/`; the second run exits immediately.

## Topologies

**Two nodes that reach each other.** Set `peerNode` and `peerUrl` on both.

**Hub and nodes behind NAT.** The NAT node sets `sync.passive = true`, one peer (the hub) and runs
`sync:pull` and `sync:push`; every connection is outbound. The hub lists the node as
`{"app":{"passive":true}}` in `sync.peersJson` and sets `sync.relay = true` to forward applied changes to
its other peers. Changes carry an `origin`, so a change never returns to where it came from.

For tables that travel through a relay between three or more nodes, declare `'writers' => ['<node>']`:
the version counters are exact for two parties and approximate for more.

## Reachability check

`php spark sync:check [peer]` classifies how this node connects and verifies it with signed requests.

| Mode | Meaning |
|---|---|
| `internet` | Public address, peers connect directly |
| `lan` | Private address (RFC 1918, loopback, link-local, CGNAT, IPv6 ULA) |
| `punch` | Outbound only, behind NAT (`sync.passive = true`) |
| `relay` | The peer is not reached directly but a relaying hub reaches it |

From cron, `php spark sync:check --alert` sends one Telegram / e-mail message when the verdict turns to FAIL
and one when it recovers (the last state is kept in `writable/sync-check.state`), so a check every few
minutes does not repeat itself.

Outbound, the node calls the peer's `ping` endpoint: round-trip time, clock skew against `replayWindow`,
the peer's node name and the address the peer sees. Inbound, it asks the peer to call it back (`probe`) at
the URL configured for it. Relays are used when they exist: nodes behind a relay are listed and checked
through it, and a direct peer that is down but reachable through a relay is reported as `relay` (warning,
not failure).

| Verdict | Conditions |
|---|---|
| FAIL | Peer unreachable, wrong node name, skew beyond `replayWindow`, or a node declared reachable that its peer cannot call |
| WARN | Skew above half the window, round trip above 1.5 s, plain `http://` over the internet, a passive node silent for over 10 minutes, a path only through a relay |
| OK | Everything else |

No endpoint ever receives a URL: the callback uses the URL configured on the called node, and a relay check
accepts only a name from the relay's own peer list.

## Clocks

"Newest wins" (tolerant tables, file conflicts) compares timestamps written by different machines. The package
measures the clock of every peer on the traffic that flows anyway and protects the decision:

* Every answer of the peer API carries the peer's time; with the round trip this gives the offset (NTP style),
  kept in `sync_cursor.clock_*` per peer. On the receiving side the signed request timestamp gives a coarse
  second-resolution estimate, which matters for a passive node that the hub never calls. The sample with the
  smallest round trip wins; a measurement older than an hour is ignored.
* **Simultaneous edits.** Timestamps closer than `clockTolerance` (2 s) are not ordered by time but by `tiebreak`,
  identically on both nodes.
* **Untrusted clock.** When a peer's offset exceeds `clockMax` (5 s), a conflict in a tolerant table is
  quarantined like one in a sensitive table, and a file conflict is decided by `tiebreak` instead of `mtime` (the
  loser is still kept as a `.conflict-` copy). A warning is logged.
* `sync:check` warns above `clockWarn` (2 s) and fails beyond `replayWindow`.

### NTP and the optional correction

`sync:ntp` asks the servers of `sync.ntpServers` (SNTP, one UDP packet each; the answer must echo the request, so a
forged or foreign answer is refused), takes the median of those that answered fast enough and keeps it in
`writable/sync-ntp.state`. `sync:check` shows it (`ntp` line, WARN above `clockWarn`) without touching the network;
a measurement older than 30 minutes is ignored. Nothing changes the system clock: keep `chrony`/`ntpd`/`w32time` for that.

With `sync.clockCorrect = true` the node adds the offset to the times the package writes itself (`updated_at` of a
row's registry entry and the `changed_at` of journal entries), so "newest wins" compares real times even when a
machine's clock is off. It also reports its correction in every answer (`ntp`), so a peer measures what is *left* of the
difference (peer offset + peer correction - own correction) when it asks "is that clock trustworthy?" (`clockMax`).
Both nodes may correct, only one, or none; a node that does not correct is still compared correctly.

Limits: the correction is the offset measured last (at most 30 minutes old; without a fresh measurement it is 0, and
`sync:check` says so). `updated_at` columns of your own tables are written by your application with the raw clock and
are not touched. File conflicts keep comparing raw `mtime`s with the raw clock difference, because file times come from
the file system. A passive node's clock is estimated by the hub from the request timestamp, which carries no
correction; it can only make the hub more cautious. Needs outbound UDP port 123 on the node.

Both nodes measure the same difference, but each with its own error: close to `clockMax` one node may escalate
while the other still applies the newer edit. The escalated conflict is then resolved by hand and the decision
reaches the other node as usual. Keep NTP running; this is a safety net, not a time service.

## Editing users: the auth lock

Users and groups are edited on one node at a time. A route protected by `authWriteLock:<edit|write>[,scope]`
takes an **edit lease** when editing starts and publishes what is being edited to the peer, which shows a
read-only banner and refuses its own edits meanwhile.

```php
$routes->get('admin/users/(:num)/edit', 'Users::edit/$1', ['filter' => ['session', 'authWriteLock:edit,users']]);
$routes->post('admin/users/(:num)',     'Users::save/$1', ['filter' => ['session', 'authWriteLock:write,users']]);
```

* `edit` also acts on GET; `write` lets reads pass.
* A lease is refreshed by each edit request and freed after `leaseTtl` of inactivity.
* **Catch-up:** taking the lock first pulls the peer's journal, so the editor never works on stale data.
* **Handover:** after a successful save the node scans, asks the peer to pull, and releases the lock once
  the peer confirms (up to 10 s).
* In views use `AuthWriteLock::banner()` and `AuthWriteLock::readOnly()`.
* Refused writes get a flash message (HTTP 423 for AJAX) and one alert per 10 minutes.

Manual locks (`php spark sync:auth-heartbeat --acquire=auth`) are renewed by the heartbeat; a live lock is
never stolen. If the peer is unreachable only the first-ranked node in `tiebreak` takes an abandoned lock
automatically; the other needs `--confirm`. The `cron` lock is taken automatically when free.

A network partition combined with a confirmed takeover can leave both nodes accepting user edits. Auth rows
carry `node_origin` so they can be reconciled by hand.

## Resolving conflicts

* **Admin UI** at `sync-admin/conflicts`: list, side-by-side diff, last common version, and the actions
  keep either node's version, merge per field (either side or a custom value), or ignore.
* **Telegram:** buttons for the same actions. One bot has one webhook, registered on a node reachable from the
  internet; a press for another node's conflict is forwarded to it. Creating the bot and the webhook:
  [TELEGRAM.md](TELEGRAM.md).

A decision is applied on every node idempotently and both nodes' counters are raised above both versions,
so the conflict cannot come back. If the peer is down, an authoritative journal entry carries the decision
and the peer applies it on its next pull. A node refuses a decision for a row it changed after the conflict
was detected.

## Database admin UI

For database admin (table list, filter/sort/paginate/edit rows, a free-form SQL box, a widget showcase), see
the separate [`ad-go/syncui`](https://github.com/ad-go/syncui) package — it has no dependency on `ad-go/sync`
and browses whatever `Config\SyncUi::$dbGroup` names, not only this package's own tables.

## Schema changes

Adding a column to a synchronised table changes every row's recorded hash. On each node, right after its
migration and before the next `sync:pull` (pause the scheduler with `tasks:disable`):

```bash
php spark migrate
php spark sync:rehash
```

This is valid while the new column holds the same value on all nodes (NULL or its default), so migrate all
nodes before anything writes to it. After adding tables to the config run `php spark sync:install --no-migrate`.

## Node names and the secret

`node`/`peerNode` and the secret must agree across nodes. To change them together, ask the peer first with a
signed request and change the local `.env` only after it answered. Stored names follow the rename
(`sync_cursor.peer_node`, `sync_write_lock.holder`, `node_origin`, conflict nodes).

## Cost and housekeeping

* The scanner reads each table in chunks of 500 rows and looks up metadata and parent uuids once per chunk.
  A node with 8,000 tracked rows and no change costs about 40 queries per minute. Deletions are detected in
  chunks by primary key, without holding the table in memory.
* `sync:files` skips hashing files whose mtime and size match the index. On a passive node `sync:files` also pushes its own changes to the hub (`files/put`), because the hub cannot pull from it; set `sync.files.dir` on both and schedule the command on the passive node too (the installer and `SyncTasks::register()` do).
* The pull saves its cursor every 50 changes; an interrupted run re-applies at most those, which the
  reconciler recognises as already integrated.
* `sync:prune` deletes journal entries older than `keepJournalDays` that the peer reports as applied, and
  decided conflicts older than `keepConflictDays`. Pending conflicts and the newest journal entry are kept.
  If the peer cannot be asked nothing is deleted. A peer offline longer than the retention must be
  re-bootstrapped.

## Upgrading nodes

Nodes can be upgraded one at a time: releases keep the wire format and the option names. Run
`composer update ad-go/sync`, then `php spark migrate -n 'AdGo\Sync'` (the package migrations are idempotent). A node that has not migrated yet
keeps working: it only lacks the clock measurement (and, for `clockCorrect`, the peer's correction: the migration `AddClockNtpColumn` adds `sync_cursor.clock_ntp_us`).

## Sticky routing

Put the load balancer in sticky mode per user (cookie or hash of the user id). The same user then lands on
the same node, "both nodes touch the same row" becomes rare, and conflicts drop by roughly 90%.

## Security

* Every API call is a POST signed with HMAC-SHA256 over method, path, timestamp, nonce and body hash, within
  ±`replayWindow` seconds. The nonce is single-use: the first request to claim it wins, atomically, in the
  `sync_nonce` table (purged by `sync:prune` and now and then by the API itself). Without that table (not
  migrated yet) the application cache is used; if neither stores values the API answers `503` instead of
  accepting requests unprotected. Responses are signed and bound to the request nonce.
* Request bodies above `sync.maxBody` are refused (`413`), a `push` with more than `sync.maxPushChanges`
  entries too.
* Payloads are PHP-`serialize()`d and read with `allowed_classes => false`.
* The Telegram webhook is authenticated by the secret-token header and the chat id.
* The client refuses plain `http://` peers unless `sync.allowInsecure = true` (localhost is exempt). Use HTTPS
  and, where the host allows it, restrict `/sync-api/*` to the peers' addresses.
* Incoming rows are reduced to the columns the local table has and never set the primary key; `files/get`
  serves neither ignored names, conflict copies, nor anything reached through a symbolic link.
* `sync.telegram.allowed_users` limits who can resolve conflicts from Telegram (see `TELEGRAM.md`).
