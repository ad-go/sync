<?php

declare(strict_types=1);

namespace AdGo\Sync\Libraries;

use Closure;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

/**
 * Small helpers shared by the core classes: time with microseconds,
 * UUIDs and the non-JSON wire/storage codec (PHP serialize()).
 */
final class Support
{
    private static ?int $fixedNow = null;
    private static ?Closure $correction = null;

    /**
     * Runs `$fn` only if no other process holds the named lock (atomic, non-blocking flock; released by the
     * OS even if the process dies). Returns false without running `$fn` when the lock is busy.
     * Guards the scheduled jobs against overlapping runs (two cron entries, cron + HTTP tick, a slow run...).
     */
    public static function exclusive(string $name, Closure $fn): bool
    {
        $dir = (defined('WRITEPATH') ? WRITEPATH : sys_get_temp_dir() . DIRECTORY_SEPARATOR) . 'sync-locks';

        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $h = @fopen($dir . DIRECTORY_SEPARATOR . preg_replace('/[^a-z0-9_-]/i', '_', $name) . '.lock', 'c');

        if ($h === false || ! flock($h, LOCK_EX | LOCK_NB)) {
            if (is_resource($h)) {
                fclose($h);
            }

            return false;
        }

        try {
            $fn();
        } finally {
            flock($h, LOCK_UN);
            fclose($h);
        }

        return true;
    }

    /**
     * Like exclusive(), but waits up to `$seconds` for the lock. Returns false if it never became free.
     */
    public static function exclusiveWait(string $name, Closure $fn, int $seconds): bool
    {
        $deadline = microtime(true) + $seconds;

        do {
            if (self::exclusive($name, $fn)) {
                return true;
            }

            usleep(250_000);
        } while (microtime(true) < $deadline);

        return false;
    }

    /** Microseconds since the Unix epoch. Stored as BIGINT (portable on MySQL / PostgreSQL / SQLite). */
    public static function nowUs(): int
    {
        return self::$fixedNow ?? (int) round(microtime(true) * 1_000_000);
    }

    /**
     * Time stamped on the changes this node records (`updated_at`, journal `changed_at`): the local clock plus the
     * NTP offset when `clockCorrect` is on, so that "newest wins" compares real times, not two machines' clocks.
     */
    public static function eventUs(): int
    {
        return self::nowUs() + self::correctionUs();
    }

    /** Microseconds added to the local clock for event times; 0 when the correction is off. */
    public static function correctionUs(): int
    {
        return self::$correction === null ? 0 : (self::$correction)();
    }

    /** The correction in seconds, or null when it is off: what a node tells its peers about its own stamps. */
    public static function correctionS(): ?float
    {
        return self::$correction === null ? null : self::correctionUs() / 1_000_000;
    }

    /** Switches the correction on (a function that returns the offset in microseconds) or off (null). */
    public static function correctWith(?Closure $offsetUs): void
    {
        self::$correction = $offsetUs;
    }

    /** For tests: freeze (int) or release (null) the clock. */
    public static function freezeNow(?int $us): void
    {
        self::$fixedNow = $us;
    }

    /**
     * Runs $fn in a transaction (nesting-safe) and returns its result.
     *
     * @template T
     *
     * @param Closure(): T $fn
     *
     * @return T
     */
    public static function tx(BaseConnection $db, Closure $fn): mixed
    {
        $db->transBegin();

        try {
            $result = $fn();
        } catch (Throwable $e) {
            $db->transRollback();

            throw $e;
        }

        if ($db->transStatus() === false) {
            $db->transRollback();

            throw new RuntimeException('Database transaction failed.');
        }

        $db->transCommit();

        return $result;
    }

    public static function uuid4(): string
    {
        $b    = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0F) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3F) | 0x80);
        $h    = bin2hex($b);

        return sprintf('%s-%s-%s-%s-%s', substr($h, 0, 8), substr($h, 8, 4), substr($h, 12, 4), substr($h, 16, 4), substr($h, 20, 12));
    }

    /** Deterministic UUID (v5, SHA-1) so pre-existing rows get the SAME uuid on both nodes. */
    public static function uuid5(string $name, string $namespace = '6ba7b810-9dad-11d1-80b4-00c04fd430c8'): string
    {
        $ns = hex2bin(str_replace('-', '', $namespace));
        $h  = sha1($ns . $name);
        $h  = substr($h, 0, 12) . '5' . substr($h, 13, 3) . dechex((hexdec($h[16]) & 0x3) | 0x8) . substr($h, 17, 15);

        return sprintf('%s-%s-%s-%s-%s', substr($h, 0, 8), substr($h, 8, 4), substr($h, 12, 4), substr($h, 16, 4), substr($h, 20, 12));
    }

    /** UUID used for rows that already exist when the package is installed. */
    public static function seedUuid(string $table, string|int $pk): string
    {
        // The salt is frozen: changing it would give already seeded rows different uuids on different nodes.
        return self::uuid5('q1-sync|' . $table . '|' . $pk);
    }

    /** serialize() for storage / transport. Never JSON. */
    public static function pack(mixed $value): string
    {
        return serialize($value);
    }

    /**
     * Safe unserialize(): no objects are ever instantiated.
     */
    public static function unpack(?string $blob): mixed
    {
        if ($blob === null || $blob === '') {
            return null;
        }

        $value = @unserialize($blob, ['allowed_classes' => false]);

        if ($value === false && $blob !== serialize(false)) {
            throw new RuntimeException('Corrupt sync payload.');
        }

        return $value;
    }

    /**
     * Stable fingerprint of a row, independent of key order and of
     * int/string typing differences between drivers.
     *
     * @param array<string, mixed>|null $row
     */
    public static function hashRow(?array $row): ?string
    {
        if ($row === null) {
            return null;
        }

        return sha1(serialize(self::normalize($row)));
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, string|null>
     */
    public static function normalize(array $row): array
    {
        ksort($row);

        $out = [];

        foreach ($row as $k => $v) {
            $out[(string) $k] = $v === null ? null : (is_bool($v) ? ($v ? '1' : '0') : (string) $v);
        }

        return $out;
    }

    public static function same(mixed $a, mixed $b): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }

        return (string) (is_bool($a) ? (int) $a : $a) === (string) (is_bool($b) ? (int) $b : $b);
    }
}
