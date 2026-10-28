<?php

declare(strict_types=1);

namespace AdGo\Sync\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use AdGo\Sync\Libraries\ClockMonitor;
use AdGo\Sync\Libraries\Kernel;
use AdGo\Sync\Libraries\NonceStore;
use AdGo\Sync\Libraries\Signer;
use Throwable;

/**
 * Authenticates peer-to-peer API calls: HMAC signature over method, path,
 * sender node, timestamp, nonce and body hash; bounded timestamp window; single-use nonce (atomic, see NonceStore);
 * bounded request size.
 * Also signs the response so the client can authenticate the answer.
 */
class SyncHmac implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $kernel = Kernel::instance();
        $opt    = $kernel->opt;

        if ($request->getMethod() !== 'POST') {
            return $this->deny(405, 'method');
        }

        if ($opt->maxBody > 0 && strlen((string) $request->getBody()) > $opt->maxBody) {
            return $this->deny(413, 'too large');
        }

        $ts    = (int) $request->getHeaderLine('X-Sync-Timestamp');
        $nonce = $request->getHeaderLine('X-Sync-Nonce');
        $sig   = $request->getHeaderLine('X-Sync-Signature');
        $node  = $request->getHeaderLine('X-Sync-Node');
        $path  = '/' . trim(uri_string(), '/');

        $check = Signer::verify($opt->secret, 'POST', $path, $node, $ts, $nonce, (string) $request->getBody(), $sig, $opt->replayWindow);

        if (! $check['ok']) {
            log_message('warning', 'sync: rejected request from ' . $request->getIPAddress() . ': ' . ($check['reason'] ?? ''));

            return $this->deny(401, 'unauthorized');
        }

        if (! $opt->isPeer($node)) {
            return $this->deny(403, 'unknown node');
        }

        $kernel->requestPeer = $node;
        (new ClockMonitor($kernel->db, $opt))->observeRequest($node, $ts);

        try {
            if (! (new NonceStore($kernel->db, $opt->replayWindow * 2))->claim($nonce)) {
                return $this->deny(401, 'replay');
            }
        } catch (Throwable $e) {
            log_message('error', 'sync: ' . $e->getMessage());

            return $this->deny(503, 'nonce store unavailable');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $opt = Kernel::instance()->opt;

        if ($response->getStatusCode() === 200) {
            $response->setHeader('X-Sync-Signature', Signer::signResponse($opt->secret, $request->getHeaderLine('X-Sync-Nonce'), (string) $response->getBody()));
        }

        return $response;
    }

    private function deny(int $code, string $why): ResponseInterface
    {
        return service('response')->setStatusCode($code)->setBody($why);
    }
}
