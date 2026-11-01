<?php

declare(strict_types=1);

namespace AdGo\Sync\Libraries;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use AdGo\Sync\Config\Sync;

/**
 * Tiny service container for the package: wires Options, MetaStore and
 * RowGateway to a connection. Applications use `Kernel::instance()`; tests
 * build their own with `new Kernel($db, $options)`.
 */
final class Kernel
{
    private static ?self $shared = null;

    public readonly MetaStore $meta;
    public readonly RowGateway $rows;

    private ?PeerClient $peerOverride = null;

    /** The verified sender of the current peer request (set by the syncHmac filter), null outside such a request. */
    public ?string $requestPeer = null;

    public function __construct(
        public readonly BaseConnection $db,
        public readonly Options $opt,
    ) {
        Support::correctWith($opt->clockCorrect ? NtpClock::offsetUs(...) : null);
        $this->meta = new MetaStore($db, $opt);
        $this->rows = new RowGateway($db, $opt, $this->meta);
    }

    public static function instance(): self
    {
        if (self::$shared === null) {
            /** @var Sync $cfg */
            $cfg          = config('Sync');
            self::$shared = new self(Database::connect($cfg->dbGroup), Options::fromConfig($cfg));
        }

        return self::$shared;
    }

    /** For tests / long-running workers. */
    public static function use(?self $kernel): void
    {
        self::$shared = $kernel;
    }

    public function reconciler(?callable $onEscalated = null): Reconciler
    {
        return new Reconciler($this->db, $this->opt, $this->meta, $this->rows, $onEscalated);
    }

    /** The client of a peer we connect to: the primary peer by default (for a passive node: its hub). */
    public function peer(?string $name = null): PeerClient
    {
        return $this->peerOverride ?? new SyncClient($this->opt, $name, 30, new ClockMonitor($this->db, $this->opt));
    }

    /** The name of the peer the current request came from, else the primary peer. */
    public function sender(): string
    {
        return $this->requestPeer ?? $this->opt->peerNode;
    }

    /** Outbound half of a passive node. */
    public function pusher(): Pusher
    {
        $hub = $this->peer($this->opt->hub());

        if (! $hub instanceof PushClient) {
            throw new \RuntimeException('the peer client cannot push');
        }

        return new Pusher($this, $hub);
    }

    public function usePeer(?PeerClient $peer): void
    {
        $this->peerOverride = $peer;
    }

    private bool $kickPending    = false;
    private bool $kickRegistered = false;

    /**
     * A local change was journaled: tell the peer to pull it right away (once per request, after the response
     * has been sent), so its open pages can show it in seconds instead of waiting for the next scheduled pull.
     * Best effort: if the peer is unreachable the scheduled `sync:pull` delivers it anyway. Never runs in CLI.
     */
    public function markChanged(): void
    {
        $this->kickPending = true;

        if (! $this->kickRegistered && PHP_SAPI !== 'cli') {
            $this->kickRegistered = true;
            register_shutdown_function([$this, 'flushPeerKick']);
        }
    }

    public function flushPeerKick(): void
    {
        if (! $this->kickPending) {
            return;
        }

        $this->kickPending = false;

        try {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }

            if ($this->opt->passive) {
                // nobody can pull us: deliver the change ourselves (scan first: Shield writes are out of band)
                Support::exclusiveWait('push', function (): void {
                    $this->scanner()->scan();
                    $this->pusher()->run();
                }, 20);

                return;
            }

            foreach ($this->peerOverride !== null ? [$this->opt->peerNode] : $this->opt->activePeers() as $name) {
                $this->peer($name)->lockSync($this->opt->node, true);
            }
        } catch (\Throwable $e) {
            try {
                log_message('warning', 'sync: could not notify the peer: ' . $e->getMessage());
            } catch (\Throwable) {
                // logging must never break a request
            }
        }
    }

    public function telegram(): TelegramNotifier
    {
        return new TelegramNotifier($this->opt);
    }

    public function locks(): AuthLock
    {
        return new AuthLock($this->db, $this->opt, $this->peer(), $this->catchUpWithPeer(...), $this->publishLocalChanges(...));
    }

    /** After taking a lock from a reachable peer: pull its journal until nothing is left (bounded). */
    private function catchUpWithPeer(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $done = false;

            if (! Support::exclusiveWait('pull', function () use (&$done): void {
                $done = $this->puller()->run()['pulled'] === 0;
            }, 15)) {
                throw new \RuntimeException('another sync is running');
            }

            if ($done) {
                return;
            }
        }
    }

    /** Before a handover: put out-of-band local changes (Shield writes) into the journal. */
    private function publishLocalChanges(): void
    {
        if (! Support::exclusiveWait('pull', fn () => $this->scanner()->scan(), 15)) {
            throw new \RuntimeException('another sync is running');
        }
    }

    /** Escalated conflicts are pushed to Telegram when it is configured. */
    public function puller(): Puller
    {
        $tg = $this->telegram();

        return new Puller($this, $this->peer(), static function (array $conflict) use ($tg): void {
            $tg->conflict($conflict);
        });
    }

    public function resolver(?PeerClient $peer = null): ConflictResolver
    {
        return new ConflictResolver($this->db, $this->opt, $this->meta, $this->reconciler(), $peer ?? $this->peer());
    }

    public function reachability(): Reachability
    {
        return new Reachability($this->opt, $this->db);
    }

    public function files(): FileSync
    {
        return new FileSync($this->db, $this->opt, $this->peer());
    }

    public function scanner(): ChangeScanner
    {
        return new ChangeScanner($this->db, $this->opt, $this->meta, $this->rows);
    }
}
