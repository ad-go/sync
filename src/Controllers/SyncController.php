<?php

declare(strict_types=1);

namespace AdGo\Sync\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use AdGo\Sync\Libraries\AuthLock;
use AdGo\Sync\Libraries\Bootstrapper;
use AdGo\Sync\Libraries\Kernel;
use AdGo\Sync\Libraries\Lang;
use AdGo\Sync\Libraries\Options;
use AdGo\Sync\Libraries\Support;
use AdGo\Sync\Libraries\SyncClient;
use Throwable;

/**
 * Peer-to-peer API. Every route except the Telegram webhook sits behind the
 * `syncHmac` filter. Requests are form-encoded POSTs, answers are
 * PHP-serialized (never JSON).
 */
class SyncController extends Controller
{
    private const LOCK_KINDS = ['auth', 'cron'];

    private Kernel $kernel;

    public function __construct()
    {
        $this->kernel = Kernel::instance();
    }

    /** POST ping: who I am, my clock, the address the caller reached me from, and the peers I relay. */
    public function ping(): ResponseInterface
    {
        return $this->reply($this->kernel->reachability()->pingAnswer($this->request->getIPAddress()));
    }

    /**
     * POST probe: [peer]. Calls the caller back at the URL this node has configured for it; with `peer`, pings that
     * peer of this node on the caller's behalf (relay check). Never takes a URL from the request.
     */
    public function probe(): ResponseInterface
    {
        return $this->reply($this->kernel->reachability()->probeBack($this->kernel->sender(), trim((string) $this->request->getPost('peer'))));
    }

    /** POST pull: after, limit */
    public function pull(): ResponseInterface
    {
        $after = max(0, (int) $this->request->getPost('after'));
        $limit = max(1, min((int) $this->request->getPost('limit') ?: $this->kernel->opt->batch(), Options::MAX_BATCH));
        $items = $this->kernel->meta->changesSince($after, $limit);

        return $this->reply(['changes' => $items, 'last_seq' => $items === [] ? $after : (int) end($items)['seq']]);
    }

    /**
     * POST push: changes (packed list in the shape `pull` returns). A passive peer delivers its journal; the entries
     * go through the Reconciler like pulled ones and the sender's cursor records how far we got. Repeats are
     * harmless (revisions are compared), so the sender may resend after any failure.
     */
    public function push(): ResponseInterface
    {
        $changes = Support::unpack((string) $this->request->getPost('changes'));
        $sender  = $this->kernel->sender();

        if (! is_array($changes)) {
            return $this->response->setStatusCode(400)->setBody('bad changes');
        }

        if (count($changes) > $this->kernel->opt->maxPushChanges) {
            return $this->response->setStatusCode(413)->setBody('too many changes');
        }

        $kernel = $this->kernel;
        $stats  = null;

        if (! Support::exclusiveWait('pull', static function () use ($kernel, $sender, $changes, &$stats): void {
            $stats = $kernel->puller()->applyBatch($sender, $changes);
        }, 20)) {
            return $this->reply(['applied' => $this->kernel->meta->cursor($sender), 'stopped' => 'another sync is running']);
        }

        return $this->reply(['applied' => $stats['cursor'], 'stopped' => $stats['stopped'], 'applied_now' => $stats['pulled']]);
    }

    public function resolve(): ResponseInterface
    {
        $p     = $this->request->getPost();
        $state = Support::unpack((string) ($p['state'] ?? ''));

        if ($state !== null && ! is_array($state)) {
            return $this->reply(['ok' => false, 'error' => 'bad state']);
        }

        return $this->reply($this->kernel->resolver()->applyPropagated(
            (string) ($p['table'] ?? ''),
            (string) ($p['uuid'] ?? ''),
            $state,
            (int) ($p['new_rev'] ?? 0),
            (int) ($p['expect_rev'] ?? 0),
            (int) ($p['ts'] ?? Support::nowUs()),
            (string) ($p['by'] ?? 'peer'),
            $this->kernel->sender(),
        ));
    }

    public function lockStatus(): ResponseInterface
    {
        return $this->lockCall(fn ($lock, $kind) => $lock->handleStatus($kind));
    }

    public function lockAcquire(): ResponseInterface
    {
        return $this->lockCall(fn ($lock, $kind) => $lock->handleAcquire($kind, $this->kernel->sender(), $this->lockMeta()));
    }

    public function lockHeartbeat(): ResponseInterface
    {
        return $this->lockCall(fn ($lock, $kind) => $lock->handleHeartbeat($kind, $this->kernel->sender(), $this->lockMeta()));
    }

    /**
     * POST auth-lock/sync: from, kick. Reports how far this node has applied the peer's journal. With `kick`
     * it also pulls it right now, AFTER the answer has been sent (the peer polls instead of waiting here).
     */
    public function lockSync(): ResponseInterface
    {
        $applied = $this->localLocks()->handleApplied($this->kernel->sender());

        // a passive peer cannot be pulled: it pushes instead
        if ((int) $this->request->getPost('kick') === 1 && ! $this->kernel->opt->isPassivePeer($this->kernel->sender())) {
            $kernel = $this->kernel;

            // post_system fires BEFORE CodeIgniter sends the response, so use a shutdown function (runs after it).
            ignore_user_abort(true);
            register_shutdown_function(static function () use ($kernel): void {
                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                }

                try {
                    Support::exclusiveWait('pull', static function () use ($kernel): void {
                        $kernel->puller()->run();
                    }, 20);
                } catch (\Throwable $e) {
                    log_message('error', 'sync kick failed: ' . $e->getMessage());
                }
            });
        }

        return $this->reply(['applied' => $applied]);
    }

    /** @return array<string, string> */
    private function lockMeta(): array
    {
        $meta = [];

        foreach (['mode', 'scope', 'by'] as $k) {
            $v = $this->request->getPost('meta_' . $k);

            if (is_string($v)) {
                $meta[$k] = $v;
            }
        }

        return $meta;
    }

    public function lockRelease(): ResponseInterface
    {
        return $this->lockCall(fn ($lock, $kind) => $lock->handleRelease($kind, $this->kernel->sender()));
    }

    public function filesManifest(): ResponseInterface
    {
        return $this->reply(['files' => $this->kernel->files()->manifest()]);
    }

    public function filesGet(): ResponseInterface
    {
        $bytes = $this->kernel->files()->read((string) $this->request->getPost('path'));

        return $this->reply($bytes === null ? ['found' => false] : ['found' => true, 'content' => $bytes]);
    }

    /** POST files/put: a passive peer delivers a file, a directory or a deletion (see FileSync::receive). */
    public function filesPut(): ResponseInterface
    {
        if (! $this->kernel->opt->isPassivePeer($this->kernel->sender())) {
            return $this->response->setStatusCode(403)->setBody('only passive peers push files');
        }

        return $this->reply($this->kernel->files()->receive($this->request->getPost()));
    }

    public function bootstrapPage(): ResponseInterface
    {
        try {
            $page = (new Bootstrapper($this->kernel->db, $this->kernel->opt, $this->kernel->peer()))->page(
                (string) $this->request->getPost('table'),
                (int) $this->request->getPost('offset'),
                (int) $this->request->getPost('limit'),
            );
        } catch (Throwable $e) {
            return $this->response->setStatusCode(400)->setBody($e->getMessage());
        }

        return $this->reply($page);
    }

    /**
     * POST telegram: webhook of the bot. Telegram sends JSON (its protocol);
     * the request is authenticated with the webhook secret token and the chat id.
     */
    public function telegram(): ResponseInterface
    {
        $tg = $this->kernel->opt->telegram;

        if (($tg['webhook_secret'] ?? '') === '' || ! hash_equals($tg['webhook_secret'], $this->request->getHeaderLine('X-Telegram-Bot-Api-Secret-Token'))) {
            return $this->response->setStatusCode(403)->setBody('forbidden');
        }

        $update = $this->request->getJSON(true);
        $cb     = is_array($update) ? ($update['callback_query'] ?? null) : null;

        if (! is_array($cb) || (string) ($cb['message']['chat']['id'] ?? '') !== (string) ($tg['chat_id'] ?? '')) {
            return $this->response->setBody('ok');
        }

        $allowed = $this->kernel->opt->telegramUsers();

        if ($allowed !== [] && ! in_array((string) ($cb['from']['id'] ?? ''), $allowed, true)) {
            $this->kernel->telegram()->answerCallback((string) $cb['id'], Lang::line('telegram.notAllowed'));

            return $this->response->setBody('ok');
        }

        $notifier = $this->kernel->telegram();

        // callback_data = r:<node>:<conflict id>:<l|r>
        if (! preg_match('/^r:([A-Za-z0-9_.-]+):(\d+):([lr])$/', (string) ($cb['data'] ?? ''), $m)) {
            return $this->response->setBody('ok');
        }

        $by = 'telegram:' . ($cb['from']['username'] ?? $cb['from']['id'] ?? '?');

        if ($m[1] === $this->kernel->opt->node) {
            $res = $this->decide((int) $m[2], $m[3], $by);
        } elseif ($this->kernel->opt->isPeer($m[1]) && $this->kernel->opt->urlOf($m[1]) !== '') {
            // One bot, one webhook: the conflict lives on the peer, which takes the decision (signed request).
            try {
                $res = (new SyncClient($this->kernel->opt, $m[1]))->call('telegram/decide', ['id' => (int) $m[2], 'side' => $m[3], 'by' => $by]);
            } catch (\Throwable $e) {
                $res = ['ok' => false, 'error' => Lang::line('telegram.peerUnreachable')];
            }
        } else {
            $res = ['ok' => false, 'error' => Lang::line('telegram.unknownNode')];
        }

        // Texts in the locale of this (webhook) request: the application's default locale, see Libraries\Lang.
        $notifier->answerCallback((string) $cb['id'], ($res['ok'] ?? false)
            ? Lang::line('telegram.kept', [$res['kept'] ?? '?'])
            : Lang::line('telegram.failed', [$res['error'] ?? '?']));

        $msg = $cb['message'] ?? [];

        if (($res['ok'] ?? false) && isset($msg['message_id'])) {
            $notifier->markResolved(
                (string) $msg['chat']['id'],
                (int) $msg['message_id'],
                (string) ($msg['text'] ?? ''),
                Lang::line('telegram.resolvedNote', [$res['kept'] ?? '?', $by, gmdate('Y-m-d H:i:s') . 'Z']),
            );
        }

        return $this->response->setBody('ok');
    }

    /**
     * POST telegram/decide: id, side (l|r), by. The peer's webhook forwards a decision for a conflict of THIS node
     * (HMAC-signed like every peer call).
     */
    public function telegramDecide(): ResponseInterface
    {
        $p    = $this->request->getPost();
        $side = (string) ($p['side'] ?? '');

        if (! in_array($side, ['l', 'r'], true)) {
            return $this->reply(['ok' => false, 'error' => 'bad side']);
        }

        return $this->reply($this->decide((int) ($p['id'] ?? 0), $side, mb_substr((string) ($p['by'] ?? 'telegram'), 0, 64)));
    }

    /**
     * @return array{ok: bool, kept?: string, error?: string}
     */
    private function decide(int $id, string $side, string $by): array
    {
        $c = $this->kernel->db->table('sync_conflicts')->where('id', $id)->get()->getRowArray();

        if ($c === null) {
            return ['ok' => false, 'error' => Lang::line('flash.unknownConflict')];
        }

        $choice = $side === 'l' ? $c['local_node'] : $c['remote_node'];
        $res    = $this->kernel->resolver()->resolve($id, $choice, null, $by);

        return $res['ok'] ? ['ok' => true, 'kept' => (string) $choice] : ['ok' => false, 'error' => (string) ($res['error'] ?? '?')];
    }

    /**
     * @param callable(AuthLock, string): array<string, mixed> $fn
     */
    private function lockCall(callable $fn): ResponseInterface
    {
        $kind = (string) $this->request->getPost('kind');

        if (! in_array($kind, self::LOCK_KINDS, true)) {
            return $this->response->setStatusCode(400)->setBody('bad kind');
        }

        return $this->reply($fn($this->localLocks(), $kind));
    }

    /** The peer-facing lock handlers only touch the local table: no PeerClient needed. */
    private function localLocks(): AuthLock
    {
        return new AuthLock($this->kernel->db, $this->kernel->opt, null);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function reply(array $data): ResponseInterface
    {
        return $this->response->setHeader('Content-Type', 'application/octet-stream')->setBody(Support::pack($data + ['time' => microtime(true)]));
    }
}
