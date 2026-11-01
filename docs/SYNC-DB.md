# Database synchronization

[→ File synchronization](SYNC-FILES.md)

Asynchronous, row-level replication between nodes that each have their own database. Every node keeps a **change journal** and a **row registry** (uuid + version). Nodes exchange journals over signed HTTPS; every incoming change goes through a reconciler with field-level merging. Local keys (`id`) never travel: rows are identified by uuid.

## 1. Principles

- **Each node writes locally** and does not wait for the others (active-active).
- **Identity = uuid.** The same row has different ids on different nodes; `sync_row_meta` links them.
- **Journal, not table comparison.** A node asks "what changed after position X".
- **Idempotence.** Applying a change twice changes nothing (revisions are compared), so every failure is resolved by resending.
- **Deterministic convergence.** Nodes that see the same concurrent changes reach the same result without further messages.
- **Nothing is lost silently.** Every conflict is recorded in `sync_conflicts`; dangerous ones apply nothing and wait for a human.
- PHP `serialize` / `unserialize(allowed_classes=false)`, not JSON.

## 2. Roles

```text
                      ┌────────────────────────────────────────────┐
                      │  Hub (site2)  · sync.relay = true          │
   NAT / no inbound   └───────▲───────────────────▲────────────────┘
 ┌───────────────┐     pull+push                  │ pull (+kick)
 │ Node "app"    ├────────────┘          ┌────────┴──────┐
 │ (passive)     │                       │ Node site1    │
 └───────────────┘                       └───────────────┘
```

| Role | Configuration | Behavior |
|---|---|---|
| Regular node | `peerNode`, `peerUrl` or `peers` | pulls every minute from its partners and is pulled by them |
| Passive node (NAT) | `sync.passive = true` | nobody can call it: it pulls from the hub and pushes its own journal |
| Hub with relay | `sync.relay = true` + a `passive` partner | what it applies from one partner is rewritten into its own journal for the others |

A node keeps **one cursor per partner**. Node names appear in `X-Sync-Node` and in `node_origin` / `origin`.

## 3. Tables

**`sync_row_meta`** (row registry)

| Column | Meaning |
|---|---|
| `table_name`, `uuid` | logical identity (unique together) |
| `row_id` | local primary key; `NULL` for a deleted row (tombstone) |
| `rev` | the row's own write counter |
| `peer_rev` | the last partner counter integrated here |
| `updated_at` | microseconds of the last change (for "newest wins") |
| `node_origin` | node that produced the current state |
| `deleted_at` | moment of deletion |
| `row_hash` | `sha1` of the row as last recorded |
| `base_blob` | the row at the last agreement (base of the three-way merge) |

**`sync_changes`** (journal, append-only)

| Column | Meaning |
|---|---|
| `seq` | position; partner cursors refer to it |
| `table_name`, `uuid`, `op` | row and operation (`I` / `U` / `D`) |
| `rev`, `seen` | the sender's revision and its `peer_rev` at that moment |
| `authoritative` | 1 = result of a conflict decision, taken as is |
| `changed_at` | microseconds |
| `node_origin` | node that wrote the entry into this journal |
| `origin` | on a relay: the partner it comes from; otherwise `NULL` |
| `payload` | serialized row with foreign keys as uuids; `NULL` on delete |

| Other table | Role |
|---|---|
| `sync_cursor` | `peer_node` → `last_seq`: how far that partner's journal is applied; `clock_offset_us`, `clock_rtt_us`, `clock_at`: the partner's measured clock |
| `sync_conflicts` | both versions, base, diverging fields, severity (`safe`/`dangerous`), status (`pending`/`resolved`/`ignored`) |
| `sync_write_lock` | the distributed `auth` and `cron` locks |
| `sync_files` | file index ([SYNC-FILES.md](SYNC-FILES.md)) |
| `sync_nonce` | nonces of accepted API requests (replay protection) |

## 4. Row identity

- A new row gets a **uuid v4** when first recorded.
- Rows that exist at installation get a **deterministic uuid v5** from `"q1-sync|table|pk"`, so two databases with the same data agree without traffic (`ChangeScanner::seed()`). The salt is part of the identifier and must not change.

**Foreign keys.** Columns declared in `fk` (`'column' => 'parent_table'`) travel as the parent's uuid:

```text
export:  products.customer_id = 17  →  the parent's uuid from sync_row_meta
import:  uuid → the parent's local row_id
         ├─ parent exists            → row_id is set
         └─ missing or deleted       → MissingParent: apply stops before this change and resumes on the next run
```

On export, a row whose parent has no metadata yet is "deferred" and retried on every scan. Tables linked by a text value (customer code, order number) send the column unchanged.

**Alias, ignored columns, filter.**
- `'table' => 'orders'`: the logical name used in the journal and on the wire differs from the physical table.
- `ignore_fields`: not part of the row state; removed on export, in the hash and on apply (before the hash and the base, so the recorded hash matches what the scanner reads).
- `where`: only matching rows participate.

## 5. Change capture

**`Syncable` trait (at write time).** In one transaction the model writes the row and `syncCapture()` calls `MetaStore::recordLocal(table, id, op, row)` and `Kernel::markChanged()` (schedules a kick). Writing and journaling are atomic. Physical deletion is `D`; soft deletion is an ordinary `U`.

**`ChangeScanner` (bypassed writes).** Direct SQL, other programs and tables without the trait are found by comparing each row's hash with `row_hash`; it runs at the start of every `sync:pull` and `sync:push`.

```text
for each configured table (parents first), unless scan = false:
    read rows in chunks of 500 by ascending pk + their registry (one query per chunk)
        no registry               → recordLocal(I)    "+"
        hash(row) ≠ row_hash      → recordLocal(U)    "~"
        missing parent (FK)       → deferred, retried next scan
    registry rows without a table row (chunks of 500, one WHERE pk IN (...)) → recordLocal(D)  "-"
```

The hash is `sha1(serialize(row))` with sorted columns and all values as strings (`null` stays `null`), so key order and driver type differences do not matter.

> **Cost.** The scanner reads each table in full, so it suits tables of thousands of rows. For very large tables use `'scan' => false`: they are not read and their writes must go through `Syncable`.

**`recordLocal` revision rules**

| Situation | Effect |
|---|---|
| No registry, op ≠ D | new entry: uuid v4, `rev = 1`, `peer_rev = 0`, op `I` |
| Deleted row never tracked | nothing to propagate |
| Registry exists | `rev = max(rev, peer_rev) + 1`; `updated_at = now`; `node_origin = this node`; `row_id = NULL` on `D` |

`max(rev, peer_rev)` makes a local change to a received row start above the received revision.

## 6. Versioning

Each node keeps `rev` (own) and `peer_rev` (the partner's, as integrated). A sent change carries `rev` = **R** and `seen` = **S** (how much of the recipient's revisions the sender integrated).

| Test at the recipient | Meaning |
|---|---|
| `R ≤ peer_rev` | already integrated → **SKIPPED** |
| `R > peer_rev` | the partner's change is new |
| `rev_local ≤ S` | the sender knew my version → **fast-forward** |
| `rev_local > S` | I changed it after it saw → **concurrent** (section 8) |

```text
A: insert        rev=1 ─────────────────────►  B: new row  rev=0  peer_rev=1
B: update        rev=max(0,1)+1=2  seen=1 ──►  A: R=2 > 0, rev_local(1) ≤ S(1) → fast-forward, peer_rev=2
A: update        rev=max(1,2)+1=3  seen=2 ──►  B: R=3 > 2, rev_local(2) ≤ 2   → fast-forward
```

## 7. Reconciling a change (`Reconciler::apply`)

1. **Echo:** `origin` = this node → `CONVERGED`.
2. **Unknown table** → `SKIPPED`.
3. **Writers:** the table declares `writers` and the origin is not listed → `REJECTED`.
4. Ignored columns are removed from the received row.
5. **Row unknown locally:** insert (or only a tombstone for `D`), registry `rev = 0`, `peer_rev = R`, `base_blob` = received state → `APPLIED`.
6. **Authoritative:** state taken as is, any pending conflict closed.
7. **Duplicate** (`R ≤ peer_rev`) → `SKIPPED`.
8. **Conflict already in quarantine:** the stored remote version is updated → `PENDING`.
9. **Only the partner is new** (`rev_local ≤ S`): write the state, update the registry (`peer_rev = R`, hash, base) without bumping `rev` and without journaling → `APPLIED`.
10. **Both new:** concurrent (section 8).
11. **Relay** (hub): an applied or merged change from another node is rewritten into the hub's journal (section 11).

A change is applied in one transaction, through the query builder rather than models, so capture is not triggered again.

## 8. Conflicts

**Base.** The state at the last agreement: the local journal entry with `rev = S` and `node_origin` = this node if it still exists, otherwise `base_blob`.

**Identical states** (ignoring ignored columns): the conflict disappears, `CONVERGED`; revisions are aligned.

**Newer** wins by timestamp (`updated_at` vs `changed_at`). Timestamps closer than `clockTolerance` (2 s) are a tie, and a tie goes to the `tiebreak` order (e.g. site1 before site2); both nodes see the same two numbers and decide alike.

**Untrusted clock.** If the measured clock of the writing node differs from ours by more than `clockMax` (5 s), time does not decide: LWW is skipped and the conflict is quarantined as for a sensitive table (see [OPERATIONS.md](OPERATIONS.md#clocks)). A node whose peer was never measured trusts it.

**Delete vs. modify.** `tolerant` tables apply the winner and record the conflict as resolved; `sensitive` tables go to quarantine.

**Three-way merge, per field** (union of keys, without `ignore_fields`; `l` local, `r` received, `b` base):

```text
l = r                          → nothing
only r changed (l = b)         → merged = r
only l changed (r = b)         → l stays
both changed, differently      → overlap

no overlaps                    → MERGED: merged state, conflict "safe", "auto-merge"
overlaps, tolerant table, no sensitive_fields in the overlap
                               → LWW on the overlapping fields, "auto-lww"
otherwise                      → QUARANTINE: nothing applied, status pending, Telegram notification
```

On MERGED and LWW both versions are first written to `sync_conflicts`, the state is applied locally and `rev` is not bumped: the partner performs the same deterministic merge and reaches the same result.

**Manual resolution (`ConflictResolver`).**
1. An administrator chooses a node, `merge` (with values) or `ignore` (UI or Telegram).
2. Refused if the row changed meanwhile (`rev` ≠ `local_rev`).
3. New revision `max(rev, remote_rev) + 1`; the state is applied (`adopt`) with `rev = peer_rev = newRev` and an **authoritative** entry is journaled.
4. The decision goes directly to the partner (`conflict/resolve`, with `expect_rev` against staleness); if it does not answer, the authoritative entry carries it on the next pull.

## 9. Pull

```text
sync:pull (every minute, under the "pull" lock):
    1. ChangeScanner::scan()
    2. for each active partner:
         cursor = sync_cursor[partner]
         repeat:
             page = POST pull(after = cursor, limit = pullBatch)    (default 200, max 1000)
             empty → stop
             for each change with seq > cursor: Reconciler::apply()
                 MissingParent → stop; cursor stays before it
                 every 50 changes (checkpoint) and at the end → setCursor
```

The cursor is a table row. After an abrupt stop at most 50 changes are reapplied and recognised as integrated. A passive node is never pulled.

## 10. Push from the passive node

The node behind NAT initiates both directions. `sync:push` (under the "push" lock) scans, then:

```text
pos = POST auth-lock/sync (from=<node>, kick=0).applied     ← how much of my journal the hub applied
repeat:
    page = changesSince(pos, 200)
    empty → stop
    r = POST push(changes = page)
    r.applied ≤ pos → stop (hub did not advance or is busy)
    pos = r.applied;  r.stopped ≠ null → stop
```

The hub's cursor is the truth: the node keeps no local send position. On the hub, `push` takes the "pull" lock with a 20 s wait (`exclusiveWait`) and uses the same `applyBatch` as pull; when busy it answers `stopped = "another sync is running"`.

## 11. Relay through the hub

The hub has several partners and each keeps a single cursor in its journal.

```text
agent → hub:   apply(change, origin=app)
               ├─ applies in the hub's database
               └─ relay(): rev_hub+1, journal(op, rev_hub, seen=peer_rev, row = agreed state, origin = "app")
site1 ← hub:   pull brings the entry (origin = app) → applied normally
agent ← hub:   pull brings the same entry: origin = own node → echo → CONVERGED
```

`origin` separates own writes (`NULL`) from relayed ones and prevents loops. The relay rewrites the agreed state from `base_blob`, at the cost of one extra journal entry per applied change.

## 12. Fast propagation (kick)

For writes made through the web application, `markChanged()` schedules, after the response is sent:

- **active node:** `auth-lock/sync(kick=1)` to the partners, which pull immediately;
- **passive node:** scan + direct push to the hub.

Writes that bypass the web application wait for the next one-minute cycle.

## 13. Locks

| Lock | Mechanism | Purpose |
|---|---|---|
| `pull`, `push`, `files` | `flock(LOCK_EX\|LOCK_NB)` in `writable/sync-locks` | one run of each per node; "already running" means a live process |
| `pull` on a received `push` | `exclusiveWait(…, 20 s)` | the server does not apply in parallel with its own pull |
| `auth`, `cron` | row in `sync_write_lock` + heartbeat; TTL 120 s (`lockTtl`), lease 300 s | one node edits users; one node runs singular cron jobs |

A live `auth`/`cron` lock is not stolen; simultaneous requests are resolved by `tiebreak`; if the partner is unreachable only the first node in `tiebreak` takes an abandoned lock automatically, the other needs explicit confirmation. After taking over, the new holder first catches up with the partner's journal (`catchUp`); handover happens after the partner confirms it applied ours (`handover`).

## 14. Transport security

```text
request:  POST /sync-api/<endpoint>      body = form-urlencoded
          X-Sync-Node       = node name
          X-Sync-Timestamp  = t
          X-Sync-Nonce      = 16 random bytes (hex)
          X-Sync-Signature  = HMAC-SHA256(secret, METHOD \n path \n node \n t \n nonce \n sha256(body))
response: serialized body
          X-Sync-Signature  = HMAC-SHA256(secret, "response" \n nonce \n sha256(body))
```

- The `syncHmac` filter accepts only `POST` and bodies up to `sync.maxBody`. It checks the secret, |now − t| ≤ `replayWindow` (300 s), a non-empty nonce of at most 64 characters and compares with `hash_equals`.
- **Single-use nonce:** claimed atomically in `sync_nonce` for `2 × replayWindow`; a repeat gets 401. Without that table the cache is used; with no usable store the API answers 503.
- The response is bound to the request nonce.
- `X-Sync-Node` is part of the signed string (a signed request cannot be relabelled as another node) and must be a configured partner (else 403); it becomes `sender()` and selects the cursor on the hub.
- The client requires HTTPS with certificate verification (`allowInsecure` overrides, localhost exempt).
- Content is read with `unserialize(allowed_classes: false)`; incoming rows keep only the local table's columns and never set the primary key.

Reachability validation (`sync:check`) is described in [OPERATIONS.md](OPERATIONS.md).

## 15. Pruning and bootstrap

**Pruner (`sync:prune`, daily 03:30).** A `sync_changes` entry is deleted only if it is older than `keepJournalDays` (30) **and** all reachable partners applied it (`min(applied)`, learned through `auth-lock/sync`); the last entry always stays so `seq` never repeats. Decided conflicts older than `keepConflictDays` (90) are deleted, `pending` never. If a partner does not answer, nothing is deleted. Old nonces are purged too.

**Bootstrap (`sync:bootstrap`).** For tables of official packages (Shield users, settings): a one-time copy from the master in pages of 500, in dependency order, with foreign key checks off; afterwards those tables leave the recurring sync (user editing is governed by the `auth` lock). It refuses a non-empty local table unless `--force`.

## 16. Failure behavior

| Event | Result |
|---|---|
| Process dies mid-batch | cursor is at the last checkpoint; at most 50 changes are reapplied and ignored |
| Partner unreachable | pull/push fails with a message; the journal accumulates and continues from the cursor |
| Parent missing (FK) | batch stops before the change and resumes automatically |
| Hub pulls and receives a push at once | `exclusiveWait(20 s)`; on timeout "another sync is running", resend next time |
| Same row changed on two nodes | field-level merge; overlaps: LWW or quarantine |
| Child whose parent is gone | treated as an orphan and deleted without failing the batch |

## 17. Limits

- **Two counters** are exact for two nodes. With a hub and three nodes the hub keeps one `peer_rev` per row, so the order of concurrent changes from two partners is approximate; declare a single writer (`writers`) where it matters. Convergence still holds.
- **Orphan rows** whose parent exists nowhere stay deferred; clean the source.
- **The scanner** is linear in table size; use `scan = false` plus `Syncable` for large tables (direct SQL writes on them are not detected).
- **Clocks:** "newest wins" uses the writer's clock. Differences below `clockTolerance` are decided by `tiebreak`, differences above `clockMax` are escalated; in between, a skewed clock can still pick the wrong winner (both versions stay in `sync_conflicts`).
- **Latency:** propagation is asynchronous; a passive node without web traffic depends on its one-minute cycle.
