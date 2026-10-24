<?php

declare(strict_types=1);

namespace AdGo\Sync\Libraries;

use Config\Services;
use RuntimeException;

/**
 * HTTPS client for the peer's sync API. Requests are form-encoded and signed
 * (see Signer); responses are PHP-serialized and signed. No JSON.
 */
class SyncClient implements PushClient, FilePushClient
{
    private readonly string $peerName;
    private readonly string $url;

    /** @param string|null $peer the peer to talk to; null = the primary peer (`peerNode` / `peerUrl`) */
    public function __construct(private readonly Options $opt, ?string $peer = null, private readonly int $timeout = 30, private readonly ?ClockMonitor $clock = null)
    {
        $this->peerName = $peer ?? $opt->peerNode;
        $this->url      = $peer === null || $peer === $opt->peerNode ? $opt->peerUrl : $opt->urlOf($peer);
    }

    public function push(array $changes): array
    {
        $r = $this->call('push', ['changes' => Support::pack($changes)]);

        return ['applied' => (int) ($r['applied'] ?? 0), 'stopped' => isset($r['stopped']) ? (string) $r['stopped'] : null];
    }

    public function pull(int $afterSeq, int $limit): array
    {
        $r = $this->call('pull', ['after' => $afterSeq, 'limit' => $limit]);

        return ['changes' => $r['changes'] ?? [], 'last_seq' => (int) ($r['last_seq'] ?? $afterSeq)];
    }

    public function resolveConflict(string $table, string $uuid, ?array $state, int $newRev, int $expectRev, int $ts, string $by): array
    {
        return $this->call('conflict/resolve', [
            'table' => $table, 'uuid' => $uuid, 'state' => Support::pack($state),
            'new_rev' => $newRev, 'expect_rev' => $expectRev, 'ts' => $ts, 'by' => $by,
        ]);
    }

    public function lockStatus(string $kind): array
    {
        return $this->call('auth-lock/status', ['kind' => $kind]);
    }

    public function lockAcquire(string $kind, string $requester, bool $confirm, array $meta = []): array
    {
        return $this->call('auth-lock/acquire', ['kind' => $kind, 'requester' => $requester, 'confirm' => $confirm ? 1 : 0] + $this->metaFields($meta));
    }

    public function lockHeartbeat(string $kind, string $holder, array $meta = []): array
    {
        return $this->call('auth-lock/heartbeat', ['kind' => $kind, 'holder' => $holder] + $this->metaFields($meta));
    }

    public function lockSync(string $from, bool $kick): array
    {
        return $this->call('auth-lock/sync', ['from' => $from, 'kick' => $kick ? 1 : 0]);
    }

    /**
     * @param array<string, string> $meta
     *
     * @return array<string, string>
     */
    private function metaFields(array $meta): array
    {
        $out = [];

        foreach (['mode', 'scope', 'by'] as $k) {
            if (isset($meta[$k])) {
                $out['meta_' . $k] = (string) $meta[$k];
            }
        }

        return $out;
    }

    public function lockRelease(string $kind, string $holder): array
    {
        return $this->call('auth-lock/release', ['kind' => $kind, 'holder' => $holder]);
    }

    public function filesManifest(): array
    {
        return $this->call('files/manifest', [])['files'] ?? [];
    }

    public function fileGet(string $path): ?string
    {
        $r = $this->call('files/get', ['path' => $path]);

        return ($r['found'] ?? false) ? (string) $r['content'] : null;
    }

    public function filePut(string $path, string $kind, ?string $expect, ?string $sha, int $mtime, string $content): array
    {
        $r = $this->call('files/put', [
            'path' => $path, 'kind' => $kind, 'expect' => $expect ?? '', 'sha256' => $sha ?? '', 'mtime' => $mtime, 'content' => base64_encode($content),
        ]);

        return ['ok' => (bool) ($r['ok'] ?? false)] + (isset($r['error']) ? ['error' => (string) $r['error']] : []);
    }

    public function bootstrapPage(string $table, int $offset, int $limit): array
    {
        $r = $this->call('bootstrap/page', ['table' => $table, 'offset' => $offset, 'limit' => $limit]);

        return ['rows' => $r['rows'] ?? [], 'total' => (int) ($r['total'] ?? 0)];
    }

    /**
     * One signed request to `/<routePrefix>/<endpoint>` of the peer; returns its signed, unserialised answer.
     * Also for an application's own endpoints behind the `syncHmac` filter.
     *
     * @param array<string, scalar|null> $params
     *
     * @return array<string, mixed>
     */
    public function call(string $endpoint, array $params): array
    {
        if ($this->opt->secret === '' || $this->url === '') {
            throw new RuntimeException("sync.secret / the URL of peer '{$this->peerName}' are not configured.");
        }

        if (! $this->opt->transportAllowed($this->url)) {
            throw new RuntimeException("Refusing plain HTTP to peer '{$this->peerName}' ({$this->url}); use https or set sync.allowInsecure = true.");
        }

        $path  = '/' . $this->opt->routePrefix . '/' . $endpoint;
        $body  = http_build_query($params, '', '&');
        $ts    = time();
        $nonce = bin2hex(random_bytes(16));

        $t0       = microtime(true);
        $response = Services::curlrequest(['timeout' => $this->timeout, 'http_errors' => false, 'verify' => true])
            ->post($this->url . $path, [
                'body'    => $body,
                'headers' => [
                    'Content-Type'     => 'application/x-www-form-urlencoded',
                    'X-Sync-Node'      => $this->opt->node,
                    'X-Sync-Timestamp' => (string) $ts,
                    'X-Sync-Nonce'     => $nonce,
                    'X-Sync-Signature' => Signer::sign($this->opt->secret, 'POST', $path, $ts, $nonce, $body),
                ],
            ]);

        $t3  = microtime(true);
        $raw = (string) $response->getBody();

        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException("Peer answered HTTP {$response->getStatusCode()} on {$endpoint}.");
        }

        $sig = $response->getHeaderLine('X-Sync-Signature');

        if (! hash_equals(Signer::signResponse($this->opt->secret, $nonce, $raw), $sig)) {
            throw new RuntimeException("Bad response signature from the peer on {$endpoint}.");
        }

        $data = Support::unpack($raw);

        if (! is_array($data)) {
            throw new RuntimeException("Unexpected response from the peer on {$endpoint}.");
        }

        if ($this->clock !== null && isset($data['time']) && is_numeric($data['time'])) {
            $this->clock->observeResponse($this->peerName, $t0, $t3, (float) $data['time'], isset($data['ntp']) && is_numeric($data['ntp']) ? (float) $data['ntp'] : null);
        }

        return $data;
    }
}
