<?php

declare(strict_types=1);

namespace AdGo\Sync\Libraries;

use InvalidArgumentException;
use AdGo\Sync\Config\Sync;

/**
 * Immutable, framework-independent view of the Sync config. The core classes
 * take this instead of the Config object so they can be used (and tested)
 * with nothing but a database connection.
 *
 * Topology. The classic set-up is two nodes that reach each other (`peerNode` / `peerUrl`). A node may also
 * have several peers (`peers`), and a peer may be *passive*: it sits behind NAT, nobody can connect to it, so it
 * initiates everything itself — it pulls our journal and PUSHES its own (`sync-api/push`). A passive node has one
 * reachable peer, its hub. A hub that should forward what it receives to its other peers sets `relay`.
 */
final class Options
{
    /** Tables that are strictly per node and can never be synchronised. */
    public const DEFAULT_EXCLUDED = [
        'queue_jobs',
        'queue_jobs_failed',
        'auth_logins',
        'auth_token_logins',
        'auth_remember_tokens',
        'ci_sessions',
        'tasks_log',
        'migrations',
    ];

    /**
     * Ready-made `$tables` entries for Shield's users and groups (no schema change; ids stay local, links go
     * through uuids). Login bookkeeping (`last_active`, `last_used_at`, `updated_at`) is ignored, otherwise every
     * login would become a change. Any overlapping edit of the same user is escalated, never overwritten silently.
     * Use together with the `auth` write lock so that only one node edits users at a time:
     *
     *     public array $tables = [ ...your tables... ] + Options::SHIELD_USERS;
     *
     * Rows that already exist on both nodes must be adopted with `spark sync:install` (deterministic uuids)
     * BEFORE the first `sync:pull`/scan, otherwise every existing user would be journaled as a new one.
     */
    public const SHIELD_USERS = [
        'users' => [
            'policy'        => 'sensitive',
            'ignore_fields' => ['last_active', 'updated_at'],
        ],
        'auth_identities' => [
            'policy'        => 'sensitive',
            'ignore_fields' => ['last_used_at', 'updated_at'],
            'fk'            => ['user_id' => 'users'],
        ],
        'auth_groups_users' => [
            'policy' => 'sensitive',
            'fk'     => ['user_id' => 'users'],
        ],
    ];

    /**
     * @param array<string, array<string, mixed>> $tables
     * @param list<string>                        $tiebreak
     * @param list<string>                        $excluded
     * @param array{dir: string, ignore: list<string>} $files
     * @param array<string, string>               $telegram
     * @param array<string, array{url: string, passive: bool}> $peers every peer by name
     */
    public function __construct(
        public readonly string $node,
        public readonly string $peerNode,
        public readonly string $peerUrl,
        public readonly string $routePrefix,
        public readonly string $secret,
        public readonly array $tiebreak,
        public readonly array $tables,
        public readonly array $excluded,
        public readonly array $bootstrapTables,
        public readonly int $lockTtl,
        public readonly int $leaseTtl,
        public readonly int $keepJournalDays,
        public readonly int $keepConflictDays,
        public readonly int $pullBatch,
        public readonly int $replayWindow,
        public readonly array $files,
        public readonly array $telegram,
        public readonly array $peers = [],
        public readonly bool $passive = false,
        public readonly bool $relay = false,
        public readonly string $selfUrl = '',
        public readonly array $mail = [],
        public readonly bool $allowInsecure = false,
        public readonly int $maxBody = 8 * 1024 * 1024,
        public readonly int $maxPushChanges = self::MAX_BATCH,
        public readonly float $clockWarn = 2.0,
        public readonly float $clockMax = 5.0,
        public readonly float $clockTolerance = 2.0,
    ) {
        foreach (array_keys($tables) as $t) {
            if (in_array($t, $excluded, true)) {
                throw new InvalidArgumentException("Table '{$t}' is in excludedTables and can never be synchronised.");
            }
        }
    }

    public static function fromConfig(Sync $c): self
    {
        return self::fromArray(get_object_vars($c));
    }

    /**
     * @param array<string, mixed> $a
     */
    public static function fromArray(array $a): self
    {
        $node    = (string) ($a['node'] ?? 'site1');
        $peer    = (string) ($a['peerNode'] ?? 'site2');
        $peerUrl = rtrim((string) ($a['peerUrl'] ?? ''), '/');

        // Peers: the classic single peer, or the `peers` list (which may also name the classic one).
        $peers = [];

        foreach ((array) ($a['peers'] ?? []) as $name => $p) {
            $name = (string) $name;

            if ($name === '' || $name === $node) {
                continue;
            }

            $peers[$name] = ['url' => rtrim((string) (is_array($p) ? ($p['url'] ?? '') : $p), '/'), 'passive' => is_array($p) && ! empty($p['passive'])];
        }

        if ($peers === [] && $peer !== '') {
            $peers[$peer] = ['url' => $peerUrl, 'passive' => false];
        }

        // The classic single peer is the primary one: the first reachable peer (locks, conflicts, bootstrap).
        if (! isset($peers[$peer])) {
            foreach ($peers as $name => $p) {
                if ($p['url'] !== '') {
                    $peer    = $name;
                    $peerUrl = $p['url'];
                    break;
                }
            }
        } elseif ($peerUrl === '') {
            $peerUrl = $peers[$peer]['url'];
        }

        // The tie-break order must name both nodes and be the same on both of them. A list that does not (e.g. the
        // default site1/site2 after the nodes were renamed) is replaced by the two names in alphabetical order,
        // which each node computes identically on its own.
        $tiebreak = $a['tiebreak'] ?? [];

        if (! in_array($node, $tiebreak, true) || ! in_array($peer, $tiebreak, true)) {
            $tiebreak = [$node, $peer];
            sort($tiebreak, SORT_STRING);
        }

        return new self(
            node: $node,
            peerNode: $peer,
            peerUrl: $peerUrl,
            routePrefix: trim((string) ($a['routePrefix'] ?? 'sync-api'), '/'),
            secret: (string) ($a['secret'] ?? ''),
            tiebreak: $tiebreak,
            tables: $a['tables'] ?? [],
            excluded: $a['excludedTables'] ?? self::DEFAULT_EXCLUDED,
            bootstrapTables: $a['bootstrapTables'] ?? [],
            lockTtl: (int) ($a['lockTtl'] ?? 120),
            leaseTtl: (int) ($a['leaseTtl'] ?? 300),
            keepJournalDays: (int) ($a['keepJournalDays'] ?? 30),
            keepConflictDays: (int) ($a['keepConflictDays'] ?? 90),
            pullBatch: (int) ($a['pullBatch'] ?? 200),
            replayWindow: (int) ($a['replayWindow'] ?? 300),
            files: $a['files'] ?? ['dir' => '', 'ignore' => []],
            telegram: $a['telegram'] ?? [],
            peers: $peers,
            passive: ! empty($a['passive']),
            relay: ! empty($a['relay']),
            selfUrl: rtrim((string) ($a['selfUrl'] ?? ''), '/'),
            mail: (array) ($a['mail'] ?? []),
            allowInsecure: ! empty($a['allowInsecure']),
            maxBody: (int) ($a['maxBody'] ?? 8 * 1024 * 1024),
            maxPushChanges: max(1, (int) ($a['maxPushChanges'] ?? self::MAX_BATCH)),
            clockWarn: max(0.0, (float) ($a['clockWarn'] ?? 2.0)),
            clockMax: max(0.0, (float) ($a['clockMax'] ?? 5.0)),
            clockTolerance: max(0.0, (float) ($a['clockTolerance'] ?? 2.0)),
        );
    }

    /** Largest number of journal entries in one pull answer or one push request. */
    public const MAX_BATCH = 1000;

    /** Journal entries per round trip, never more than the receiver accepts. */
    public function batch(): int
    {
        return max(1, min($this->pullBatch, self::MAX_BATCH));
    }

    /** Plain HTTP is only for this machine; anything else needs `allowInsecure`. */
    public function transportAllowed(string $url): bool
    {
        if ($this->allowInsecure || str_starts_with(strtolower($url), 'https://')) {
            return true;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return in_array($host, ['localhost', '127.0.0.1', '[::1]', '::1'], true) || str_ends_with($host, '.localhost');
    }

    /**
     * Telegram user ids allowed to press the decision buttons; empty = every member of the configured chat.
     *
     * @return list<string>
     */
    public function telegramUsers(): array
    {
        $v = $this->telegram['allowed_users'] ?? [];
        $v = is_array($v) ? $v : explode(',', (string) $v);

        return array_values(array_filter(array_map(static fn ($x): string => trim((string) $x), $v), static fn (string $x): bool => $x !== ''));
    }

    /** @return array<string, mixed>|null */
    public function table(string $name): ?array
    {
        if (! isset($this->tables[$name])) {
            return null;
        }

        return $this->tables[$name] + [
            'table'            => $name, // the physical table; the key is the name used on the wire and in the journal
            'pk'               => 'id',
            'policy'           => 'sensitive',
            'sensitive_fields' => [],
            'ignore_fields'    => [],
            'where'            => [],
            'fk'               => [],
            'scan'             => true,
        ];
    }

    /** Rank in the tie-break list; lower wins. Unknown nodes lose. */
    public function rank(string $node): int
    {
        $i = array_search($node, $this->tiebreak, true);

        return $i === false ? PHP_INT_MAX : (int) $i;
    }

    /** @return list<string> every configured peer name (what the HMAC filter accepts as sender) */
    public function peerNames(): array
    {
        return array_keys($this->peers);
    }

    public function isPeer(string $name): bool
    {
        return isset($this->peers[$name]);
    }

    /** A passive peer cannot be reached: it pulls and pushes by itself; we never connect to it. */
    public function isPassivePeer(string $name): bool
    {
        return ! empty($this->peers[$name]['passive']);
    }

    /** The URL of a peer we connect to ('' for a passive peer or an unknown name). */
    public function urlOf(string $name): string
    {
        return $this->isPassivePeer($name) ? '' : (string) ($this->peers[$name]['url'] ?? '');
    }

    /** @return list<string> peers this node connects to (pulls from, kicks, asks for locks) */
    public function activePeers(): array
    {
        $out = [];

        foreach ($this->peers as $name => $p) {
            if (! $p['passive'] && $p['url'] !== '') {
                $out[] = $name;
            }
        }

        return $out;
    }

    /** The one reachable peer of a passive node (the hub) — the primary peer. */
    public function hub(): string
    {
        return $this->peerNode;
    }
}
