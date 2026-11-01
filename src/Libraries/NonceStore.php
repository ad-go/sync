<?php

declare(strict_types=1);

namespace AdGo\Sync\Libraries;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

/**
 * Remembers the nonces of accepted requests so that each one is honoured once.
 *
 * The database is the primary store: `INSERT IGNORE` on the primary key is atomic, so two identical requests
 * arriving together cannot both pass. Without the table (a node that was not migrated yet) the application cache is
 * used, and only if it demonstrably stores values. When neither works the request must be refused: a window of
 * `replayWindow` seconds on its own is not replay protection.
 */
final class NonceStore
{
    public function __construct(private readonly BaseConnection $db, private readonly int $ttl)
    {
    }

    /**
     * @return bool true = first use, false = replay
     *
     * @throws RuntimeException when no store is usable
     */
    public function claim(string $nonce, ?int $now = null): bool
    {
        $now  = $now ?? time();
        $hash = hash('sha256', $nonce);

        try {
            $this->db->table('sync_nonce')->ignore(true)->insert(['nonce_hash' => $hash, 'seen_at' => $now]);
            $fresh = $this->db->affectedRows() > 0;

            if ($fresh && random_int(1, 50) === 1) {
                $this->purge($now);
            }

            return $fresh;
        } catch (Throwable) {
            // not migrated yet (or the database refused): the cache is the fallback
        }

        return $this->viaCache($hash);
    }

    public function purge(?int $now = null): int
    {
        $this->db->table('sync_nonce')->where('seen_at <', ($now ?? time()) - $this->ttl)->delete();

        return $this->db->affectedRows();
    }

    private function viaCache(string $hash): bool
    {
        $key = 'sync_nonce_' . $hash;

        try {
            if (cache($key) !== null) {
                return false;
            }

            cache()->save($key, 1, $this->ttl);

            if (cache($key) === null) {
                throw new RuntimeException('the cache does not keep values');
            }
        } catch (Throwable $e) {
            throw new RuntimeException('no nonce store available: ' . $e->getMessage(), 0, $e);
        }

        return true;
    }
}
