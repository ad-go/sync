<?php

declare(strict_types=1);

namespace AdGo\Sync\Libraries;

/**
 * Everything one node asks of the other. SyncClient implements it over signed
 * HTTPS (form-encoded requests, PHP-serialized responses); tests use fakes.
 */
interface PeerClient
{
    /**
     * @return array{changes: list<array<string, mixed>>, last_seq: int}
     */
    public function pull(int $afterSeq, int $limit): array;

    /**
     * @param array<string, mixed>|null $state
     *
     * @return array{ok: bool, error?: string}
     */
    public function resolveConflict(string $table, string $uuid, ?array $state, int $newRev, int $expectRev, int $ts, string $by): array;

    /** @return array{holder: ?string, heartbeat_at: int, acquired_at: int, ttl: int, now: int} */
    public function lockStatus(string $kind): array;

    /**
     * @param array<string, string> $meta mode / scope / by (what is being edited, by whom)
     *
     * @return array{ok: bool, holder: ?string, reason?: string}
     */
    public function lockAcquire(string $kind, string $requester, bool $confirm, array $meta = []): array;

    /**
     * @param array<string, string> $meta
     *
     * @return array{ok: bool}
     */
    public function lockHeartbeat(string $kind, string $holder, array $meta = []): array;

    /**
     * Optionally makes the peer pull our journal right now ($kick), and reports how far it has applied it.
     *
     * @return array{applied: int}
     */
    public function lockSync(string $from, bool $kick): array;

    /** @return array{ok: bool} */
    public function lockRelease(string $kind, string $holder): array;

    /** @return list<array{path: string, sha256: string, mtime: int, size: int}> */
    public function filesManifest(): array;

    /** Raw bytes of one file, or null if it does not exist any more. */
    public function fileGet(string $path): ?string;

    /**
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function bootstrapPage(string $table, int $offset, int $limit): array;
}
