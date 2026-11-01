<?php

declare(strict_types=1);

namespace AdGo\Sync\Libraries;

use Closure;
use Config\Services;
use AdGo\Sync\Jobs\TelegramJob;
use RuntimeException;
use Throwable;

/**
 * Alerts + quick triage through a Telegram bot. Complement, NOT the source of
 * truth: the admin UI (ConflictAdmin) can resolve everything.
 *
 * The Telegram Bot API itself requires JSON (the `reply_markup` form field and
 * the webhook body). That is the single place in the package that touches JSON;
 * nothing JSON is ever stored or exchanged between the two nodes.
 *
 * Messages go through codeigniter4/queue (queue name `telegram.queue`, default
 * "telegram") so that a slow or failing Telegram API never delays a sync run and
 * failed deliveries are retried. If pushing to the queue is impossible (tables
 * missing, DB error) or `telegram.queue` is empty, the message is sent directly.
 *
 * Texts come from Language/<locale>/Sync.php (`telegram.*`) in the locale that is current when the message is
 * BUILT (not when the queue delivers it): the request's locale on the web, the application's default locale
 * (`Config\App::$defaultLocale`) in CLI commands, queue workers and scheduled tasks. Both nodes post to one
 * chat, so give them the same default locale.
 */
class TelegramNotifier
{
    /**
     * @param (Closure(string, array<string, string>): void)|null $push      test seam: replaces the queue push
     * @param (Closure(string, array<string, string>): int)|null  $transport test seam: replaces the HTTP call, returns the status code
     */
    public function __construct(
        private readonly Options $opt,
        private readonly ?Closure $push = null,
        private readonly ?Closure $transport = null,
    ) {
    }

    public function enabled(): bool
    {
        return $this->telegramEnabled() || $this->mailEnabled();
    }

    private function telegramEnabled(): bool
    {
        return ($this->opt->telegram['token'] ?? '') !== '' && ($this->opt->telegram['chat_id'] ?? '') !== '';
    }

    /** Every message is also mailed when sync.mail.to and sync.mail.host are set. */
    private function mailEnabled(): bool
    {
        return ($this->opt->mail['to'] ?? '') !== '' && ($this->opt->mail['host'] ?? '') !== '';
    }

    /**
     * @param array<string, mixed> $conflict a sync_conflicts row
     */
    public function conflict(array $conflict): bool
    {
        $id     = (int) $conflict['id'];
        $local  = (string) $conflict['local_node'];
        $remote = (string) $conflict['remote_node'];
        $l      = Support::unpack($conflict['local_blob']);
        $r      = Support::unpack($conflict['remote_blob']);
        $fields = $conflict['fields'] === '*' || $conflict['fields'] === '' ? Lang::line('telegram.rowDeletedOnOneNode') : $conflict['fields'];

        $text = Lang::line('telegram.alert', [
            $id,
            $this->opt->node,
            $conflict['table_name'],
            $conflict['uuid'],
            $fields,
            $local,
            gmdate('Y-m-d H:i:s', intdiv((int) $conflict['local_ts'], 1_000_000)) . 'Z',
            $this->brief($l, (string) $conflict['fields']),
            $remote,
            gmdate('Y-m-d H:i:s', intdiv((int) $conflict['remote_ts'], 1_000_000)) . 'Z',
            $this->brief($r, (string) $conflict['fields']),
        ]);

        $buttons = [[
            ['text' => Lang::line('telegram.keep', [$local]), 'callback_data' => "r:{$this->opt->node}:{$id}:l"],
            ['text' => Lang::line('telegram.keep', [$remote]), 'callback_data' => "r:{$this->opt->node}:{$id}:r"],
        ]];

        $admin = rtrim((string) ($this->opt->telegram['admin_url'] ?? ''), '/');

        if ($admin !== '') {
            $buttons[] = [['text' => Lang::line('telegram.openInAdmin'), 'url' => $admin . '/' . $id]];
        }

        return $this->send($text, $buttons);
    }

    /**
     * @param list<list<array<string, string>>> $buttons
     */
    public function send(string $text, array $buttons = []): bool
    {
        $ok = false;

        if ($this->telegramEnabled()) {
            $params = ['chat_id' => $this->opt->telegram['chat_id'], 'text' => $text, 'disable_web_page_preview' => 'true'];

            if ($buttons !== []) {
                $params['reply_markup'] = json_encode(['inline_keyboard' => $buttons], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $ok = $this->dispatch('sendMessage', $params);
        }

        if ($this->mailEnabled()) {
            $links = [];

            foreach ($buttons as $row) {
                foreach ($row as $b) {
                    if (isset($b['url'])) {
                        $links[] = $b['text'] . ': ' . $b['url'];
                    }
                }
            }

            $first = trim(strtok($text, "
") ?: 'sync');
            $ok    = $this->dispatch('email', [
                'to'      => (string) $this->opt->mail['to'],
                'subject' => '[' . $this->opt->node . '] ' . (mb_strlen($first) > 120 ? mb_substr($first, 0, 117) . '...' : $first),
                'text'    => $text . ($links === [] ? '' : "

" . implode("
", $links)),
            ]) || $ok;
        }

        return $ok;
    }

    /**
     * Actual delivery (used by TelegramJob). Throws on any failure so the queue retries it.
     *
     * @param array<string, string> $params
     */
    public function deliver(string $method, array $params): void
    {
        if (! $this->enabled()) {
            return; // disabled meanwhile: nothing to retry
        }

        if ($method === 'email') {
            $this->mail($params);

            return;
        }

        if (! $this->telegramEnabled()) {
            return;
        }

        $status = $this->transport !== null
            ? ($this->transport)($method, $params)
            : Services::curlrequest(['timeout' => 10, 'http_errors' => false])
                ->post('https://api.telegram.org/bot' . $this->opt->telegram['token'] . '/' . $method, ['form_params' => $params])
                ->getStatusCode();

        if ($status !== 200) {
            throw new RuntimeException("Telegram {$method} answered HTTP {$status}");
        }
    }

    /** @param array<string, string> $p to, subject, text */
    private function mail(array $p): void
    {
        if ($this->transport !== null) {
            $status = ($this->transport)('email', $p);

            if ($status !== 200) {
                throw new RuntimeException("mail answered {$status}");
            }

            return;
        }

        $m    = $this->opt->mail;
        $base = $this->emailDefaults();
        $from = (string) (($m['from'] ?? '') !== '' ? $m['from'] : (($m['user'] ?? '') !== '' ? $m['user'] : ($base['fromEmail'] ?? '')));
        $mail = Services::email([
            'protocol'    => 'smtp',
            'SMTPHost'    => (string) $m['host'],
            'SMTPPort'    => (int) ($m['port'] ?? 587),
            'SMTPUser'    => (string) ($m['user'] ?? ''),
            'SMTPPass'    => (string) ($m['pass'] ?? ''),
            'SMTPCrypto'  => (string) ($m['crypto'] ?? 'tls'),
            'SMTPTimeout' => 15,
            'mailType'    => 'text',
            'charset'     => 'UTF-8',
            'newline'     => "
",
            'CRLF'        => "
",
        ] + $base, false);
        $mail->setFrom($from, 'Sync ' . $this->opt->node)->setTo($p['to'])->setSubject($p['subject'])->setMessage($p['text']);

        if (! $mail->send(false)) {
            throw new RuntimeException('mail: ' . trim(strip_tags($mail->printDebugger(['headers']))));
        }
    }

    /** @return array<string, mixed> the application's Config\Email values, used for whatever sync.mail does not set */
    private function emailDefaults(): array
    {
        try {
            return get_object_vars(config('Email'));
        } catch (Throwable) {
            return [];
        }
    }

    public function answerCallback(string $callbackId, string $text): void
    {
        $this->api('answerCallbackQuery', ['callback_query_id' => $callbackId, 'text' => $text]);
    }

    /** Turns an alert into a decided one: the note is appended and the buttons are removed. */
    public function markResolved(string $chatId, int $messageId, string $originalText, string $note): void
    {
        $this->api('editMessageText', [
            'chat_id'                  => $chatId,
            'message_id'               => (string) $messageId,
            'text'                     => $originalText . "

✅ " . $note,
            'disable_web_page_preview' => 'true',
            'reply_markup'             => json_encode(['inline_keyboard' => []]),
        ]);
    }

    /**
     * Queue first, direct send as the fallback.
     *
     * @param array<string, string> $params
     */
    private function dispatch(string $method, array $params): bool
    {
        if (($this->opt->telegram['queue'] ?? '') !== '') {
            try {
                $this->push !== null
                    ? ($this->push)($method, $params)
                    : service('queue')->push((string) $this->opt->telegram['queue'], TelegramJob::NAME, ['method' => $method, 'params' => $params]);

                return true;
            } catch (Throwable $e) {
                $this->log('Telegram queue unavailable, sending directly: ' . $e->getMessage());
            }
        }

        return $this->api($method, $params);
    }

    /**
     * Immediate delivery without retries (callback answers must not wait for a worker).
     *
     * @param array<string, string> $params
     */
    private function api(string $method, array $params): bool
    {
        try {
            $this->deliver($method, $params);

            return true;
        } catch (Throwable $e) {
            $this->log('Telegram: ' . $e->getMessage());

            return false;
        }
    }

    /** A logging problem must never break (or hide) a delivery. */
    private function log(string $message): void
    {
        try {
            log_message('error', $message);
        } catch (Throwable) {
        }
    }

    /**
     * @param array<string, mixed>|null $row
     */
    private function brief(?array $row, string $fields): string
    {
        if ($row === null) {
            return Lang::line('telegram.deleted');
        }

        $keys = $fields !== '' && $fields !== '*' ? explode(',', $fields) : array_keys($row);
        $out  = [];

        foreach ($keys as $k) {
            $v     = (string) ($row[$k] ?? 'NULL');
            $out[] = $k . '=' . (mb_strlen($v) > 60 ? mb_substr($v, 0, 57) . '...' : $v);
        }

        return implode(', ', $out);
    }
}
