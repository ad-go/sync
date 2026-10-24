<?php

declare(strict_types=1);

namespace AdGo\Sync\Libraries;

use Closure;
use RuntimeException;

/**
 * This node's clock against NTP servers (SNTP, RFC 4330: one UDP packet each way), and the last result kept in a
 * small state file so that the rest of the package never waits for the network.
 *
 * offset = true time minus this node's clock: what has to be added to the local clock to get the real time.
 * It is measured here and, when `clockCorrect` is on, added to the timestamps the package writes itself
 * (`Support::eventUs()`); the system clock is never touched.
 */
final class NtpClock
{
    /** Seconds between 1900-01-01 (NTP) and 1970-01-01 (Unix). */
    private const EPOCH = 2_208_988_800;
    /** A stored measurement older than this is ignored: the clock may have moved since. */
    public const FRESH_S = 1800;
    /** An answer slower than this (seconds round trip) says too little about the offset. */
    public const MAX_RTT_S = 2.0;
    /** An offset beyond this (seconds) is more likely a broken server than a broken clock. */
    public const SANE_S = 86_400;

    private static ?int $memo   = null;
    private static float $memoAt = 0.0;

    /** @var Closure(string, string, float): string sends the request to a host, returns the 48-byte answer */
    private readonly Closure $send;

    public function __construct(private readonly ?string $file = null, ?Closure $send = null)
    {
        $this->send = $send ?? static function (string $host, string $packet, float $timeout): string {
            $s = @stream_socket_client("udp://{$host}:123", $errno, $err, $timeout);

            if ($s === false) {
                throw new RuntimeException($err !== '' ? $err : "cannot open a socket to {$host}");
            }

            stream_set_timeout($s, (int) ceil($timeout));
            fwrite($s, $packet);
            $answer = (string) fread($s, 48);
            fclose($s);

            return $answer;
        };
    }

    /**
     * One server. The request carries our send time as its transmit timestamp and the answer must echo it, so an
     * answer to somebody else's request (or a forged one) is refused.
     *
     * @return array{offset: float, rtt: float, stratum: int} offset in seconds
     */
    public function query(string $host, float $timeout = 2.0): array
    {
        $t1     = microtime(true);
        $stamp  = self::encode($t1);
        $answer = ($this->send)($host, "\x1B" . str_repeat("\0", 39) . $stamp, $timeout);
        $t4     = microtime(true);

        if (strlen($answer) < 48) {
            throw new RuntimeException("{$host} did not answer");
        }

        $mode    = ord($answer[0]) & 7;
        $stratum = ord($answer[1]);

        if ($mode !== 4 || $stratum < 1 || $stratum > 15) {
            throw new RuntimeException("{$host} is not a usable time server (mode {$mode}, stratum {$stratum})");
        }

        if (substr($answer, 24, 8) !== $stamp) {
            throw new RuntimeException("{$host} answered another request");
        }

        $t2 = self::decode(substr($answer, 32, 8));
        $t3 = self::decode(substr($answer, 40, 8));

        if ($t3 <= 0.0) {
            throw new RuntimeException("{$host} sent no time");
        }

        return ['offset' => (($t2 - $t1) + ($t3 - $t4)) / 2, 'rtt' => ($t4 - $t1) - ($t3 - $t2), 'stratum' => $stratum];
    }

    /**
     * Every server once; the median of the answers that came back fast enough (a few bad servers cannot move it).
     *
     * @param list<string> $servers
     *
     * @return array{offset: float|null, answers: int, servers: array<string, array{offset: float, rtt: float}|string>} offset null when none answered
     */
    public function measure(array $servers, float $timeout = 2.0): ?array
    {
        $report = [];
        $good   = [];

        foreach ($servers as $host) {
            try {
                $r = $this->query($host, $timeout);

                if ($r['rtt'] > self::MAX_RTT_S) {
                    throw new RuntimeException(sprintf('round trip %.1f s is too slow', $r['rtt']));
                }

                $report[$host] = ['offset' => $r['offset'], 'rtt' => $r['rtt']];
                $good[]        = $r['offset'];
            } catch (RuntimeException $e) {
                $report[$host] = $e->getMessage();
            }
        }

        if ($good === []) {
            return ['offset' => null, 'answers' => 0, 'servers' => $report];
        }

        sort($good);
        $n = count($good);

        return ['offset' => $n % 2 === 1 ? $good[intdiv($n, 2)] : ($good[$n / 2 - 1] + $good[$n / 2]) / 2, 'answers' => $n, 'servers' => $report];
    }

    public function save(float $offset, ?int $nowUs = null): void
    {
        file_put_contents($this->path(), (int) round($offset * 1_000_000) . '|' . ($nowUs ?? Support::nowUs()), LOCK_EX);
        self::$memo = null;
    }

    /** @return array{offset_s: float, age_s: int}|null null when never measured or too old */
    public function load(?int $nowUs = null): ?array
    {
        $raw = is_file($this->path()) ? explode('|', trim((string) file_get_contents($this->path()))) : [];

        if (count($raw) !== 2 || ! is_numeric($raw[0]) || ! is_numeric($raw[1])) {
            return null;
        }

        $age = intdiv(($nowUs ?? Support::nowUs()) - (int) $raw[1], 1_000_000);
        $s   = (int) $raw[0] / 1_000_000;

        return $age > self::FRESH_S || $age < -60 || abs($s) > self::SANE_S ? null : ['offset_s' => $s, 'age_s' => max(0, $age)];
    }

    /** Microseconds to add to the local clock; 0 when there is no fresh measurement. Memoised for 30 s per process. */
    public static function offsetUs(): int
    {
        $now = microtime(true);

        if (self::$memo === null || $now - self::$memoAt > 30.0) {
            $m          = (new self())->load();
            self::$memo   = $m === null ? 0 : (int) round($m['offset_s'] * 1_000_000);
            self::$memoAt = $now;
        }

        return self::$memo;
    }

    private function path(): string
    {
        return $this->file ?? ((defined('WRITEPATH') ? WRITEPATH : sys_get_temp_dir() . DIRECTORY_SEPARATOR) . 'sync-ntp.state');
    }

    /** Unix time (float) as an 8-byte NTP timestamp. */
    private static function encode(float $t): string
    {
        $s = (int) floor($t);

        return pack('NN', $s + self::EPOCH, (int) floor(($t - $s) * 4_294_967_296));
    }

    private static function decode(string $b): float
    {
        $p = unpack('Nsec/Nfrac', $b);

        return $p['sec'] === 0 ? 0.0 : $p['sec'] - self::EPOCH + $p['frac'] / 4_294_967_296;
    }
}
