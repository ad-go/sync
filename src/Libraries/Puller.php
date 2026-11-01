<?php

declare(strict_types=1);

namespace AdGo\Sync\Libraries;

/**
 * Pulls the peers' change journals from our cursors and feeds them through the Reconciler. One cursor per peer
 * (sync_cursor). The cursor is saved every CHECKPOINT changes, at the end of a page and whenever the run stops
 * (a change whose parent row has not arrived yet, an exception), so the next run resumes right there. If the
 * process dies hard the worst case is that up to CHECKPOINT changes are applied again, which the Reconciler treats
 * as already seen (revisions are compared). A passive peer is never pulled: it pushes (applyBatch).
 */
class Puller
{
    private const CHECKPOINT = 50;

    public function __construct(
        private readonly Kernel $kernel,
        private readonly ?PeerClient $peer = null,
        private $onEscalated = null,
    ) {
    }

    /**
     * Pulls one peer (by name) or every reachable peer.
     *
     * @return array{pulled: int, outcomes: array<string, int>, stopped: ?string}
     */
    public function run(?string $peerName = null): array
    {
        $names = $peerName !== null ? [$peerName] : ($this->peer !== null ? [$this->kernel->opt->peerNode] : $this->kernel->opt->activePeers());
        $stats = ['pulled' => 0, 'outcomes' => [], 'stopped' => null];

        foreach ($names as $name) {
            $r = $this->pullPeer($name, $this->peer ?? $this->kernel->peer($name));
            $stats['pulled'] += $r['pulled'];

            $this->mergeOutcomes($stats['outcomes'], $r['outcomes']);

            if ($r['stopped'] !== null) {
                $stats['stopped'] = ($stats['stopped'] === null ? '' : $stats['stopped'] . '; ') . $name . ': ' . $r['stopped'];
            }
        }

        return $stats;
    }

    /**
     * @return array{pulled: int, outcomes: array<string, int>, stopped: ?string}
     */
    private function pullPeer(string $peer, PeerClient $client): array
    {
        $meta  = $this->kernel->meta;
        $stats = ['pulled' => 0, 'outcomes' => [], 'stopped' => null];
        $cur   = $meta->cursor($peer);

        while (true) {
            $page = $client->pull($cur, $this->kernel->opt->batch());

            if ($page['changes'] === []) {
                break;
            }

            $r = $this->applyBatch($peer, $page['changes']);
            $stats['pulled'] += $r['pulled'];

            $this->mergeOutcomes($stats['outcomes'], $r['outcomes']);

            if ($r['stopped'] !== null) {
                $stats['stopped'] = $r['stopped'];
                break;
            }

            $cur = $r['cursor'];
        }

        return $stats;
    }

    /**
     * @param array<string, int> $into
     * @param array<string, int> $add
     */
    private function mergeOutcomes(array &$into, array $add): void
    {
        foreach ($add as $o => $n) {
            $into[$o] = ($into[$o] ?? 0) + $n;
        }
    }

    /**
     * Applies a page of a peer's journal (pulled, or pushed by a passive peer) and advances that peer's cursor.
     * Entries at or before the cursor are skipped (a resend after a failure).
     *
     * @param list<array<string, mixed>> $changes
     *
     * @return array{pulled: int, outcomes: array<string, int>, stopped: ?string, cursor: int}
     */
    public function applyBatch(string $peer, array $changes): array
    {
        $meta  = $this->kernel->meta;
        $rec   = $this->kernel->reconciler($this->onEscalated);
        $cur   = $meta->cursor($peer);
        $saved = $cur;
        $stats = ['pulled' => 0, 'outcomes' => [], 'stopped' => null, 'cursor' => $cur];

        try {
            foreach ($changes as $change) {
                if ((int) ($change['seq'] ?? 0) <= $cur) {
                    continue;
                }

                try {
                    $res = $rec->apply($change);
                } catch (MissingParent $e) {
                    $stats['stopped'] = $e->getMessage();
                    break;
                }

                $cur = (int) $change['seq'];
                $stats['pulled']++;
                $stats['outcomes'][$res['outcome']] = ($stats['outcomes'][$res['outcome']] ?? 0) + 1;

                if ($stats['pulled'] % self::CHECKPOINT === 0) {
                    $meta->setCursor($peer, $cur);
                    $saved = $cur;
                }
            }
        } finally {
            if ($cur !== $saved) {
                $meta->setCursor($peer, $cur);
            }
        }

        $stats['cursor'] = $cur;

        return $stats;
    }
}
