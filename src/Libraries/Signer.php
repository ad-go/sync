<?php

declare(strict_types=1);

namespace AdGo\Sync\Libraries;

/**
 * HMAC-SHA256 request/response signing with timestamp + nonce (anti-replay).
 *
 *   signature = HMAC(secret, METHOD "\n" path "\n" timestamp "\n" nonce "\n" sha256(body))
 */
final class Signer
{
    public static function sign(string $secret, string $method, string $path, int $timestamp, string $nonce, string $body): string
    {
        return hash_hmac('sha256', strtoupper($method) . "\n" . $path . "\n" . $timestamp . "\n" . $nonce . "\n" . hash('sha256', $body), $secret);
    }

    /**
     * @return array{ok: bool, reason?: string}
     */
    public static function verify(string $secret, string $method, string $path, int $timestamp, string $nonce, string $body, string $signature, int $window, ?int $now = null): array
    {
        if ($secret === '') {
            return ['ok' => false, 'reason' => 'secret not configured'];
        }

        if (abs(($now ?? time()) - $timestamp) > $window) {
            return ['ok' => false, 'reason' => 'timestamp outside the replay window'];
        }

        if ($nonce === '' || strlen($nonce) > 64) {
            return ['ok' => false, 'reason' => 'bad nonce'];
        }

        if (! hash_equals(self::sign($secret, $method, $path, $timestamp, $nonce, $body), $signature)) {
            return ['ok' => false, 'reason' => 'bad signature'];
        }

        return ['ok' => true];
    }

    /** Response signature: binds the body to the request nonce so it cannot be replayed for another request. */
    public static function signResponse(string $secret, string $requestNonce, string $body): string
    {
        return hash_hmac('sha256', 'response' . "\n" . $requestNonce . "\n" . hash('sha256', $body), $secret);
    }
}
