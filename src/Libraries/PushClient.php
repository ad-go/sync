<?php

declare(strict_types=1);

namespace AdGo\Sync\Libraries;

/** A peer we can push our journal to (a passive node talking to its hub). */
interface PushClient extends PeerClient
{
    /**
     * Sends a page of our journal (the shape `pull()` returns) to the peer, which applies it and records how far
     * it got.
     *
     * @param list<array<string, mixed>> $changes
     *
     * @return array{applied: int, stopped: ?string}
     */
    public function push(array $changes): array;
}
