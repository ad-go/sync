<?php

declare(strict_types=1);

namespace AdGo\Sync\Libraries;

/**
 * The outbound half of a passive node: pushes this node's journal to its hub. The hub tells how far it has
 * applied our journal (`auth-lock/sync`, the same `applied` the handover uses); we send the pages after that and
 * follow the position the hub reports back. Nothing is remembered locally: the hub's cursor is the truth, so a
 * run that dies half-way simply sends again (the Reconciler on the hub treats repeats as already seen).
 */
class Pusher
{
    public function __construct(
        private readonly Kernel $kernel,
        private readonly PushClient $hub,
    ) {
    }

    /**
     * @return array{pushed: int, applied: int, stopped: ?string}
     */
    public function run(): array
    {
        $meta  = $this->kernel->meta;
        $node  = $this->kernel->opt->node;
        $stats = ['pushed' => 0, 'applied' => 0, 'stopped' => null];
        $pos   = (int) ($this->hub->lockSync($node, false)['applied'] ?? 0);

        while (true) {
            $page = $meta->changesSince($pos, $this->kernel->opt->batch());

            if ($page === []) {
                break;
            }

            $r       = $this->hub->push($page);
            $applied = (int) ($r['applied'] ?? $pos);
            $stats['pushed'] += count(array_filter($page, static fn (array $c): bool => (int) $c['seq'] <= $applied));

            if ($applied <= $pos) {
                $stats['stopped'] = (string) ($r['stopped'] ?? 'the hub did not advance');
                break;
            }

            $pos = $applied;

            if (($r['stopped'] ?? null) !== null) {
                $stats['stopped'] = (string) $r['stopped'];
                break;
            }
        }

        $stats['applied'] = $pos;

        return $stats;
    }
}
