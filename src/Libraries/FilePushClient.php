<?php

declare(strict_types=1);

namespace AdGo\Sync\Libraries;

/** A peer that accepts files pushed to it: the hub of a passive node, which the hub itself cannot reach. */
interface FilePushClient extends PeerClient
{
    /**
     * @param string  $kind    'file' | 'dir' | 'delete'
     * @param ?string $expect  what the peer holds at $path now (sha256, FileSync::DIR, or null for nothing); the peer refuses with 'stale' otherwise
     * @param string  $content raw bytes of a file
     *
     * @return array{ok: bool, error?: string}
     */
    public function filePut(string $path, string $kind, ?string $expect, ?string $sha, int $mtime, string $content): array;
}
