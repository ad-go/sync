# Architecture

## Package tables

Existing tables are never altered. The package owns seven tables; `migrate:rollback` removes them.

| Table | Role |
|---|---|
| `sync_row_meta` | One row per synchronised row: `uuid`, `rev`, `peer_rev`, `row_hash`, `base_blob` (last agreed state) |
| `sync_changes` | Append-only change journal, with the `origin` node of each change |
| `sync_cursor` | Position reached in each peer's journal, the time of the last contact, the measured clock offset of the peer and the NTP correction it reports (`clock_ntp_us`) |
| `sync_conflicts` | Quarantined conflicts and the log of automatically resolved ones, with both versions |
| `sync_write_lock` | The `auth` and `cron` locks and edit leases |
| `sync_files` | Index of the shared directory: path, hash, mtime, size, tombstones |
| `sync_nonce` | Nonces of accepted requests (primary key = first use wins), purged after `2 × replayWindow` |

Timestamps are BIGINT microseconds for portability across databases.

## Recording changes

A row is identified across nodes by a **uuid**; primary keys stay local and foreign keys travel through
uuids. Changes enter the journal in three ways:

1. **`Syncable` models** journal `insert`, `update` and `delete` in the same transaction as the registry row.
2. **The scanner** (`ChangeScanner`, run by every `sync:pull` and by `sync:scan`) compares each row with the
   hash in `sync_row_meta`. It reads tables in chunks of 500 rows and detects deletions by primary key in
   chunks. Tables with `'scan' => false` are skipped.
3. **Applied remote changes** are journaled again only on a relaying hub, tagged with their origin.

## Reconciliation

Every row carries two counters per node: `rev` (its own writes) and `peer_rev` (the peer's counter already
integrated). A change carries the sender's `rev` and what the sender had integrated of the receiver's.
A local write leaves with `max(rev, peer_rev) + 1`, so it always exceeds anything already seen.

| Situation | Outcome |
|---|---|
| Only one side changed | Fast-forward |
| Both changed, disjoint fields | Three-way merge against `base_blob`, identical on both nodes with no extra traffic |
| Same field, `tolerant` table, field not in `sensitive_fields` | Last writer wins (newer `updated_at`; a difference below `clockTolerance` counts as a tie: node ranked first in `tiebreak`). Not decided by time, but quarantined, when the writer's clock is further than `clockMax` from ours |
| Same field, `sensitive` table or field; or delete against edit | Nothing applied; quarantined as `pending` and alerted |
| Change already integrated | No-op: applying a change twice changes nothing |
| Parent row not yet arrived | The pull pauses at that change and resumes on the next run |
| Child whose parent is already deleted | The delete is accepted; no orphan is kept |

Deletes travel as tombstones. Every conflict, even an automatically resolved one, keeps both versions in
`sync_conflicts`.

## Transport

Every call is a form-encoded POST; answers are PHP-serialized. Nothing is JSON, apart from the Telegram Bot
API itself (`Libraries\TelegramNotifier`).

* `SyncHmac` verifies the HMAC-SHA256 signature over method, path, timestamp, nonce and body hash, the
  `replayWindow`, and the single-use nonce. Responses are signed and bound to the request nonce.
* **Pull:** the node asks a peer for its journal after the stored cursor, in batches of `pullBatch`, applies
  each change through the `Reconciler`, and saves the cursor every 50 changes.
* **Push (passive nodes):** the node delivers its own journal to the hub (`sync-api/push`); the hub's
  `sync_cursor` for that node decides what is sent. The hub then applies the changes like any other.
* **Kick:** after a local change a node tells its reachable peers to pull now, so propagation takes seconds
  rather than a cron period.
* **Relay:** a hub with `relay = true` journals what it applies again, tagged with the `origin`, and does not
  send a change back to its origin.

## File sync

`FileSync` mirrors `files.dir`, sub-directories included, between the nodes of the primary peer.

1. Each node builds a manifest: relative path, SHA-256, mtime and size, with the marker `dir` for
   directories. Links are skipped, as are the `ignore` patterns and `.conflict-` copies. Unchanged files
   (same mtime and size as in the index, indexed at least two seconds ago) are not re-hashed.
2. For every path the decision uses three states: local **L**, remote **R** and the index **I** (what both
   sides agreed on last time). Only L changed: send; only R changed: fetch; L deleted and R unchanged:
   delete remotely; and symmetrically. "Send" means the peer pulls it on its own run, except for a passive node, which pushes it to the hub (`files/put`, refused as `stale` when the hub changed the path meanwhile).
3. If both changed, the newer mtime wins and the loser is kept as `<name>.conflict-<node>-<time>`; ties are
   decided by `tiebreak`. A file/directory type clash is refused.
4. Writes are atomic: downloaded to `.part`, verified against the SHA-256, then renamed. Paths are confined
   to the directory.
5. Directories: files are processed first, then directories the peer has by increasing depth (parents
   first), then directories the peer lacks by decreasing depth (children first). A directory is removed only
   if it is empty; otherwise its index entry stays until it is.

## Layers

```
Commands / Tasks / Filters / Controllers      entry points (spark, scheduler, HTTP)
Kernel                                        wiring: Options, MetaStore, RowGateway, clients
Reconciler  ChangeScanner  Pruner  Bootstrapper  FileSync  Reachability   algorithms
MetaStore  RowGateway  SyncClient  Signer       persistence and transport
Options                                       immutable view of Config\Sync
```

`Kernel` is available as `service('sync')`. The core classes depend only on `Options` and a database
connection, which is how the unit tests run them against two in-memory SQLite databases (`tests/TwoNodes.php`).
