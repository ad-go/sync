<?php

declare(strict_types=1);

namespace AdGo\Sync\Libraries;

use CodeIgniter\Database\BaseConnection;

/**
 * Keeps one directory identical on both nodes. Three-way logic against
 * `sync_files` (the state at the last agreement):
 *
 *   local == remote            -> nothing, remember it
 *   local unchanged (== index) -> take the remote version (download / delete)
 *   remote unchanged           -> keep local (the peer pulls it)
 *   both changed differently   -> newer mtime wins; the loser is kept as
 *                                 "<name>.conflict-<node>-<ts>"
 *
 * Only differences are transferred (sha256 comparison). Paths are confined to
 * the configured directory.
 *
 * Sub-directories are part of the tree: a directory is an entry whose "hash" is the marker DIR, so the same three-way
 * logic creates it on the peer (also when it is empty) and removes it when the peer removed it (only while it is
 * empty here: files go first, directories deepest first). A path that is a directory on one node and a file on the
 * other is reported as a conflict and left alone.
 *
 * A passive node (behind NAT) cannot be pulled from, so it PUSHES its own changes to the hub (`files/put`, see
 * receive()) instead of leaving them for the peer to fetch. The hub accepts a change only if it still holds the
 * state the pusher saw (`expect`); otherwise the next run decides it like any other conflict.
 */
class FileSync
{
    /** The "hash" of a directory entry in the manifest and in the index. */
    public const DIR = 'dir';

    /** Largest file a passive node pushes in one request (sent base64-encoded; stays under a typical post_max_size). */
    public const MAX_PUSH = 4 * 1024 * 1024;

    private readonly string $base;

    public function __construct(
        private readonly BaseConnection $db,
        private readonly Options $opt,
        private readonly PeerClient $peer,
    ) {
        $dir = rtrim((string) $this->opt->files['dir'], '/\\');

        if ($dir !== '' && ! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $this->base = $dir === '' ? '' : (string) realpath($dir);
    }

    /**
     * Manifest of this node (served to the peer and used locally). A file whose mtime and size are those recorded
     * in the index, and that was indexed at least two seconds after its last change, is not read again: its
     * recorded hash is used.
     *
     * @param array<string, array<string, mixed>>|null $indexed the index rows by path (loaded when not given)
     *
     * @return list<array{path: string, sha256: string, mtime: int, size: int}>
     */
    public function manifest(?array $indexed = null): array
    {
        if ($this->base === '') {
            return [];
        }

        $indexed ??= $this->indexed();
        $out = [];
        $it  = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->base, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);

        foreach ($it as $f) {
            /** @var \SplFileInfo $f */
            if ($f->isLink() || (! $f->isFile() && ! $f->isDir())) {
                continue;
            }

            $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($this->base) + 1));

            if ($this->ignored($rel) || str_contains($rel, '.conflict-')) {
                continue;
            }

            if ($f->isDir()) {
                $out[] = ['path' => $rel, 'sha256' => self::DIR, 'mtime' => 0, 'size' => 0];

                continue;
            }

            $mtime = $f->getMTime();
            $size  = $f->getSize();
            $ix    = $indexed[$rel] ?? null;
            $sha   = $ix !== null && $ix['sha256'] !== null && $size > 0 && (int) $ix['mtime'] === $mtime && (int) $ix['size'] === $size
                && $mtime + 2 <= intdiv((int) $ix['seen_at'], 1_000_000) ? (string) $ix['sha256'] : hash_file('sha256', $f->getPathname());

            $out[] = ['path' => $rel, 'sha256' => $sha, 'mtime' => $mtime, 'size' => $size];
        }

        usort($out, static fn ($a, $b) => strcmp($a['path'], $b['path']));

        return $out;
    }

    /** Raw bytes of a file under the base dir, or null. */
    public function read(string $rel): ?string
    {
        $abs = $this->resolve($rel);

        if ($abs === null || $this->ignored($rel) || str_contains($rel, '.conflict-') || ! $this->insideBase($abs)) {
            return null;
        }

        return is_file($abs) ? (string) file_get_contents($abs) : null;
    }

    /**
     * @return array{downloaded: int, pushed: int, deleted: int, conflicts: int, kept: int, dirs: int}
     */
    public function run(): array
    {
        $stat = ['downloaded' => 0, 'pushed' => 0, 'deleted' => 0, 'conflicts' => 0, 'kept' => 0, 'dirs' => 0];

        if ($this->base === '') {
            return $stat;
        }

        $indexed = $this->indexed();
        $local   = $this->index($this->manifest($indexed));
        $remote  = $this->index($this->peer->filesManifest());
        $known   = array_map(static fn (array $r) => $r['sha256'], $indexed);
        $clock   = new ClockMonitor($this->db, $this->opt);

        foreach ($this->inOrder(array_unique(array_merge(array_keys($local), array_keys($remote))), $local, $remote, $known) as $path) {
            if ($this->ignored($path) || $this->resolve($path) === null) {
                continue; // never touch anything outside the directory
            }

            $l = $local[$path]['sha256'] ?? null;
            $r = $remote[$path]['sha256'] ?? null;
            $i = $known[$path] ?? null;
            $lm = $local[$path]['mtime'] ?? 0;
            $rm = $remote[$path]['mtime'] ?? 0;

            if ($l === $r) {
                $ix = $indexed[$path] ?? null;
                $ls = $local[$path]['size'] ?? 0;

                if ($ix === null || $ix['sha256'] !== $l || (int) $ix['mtime'] !== $lm || (int) $ix['size'] !== $ls) {
                    $this->remember($path, $l, $lm, $ls);
                }

                continue;
            }

            if ($l === $i) {
                $this->takeRemote($path, $r, $rm, $stat);

                continue;
            }

            if ($r === $i) {
                $this->offer($path, $l, $lm, $local[$path]['size'] ?? 0, $r, $stat); // our change: the peer pulls it, or we push it

                continue;
            }

            $stat['conflicts']++;

            if ($l === self::DIR || $r === self::DIR) {
                continue; // a directory against a file (or a change of type): never decided automatically
            }

            // mtime decides only when it is clearly different and the peer's clock can be trusted; otherwise the
            // tiebreak order does, which both nodes evaluate alike (the loser is kept as a .conflict- copy either way)
            $byTime     = $rm !== $lm && abs($rm - $lm) >= $this->opt->clockTolerance && ! $clock->unreliable($this->opt->peerNode, null, true);
            $remoteWins = $byTime ? $rm > $lm : $this->opt->rank($this->opt->peerNode) < $this->opt->rank($this->opt->node);

            if ($remoteWins) {
                $this->keepCopy($path);
                $this->takeRemote($path, $r, $rm, $stat);
            } else {
                $this->offer($path, $l, $lm, $local[$path]['size'] ?? 0, $r, $stat);
            }
        }

        // A path that exists on neither node any more needs no index entry (it would stay for ever otherwise).
        $gone = array_diff(array_keys($known), array_keys($local), array_keys($remote));

        if ($gone !== []) {
            $this->db->table('sync_files')->whereIn('path_hash', array_map('sha1', $gone))->delete();
        }

        return $stat;
    }

    /**
     * Files first, then the directories that exist on the peer (parents before children), then the directories the
     * peer no longer has (children before parents, so that an emptied directory can be removed in the same run).
     *
     * @param list<string>                                                $paths
     * @param array<string, array{sha256: string, mtime: int, size: int}> $local
     * @param array<string, array{sha256: string, mtime: int, size: int}> $remote
     * @param array<string, ?string>                                      $known
     *
     * @return list<string>
     */
    private function inOrder(array $paths, array $local, array $remote, array $known): array
    {
        $rank = static function (string $p) use ($local, $remote, $known): array {
            $isDir = ($local[$p]['sha256'] ?? null) === self::DIR || ($remote[$p]['sha256'] ?? null) === self::DIR || ($known[$p] ?? null) === self::DIR;

            if (! $isDir) {
                return [0, 0, $p];
            }

            $depth = substr_count($p, '/');

            $deleting = ! isset($local[$p]) && ($known[$p] ?? null) === self::DIR; // gone here, still there: removed deepest first

            return isset($remote[$p]) && ! $deleting ? [1, $depth, $p] : [2, -$depth, $p];
        };

        usort($paths, static fn (string $a, string $b): int => $rank($a) <=> $rank($b));

        return $paths;
    }

    /**
     * @param list<array{path: string, sha256: string, mtime: int, size: int}> $manifest
     *
     * @return array<string, array{sha256: string, mtime: int, size: int}>
     */
    private function index(array $manifest): array
    {
        $out = [];

        foreach ($manifest as $m) {
            $out[$m['path']] = ['sha256' => $m['sha256'], 'mtime' => (int) $m['mtime'], 'size' => (int) ($m['size'] ?? 0)];
        }

        return $out;
    }

    /**
     * The index of the directory as of the last agreement, by path.
     *
     * @return array<string, array<string, mixed>>
     */
    private function indexed(): array
    {
        $out = [];

        foreach ($this->db->table('sync_files')->get()->getResultArray() as $r) {
            $out[$r['path']] = $r;
        }

        return $out;
    }

    /**
     * @param array{downloaded: int, pushed: int, deleted: int, conflicts: int, kept: int, dirs: int} $stat
     */
    private function takeRemote(string $path, ?string $sha, int $mtime, array &$stat): void
    {
        $abs = $this->resolve($path);

        if ($abs === null) {
            return;
        }

        if ($sha === self::DIR) {
            if (is_file($abs)) {
                return; // a file here, a directory there
            }

            if (! is_dir($abs)) {
                mkdir($abs, 0775, true);
                $stat['dirs']++;
            }

            $this->remember($path, self::DIR, 0, 0);

            return;
        }

        if ($sha === null) {
            if (is_dir($abs) && ! is_link($abs)) {
                if (! @rmdir($abs)) {
                    return; // not empty (yet): its files are still being handled, or are ours; the index keeps the entry
                }

                $stat['dirs']++;
            } elseif (is_file($abs)) {
                unlink($abs);
                $stat['deleted']++;
            }

            $this->remember($path, null, 0, 0);

            return;
        }

        if (is_dir($abs)) {
            return; // a directory here, a file there
        }

        $bytes = $this->peer->fileGet($path);

        if ($bytes === null || hash('sha256', $bytes) !== $sha) {
            return; // changed or vanished meanwhile; next run
        }

        if (! is_dir(dirname($abs))) {
            mkdir(dirname($abs), 0775, true);
        }

        $tmp = $abs . '.part-' . bin2hex(random_bytes(4));
        file_put_contents($tmp, $bytes);
        rename($tmp, $abs);
        @touch($abs, $mtime);
        $this->remember($path, $sha, $mtime, strlen($bytes));
        $stat['downloaded']++;
    }

    /**
     * Our change is newer than the peer's. A node the peer can reach is pulled from; a passive node delivers it.
     *
     * @param array{downloaded: int, pushed: int, deleted: int, conflicts: int, kept: int, dirs: int} $stat
     */
    private function offer(string $path, ?string $sha, int $mtime, int $size, ?string $peerHas, array &$stat): void
    {
        if (! $this->opt->passive || ! $this->peer instanceof FilePushClient) {
            $stat['kept']++;

            return;
        }

        $kind    = $sha === null ? 'delete' : ($sha === self::DIR ? 'dir' : 'file');
        $content = '';

        if ($kind === 'file') {
            $abs     = $this->resolve($path);
            $content = $abs !== null && is_file($abs) && filesize($abs) <= self::MAX_PUSH ? (string) file_get_contents($abs) : null;

            if ($content === null || hash('sha256', $content) !== $sha) {
                $stat['kept']++; // too big, or changed while we looked: left for the next run

                return;
            }
        }

        try {
            $r = $this->peer->filePut($path, $kind, $peerHas, $sha, $mtime, $content);
        } catch (\Throwable) {
            $r = ['ok' => false];
        }

        if (! $r['ok']) {
            $stat['kept']++; // stale, not empty, or the hub is unreachable: decided again next run

            return;
        }

        $this->remember($path, $sha, $mtime, $size);
        $stat['pushed']++;
    }

    /**
     * The hub's side of `files/put`: applies one change delivered by a passive peer.
     *
     * @param array<string, mixed> $p path, kind (file|dir|delete), expect (what we must hold now, '' = nothing), sha256, mtime, content (base64)
     *
     * @return array{ok: bool, error?: string}
     */
    public function receive(array $p): array
    {
        $path = (string) ($p['path'] ?? '');
        $abs  = $this->resolve($path);
        $kind = (string) ($p['kind'] ?? '');

        if ($abs === null || $this->ignored($path) || str_contains($path, '.conflict-') || ! in_array($kind, ['file', 'dir', 'delete'], true)) {
            return ['ok' => false, 'error' => 'refused'];
        }

        if ($kind === 'dir' && is_dir($abs) && ! is_link($abs)) {
            return ['ok' => true]; // creating what exists (a file pushed earlier in the same run made its parents) is not stale
        }

        $now = is_link($abs) ? 'link' : (is_dir($abs) ? self::DIR : (is_file($abs) ? hash_file('sha256', $abs) : null));

        if ($now !== (($p['expect'] ?? '') === '' ? null : (string) $p['expect'])) {
            return ['ok' => false, 'error' => 'stale'];
        }

        if ($kind === 'dir') {
            return is_file($abs) || (! is_dir($abs) && ! @mkdir($abs, 0775, true)) ? ['ok' => false, 'error' => 'type clash'] : ['ok' => true];
        }

        if ($kind === 'delete') {
            if (is_dir($abs)) {
                return @rmdir($abs) ? ['ok' => true] : ['ok' => false, 'error' => 'not empty'];
            }

            return is_file($abs) && ! unlink($abs) ? ['ok' => false, 'error' => 'cannot delete'] : ['ok' => true];
        }

        $bytes = base64_decode((string) ($p['content'] ?? ''), true);

        if ($bytes === false || strlen($bytes) > self::MAX_PUSH || hash('sha256', $bytes) !== (string) ($p['sha256'] ?? '') || is_dir($abs)) {
            return ['ok' => false, 'error' => 'bad content'];
        }

        if (! is_dir(dirname($abs)) && ! @mkdir(dirname($abs), 0775, true)) {
            return ['ok' => false, 'error' => 'type clash'];
        }

        $tmp = $abs . '.part-' . bin2hex(random_bytes(4));
        file_put_contents($tmp, $bytes);
        rename($tmp, $abs);
        @touch($abs, (int) ($p['mtime'] ?? time()));

        return ['ok' => true];
    }

    private function keepCopy(string $path): void
    {
        $abs = $this->resolve($path);

        if ($abs !== null && is_file($abs)) {
            copy($abs, $abs . '.conflict-' . $this->opt->node . '-' . date('YmdHis'));
        }
    }

    private function remember(string $path, ?string $sha, int $mtime, int $size): void
    {
        $hash = sha1($path);
        $data = ['sha256' => $sha, 'mtime' => $mtime, 'size' => $size, 'seen_at' => Support::nowUs(), 'path' => $path];

        $this->db->table('sync_files')->where('path_hash', $hash)->update($data);

        if ($this->db->affectedRows() === 0 && $this->db->table('sync_files')->where('path_hash', $hash)->countAllResults() === 0) {
            $this->db->table('sync_files')->insert($data + ['path_hash' => $hash]);
        }
    }

    private function ignored(string $rel): bool
    {
        foreach ($this->opt->files['ignore'] ?? [] as $pattern) {
            if (fnmatch($pattern, $rel) || fnmatch($pattern, basename($rel))) {
                return true;
            }
        }

        return false;
    }

    /** No link on the way (a link inside the directory could lead out of it) and the real path stays under the base. */
    private function insideBase(string $abs): bool
    {
        $base = realpath($this->base);
        $real = realpath($abs);

        if ($base === false || $real === false) {
            return true; // nothing there: the caller answers "not found"
        }

        for ($p = $abs; strlen($p) > strlen($this->base); $p = dirname($p)) {
            if (is_link($p)) {
                return false;
            }
        }

        return str_starts_with($real, $base . DIRECTORY_SEPARATOR);
    }

    /** Absolute path inside the base dir, or null if the relative path would escape it. */
    private function resolve(string $rel): ?string
    {
        if ($this->base === '' || $rel === '' || str_contains($rel, "\0") || str_starts_with($rel, '/') || preg_match('~^[A-Za-z]:~', $rel)) {
            return null;
        }

        foreach (explode('/', str_replace('\\', '/', $rel)) as $part) {
            if ($part === '..' || $part === '') {
                return null;
            }
        }

        return $this->base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    }
}
