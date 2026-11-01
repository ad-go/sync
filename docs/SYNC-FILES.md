# File synchronization (`sync:files`)

[← Database synchronization](SYNC-DB.md)

A directory is kept identical on two nodes. Only differences travel (SHA-256 comparison); when both sides modified the same file the newest wins and the loser is kept as a `.conflict-…` file.

## 1. Principle and guarantees

Files have no change log. Each node *describes* its directory in a manifest, and the two manifests are compared with a third state, **the index from the last agreement** (`sync_files`). From the three values it is deduced who changed what, usually without synchronized clocks.

| Guarantee | How |
|---|---|
| Only differences travel | identical `sha256` → nothing transferred |
| No silent overwrite | the loser of a conflict is copied to `name.conflict-<node>-<YmdHis>` |
| Atomic write | written as `name.part-<hex>`, then renamed |
| Integrity | downloaded content must match the manifest hash, otherwise it is deferred |
| No overlap | `sync:files` takes the exclusive `files` lock; a second run exits |
| Confinement | nothing outside the configured directory |

## 2. Components

| Component | Role |
|---|---|
| `Libraries\FileSync` | `manifest()`, `run()`, `read()`, `receive()` |
| `Commands\SyncFiles` | runs it under the `files` lock; prints *downloaded / pushed / deleted / directories / conflicts / kept local* |
| `Tasks\SyncTasks` | schedules `sync:files` every 5 minutes |
| `SyncController::filesManifest` / `filesGet` / `filesPut` | endpoints `sync-api/files/manifest`, `files/get`, `files/put` |
| table `sync_files` | state at the last agreement |

```php
public array $files = [
    'dir'    => '',                       // default WRITEPATH/sync-files; set absolute on each node (.env sync.files.dir)
    'ignore' => ['*.tmp', '.gitkeep'],    // fnmatch() on the relative path or the file name
];
```

## 3. A run

```text
Node A (running sync:files)                                  Node B (partner)
1  indexed = sync_files
2  local   = own manifest()
3  remote  = POST files/manifest ──────────────────────────►  manifest()
                                  ◄──── {path, sha256, mtime, size}
4  for each path in local ∪ remote:  L local, R remote, I index
      L = R        → remember
      L = I        → B changed → takeRemote (download / delete)
      R = I        → A changed → stays; B takes it on its own run
      otherwise    → conflict (§6)
   download: POST files/get(path) ────────────────────────►
                                  ◄──── bytes
5  verify sha256, write .part-xxxx, rename, touch(mtime), remember
6  drop index entries that no longer exist anywhere
```

Each node *pulls* what the other changed, so a change appears after the other node's next run (at most 5 minutes). A **passive node** (behind NAT) cannot be called and pushes its own changes (§8).

## 4. Manifest and hash reuse

`manifest()` walks the directory recursively and returns `{path, sha256, mtime, size}` sorted by path, for files and **directories**. Symbolic links, `ignore` matches and anything with `.conflict-` in its path are skipped.

The hash in the index is reused only if all hold:

```text
ix.sha256 ≠ NULL, size > 0, ix.mtime == mtime, ix.size == size,
mtime + 2 s ≤ ix.seen_at        // the file was quiet when indexed
```

The last condition protects files written in the same second as the indexing (one-second mtime resolution). Otherwise `hash_file('sha256')` is computed. Cost: a walk and `stat` per file, plus reading only new or changed files.

## 5. Three-way decision

`null` means "does not exist".

| Condition (in order) | Interpretation | Action |
|---|---|---|
| `L = R` | identical (or both absent) | update the index if it differs |
| `L = I` | only the partner changed | `takeRemote`: download or delete locally |
| `R = I` | only local changed | keep; the partner takes it |
| otherwise | both changed | conflict (§6) |

| Situation | L | R | I | Result |
|---|---|---|---|---|
| New on the partner | null | h | null | downloaded |
| New locally | h | null | null | kept |
| Modified on the partner | h1 | h2 | h1 | downloaded |
| Deleted on the partner | h1 | null | h1 | deleted locally |
| Deleted locally | null | h1 | h1 | kept; the partner deletes |
| Deleted on the partner, modified locally | h2 | null | h1 | conflict |
| First contact, different files | h1 | h2 | null | conflict |
| First contact, identical file | h | h | null | recorded |

On the first run the index is empty, so a file on one side only is "new", never "deleted".

## 6. Conflict

```text
byTime     = mtime_remote ≠ mtime_local  and  |difference| ≥ clockTolerance  and  the peer's clock is trusted
remoteWins = byTime ? mtime_remote > mtime_local
                    : rank(peerNode) < rank(thisNode)                           // "tiebreak" order
```

The peer's clock is not trusted when its measured offset exceeds `clockMax` ([OPERATIONS.md](OPERATIONS.md#clocks)). In both cases the loser is kept as a `.conflict-` copy, so nothing is lost.

- **Partner wins:** the local copy is saved as `path.conflict-<localNode>-<YmdHis>`, then `takeRemote`.
- **Local wins:** nothing now; the partner sees the mirror situation, reaches the same decision, saves its copy and takes our version.

`.conflict-` files stay on the node that created them and need a human decision. A conflict needs changes on both sides within one window between runs.

## 7. Transfer, deletion, directories

**`takeRemote`**
1. `resolve()` confines the path: empty paths, `\0`, absolute paths (`/…`, `C:…`) and `..` or empty segments are refused.
2. Deletion (`R = null`): `unlink`; the index keeps the entry with `sha256 = NULL` (accepted deletion).
3. Download: `files/get` returns the content; if its hash differs from the manifest the file is skipped until the next run.
4. Atomic write: create directories, write `path.part-<8 hex>`, `rename()`.
5. `touch($abs, $mtime)` copies the source mtime so that the next run sees equal hash and date.
6. `remember(path, sha, mtime, size)` with `seen_at = now`.

**Subdirectories.** The whole tree is synchronized at any depth, including empty directories. A directory is a manifest entry with "hash" `dir`, `mtime = 0`, `size = 0`, under the same decision table:

| Situation | L | R | I | Result |
|---|---|---|---|---|
| New on the partner | null | dir | null | `mkdir -p` |
| New locally | dir | null | null | kept |
| Deleted on the partner | dir | null | dir | `rmdir`, only if empty |
| Deleted locally | null | dir | dir | kept; the partner deletes |
| Directory on one side, file on the other | dir | hash | — | reported; nothing touched |

Order: files first; then the partner's directories, parents before children; then directories the partner no longer has, children before parents (a deleted tree disappears in one run). A non-empty directory is never deleted; a file is not written over a directory or the reverse; directories have no synchronized mtime. A manifest without directories breaks nothing.

## 8. Push from a passive node (`files/put`)

On a passive node (`sync.passive = true`) the cases *local changed, partner unchanged* and *both changed, local wins* deliver the change instead of keeping it:

```text
offer(path, L, R):                     # R = what the hub has, from the manifest just fetched
    not passive or peer cannot receive → kept++ ; return
    kind = delete if L is null; dir if L is a directory; file otherwise (refuse > 4 MiB or if the hash changed)
    POST files/put { path, kind, expect = R, sha256 = L, mtime, content = base64 }
    ok → remember(path, L); pushed++       else → kept++ (decided again next run)
```

`FileSync::receive()` on the hub:
1. the sender must be a configured passive peer (403 otherwise) and the request is signed;
2. the path is confined and must not be ignored or a `.conflict-` copy;
3. **`expect` must equal what the hub holds now** (a SHA-256, `dir`, or nothing); otherwise `stale`, nothing is written, and the next run decides by the conflict rule. Creating an existing directory is always accepted;
4. a file is decoded, its SHA-256 compared with the announced one, written to `<name>.part-…`, renamed; directories are created; `mtime` restored;
5. a deletion removes a file, or a directory only when empty (`not empty` otherwise); a file/directory clash is refused.

The hub does not index the entry: it is an ordinary local change that its other partners download on their run. Files travel *passive node → hub → other nodes*. Files over 4 MiB stay local (`kept local`); keep archives out of this directory.

## 9. The `sync_files` index

| Column | Content |
|---|---|
| `path_hash` | `sha1(path)`, fixed-length key |
| `path` | relative path with `/` |
| `sha256` | hash at the last agreement; `dir` = directory; `NULL` = accepted deletion |
| `mtime`, `size` | for hash reuse (§4) |
| `seen_at` | microseconds of recording (the 2-second guard) |

The index is local to each node. Losing it loses no files; the next run starts as "first contact".

## 10. Safety and topology

- The endpoints are behind the `syncHmac` filter (signed `POST`, replay window, single-use nonce, signed response; see SYNC-DB §14).
- `files/get` refuses ignored names, `.conflict-` copies, symbolic links and paths outside the directory.
- Bytes are copied, nothing is interpreted. One `files` run per node at a time.
- Files are synchronized with the node's main partner (`peerNode`/`peerUrl`); a third node receives a passive node's files through the hub's own sync, not directly. A change appears after at most one period of the other node's schedule (5 minutes) plus the run.

## 11. Examples

**New file.** `uploads/p1.jpg` appears on A. A: L=h, R=null, I=null → kept. B: L=null, R=h, I=null → downloads, verifies, renames, touches, remembers. Both have I = h.

**Both modified.** I = h0; A has h1 (mtime 10:00:05), B has h2 (10:00:40). A runs: conflict, remote is newer → copies h1 to `file.conflict-site1-20261019100100`, takes h2. B runs: identical. Both have h2; A also has the conflict copy.

**Deletion.** I = h; A deletes. B: L=h, R=null, I=h → unlink, remember(`NULL`); the next run drops the index entry.

## 12. Limits

| Limit | Consequence |
|---|---|
| A transfer loads the whole file in memory | suited to images and documents, not archives of hundreds of MB |
| No rename detection | a move is a deletion plus a new file; the content is retransferred |
| The manifest walks the whole tree each run | thousands of files: negligible; hundreds of thousands: slow |
| Conflicts depend on `mtime` | below `clockTolerance` and above `clockMax` the tiebreak decides; in between a skewed clock can pick the wrong winner. The loser stays in `.conflict-` |
| Only the main partner | other nodes get a passive node's files through the hub |
| Passive push up to 4 MiB | larger files stay local |
| `.conflict-` files neither propagate nor clean themselves | manual checking |
