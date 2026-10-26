<?php

declare(strict_types=1);

namespace AdGo\Sync\Libraries;

/**
 * HMAC-SHA256 request/response signing with timestamp + nonce (anti-replay).
 *
 *   signature = HMAC(secret, METHOD "\n" path "\n" node "\n" timestamp "\n" nonce "\n" sha256(body))
 *
 * The sender's node name (`X-Sync-Node`) is part of the signed string, so a secret holder cannot
 * sign a request as one node and present it as another.
 */
final class Signer
{
    public static function sign(string $secret, string $method, string $path, string $node, int $timestamp, string $nonce, string $body): string
    {
        return hash_hmac('sha256', strtoupper($method) . "\n" . $path . "\n" . $node . "\n" . $timestamp . "\n" . $nonce . "\n" . hash('sha256', $body), $secret);
    }

    /**
     * @return array{ok: bool, reason?: string}
     */
    public static function verify(string $secret, string $method, string $path, string $node, int $timestamp, string $nonce, string $body, string $signature, int $window, ?int $now = null): array
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

        if ($node === '' || strlen($node) > 64 || str_contains($node, "\n")) {
            return ['ok' => false, 'reason' => 'bad node'];
        }

        if (! hash_equals(self::sign($secret, $method, $path, $node, $timestamp, $nonce, $body), $signature)) {
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
