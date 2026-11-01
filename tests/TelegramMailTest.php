<?php

declare(strict_types=1);

namespace AdGo\Sync\Tests;

use PHPUnit\Framework\TestCase;
use AdGo\Sync\Libraries\Options;
use AdGo\Sync\Libraries\TelegramNotifier;

/** Every Telegram message is also mailed when sync.mail is configured. */
final class TelegramMailTest extends TestCase
{
    private function opt(array $mail, string $token = 'T'): Options
    {
        return Options::fromArray([
            'node' => 'site1', 'peerNode' => 'site2',
            'telegram' => ['token' => $token, 'chat_id' => '42', 'queue' => 'telegram'],
            'mail' => $mail,
        ]);
    }

    public function testTheMessageGoesToTelegramAndToTheMailbox(): void
    {
        $pushed = [];
        $n      = new TelegramNotifier($this->opt(['to' => 'ops@example.com', 'host' => 'smtp.example.com', 'pass' => 'SECRET']), static function (string $m, array $p) use (&$pushed): void {
            $pushed[$m] = $p;
        });

        $this->assertTrue($n->send("Sync conflict #3\nrow x", [[['text' => 'Open', 'url' => 'https://a/b/3']]]));
        $this->assertSame(['sendMessage', 'email'], array_keys($pushed));
        $this->assertSame('ops@example.com', $pushed['email']['to']);
        $this->assertSame('[site1] Sync conflict #3', $pushed['email']['subject']);
        $this->assertStringContainsString('Open: https://a/b/3', $pushed['email']['text']);
        $this->assertStringNotContainsString('SECRET', serialize($pushed), 'the SMTP password is read at delivery, never queued');
    }

    public function testMailAloneWorksWithoutTelegram(): void
    {
        $got = [];
        $n   = new TelegramNotifier($this->opt(['to' => 'ops@example.com', 'host' => 'smtp.example.com'], ''), static function (string $m) use (&$got): void {
            $got[] = $m;
        });

        $this->assertTrue($n->send('hello'));
        $this->assertSame(['email'], $got);
    }

    public function testNoMailTargetMeansTelegramOnly(): void
    {
        $got = [];
        $n   = new TelegramNotifier($this->opt([]), static function (string $m) use (&$got): void {
            $got[] = $m;
        });

        $n->send('hello');
        $this->assertSame(['sendMessage'], $got);
    }

    public function testDeliveringMailUsesTheTransportSeamAndNeverTelegram(): void
    {
        $calls = [];
        $n     = new TelegramNotifier($this->opt(['to' => 'a@b.c', 'host' => 'h']), null, static function (string $m) use (&$calls): int {
            $calls[] = $m;

            return 200;
        });

        $n->deliver('email', ['to' => 'a@b.c', 'subject' => 's', 'text' => 't']);
        $this->assertSame(['email'], $calls);
    }
}
