<?php

declare(strict_types=1);

namespace AdGo\Sync\Libraries;

use CodeIgniter\Database\BaseConnection;

/**
 * Validation, generation and database bookkeeping for the node names and the keys (`sync.node`, `sync.peerNode`,
 * `sync.secret`, `encryption.key`).
 */
class NodeIdentity
{
    public const NODE_PATTERN    = '/^[a-z0-9][a-z0-9_-]{0,31}$/';
    public const SECRET_PATTERN  = '/^[A-Za-z0-9]{32,128}$/';
    public const ENCKEY_PATTERNS = ['/^hex2bin:[0-9a-f]{64}$/', '/^base64:[A-Za-z0-9+\/]{43}=$/'];

    /** A new shared secret for the synchronisation: 48 random bytes as hex. */
    public static function newSecret(): string
    {
        return bin2hex(random_bytes(48));
    }

    /** A new encryption key in the format CodeIgniter reads: 32 random bytes. */
    public static function newEncryptionKey(): string
    {
        return 'hex2bin:' . bin2hex(random_bytes(32));
    }

    public static function validEncryptionKey(string $v): bool
    {
        foreach (self::ENCKEY_PATTERNS as $p) {
            if (preg_match($p, $v) === 1) {
                return true;
            }
        }

        return false;
    }

    /** Short fingerprint of a secret: lets the administrators compare the two nodes without revealing anything. */
    public static function fingerprint(?string $secret): string
    {
        return $secret === null || $secret === '' ? '(not set)' : substr(hash('sha256', $secret), 0, 10);
    }

    /**
     * After a node was renamed, the names stored in the package's own tables follow it, otherwise the journal
     * cursor would be lost (a full re-pull), lock holders would name a node that no longer exists and ties in
     * conflicts would be broken in favour of an unknown name. A swap (a→b, b→a) is handled through a placeholder.
     *
     * @param array<string, string> $map old name => new name
     */
    public static function migrateNames(BaseConnection $db, array $map): void
    {
        $map = array_filter($map, static fn ($new, $old) => $new !== $old, ARRAY_FILTER_USE_BOTH);

        if ($map === []) {
            return;
        }

        $columns = [
            ['sync_cursor', 'peer_node'], ['auth_write_lock', 'holder'], ['sync_row_meta', 'node_origin'],
            ['sync_changes', 'node_origin'], ['sync_conflicts', 'local_node'], ['sync_conflicts', 'remote_node'],
        ];

        $db->transStart();

        foreach ([1, 2] as $step) {
            foreach ($map as $old => $new) {
                foreach ($columns as [$table, $col]) {
                    $step === 1
                        ? $db->table($table)->where($col, $old)->update([$col => '~' . $new])
                        : $db->table($table)->where($col, '~' . $new)->update([$col => $new]);
                }
            }
        }

        $db->transComplete();
    }
}
