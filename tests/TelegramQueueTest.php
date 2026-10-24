<?php

declare(strict_types=1);

namespace AdGo\Sync\Tests;

use PHPUnit\Framework\TestCase;
use AdGo\Sync\Libraries\Options;
use AdGo\Sync\Libraries\TelegramNotifier;
use RuntimeException;

final class TelegramQueueTest extends TestCase
{
    private function opt(string $queue = 'telegram', string $token = 'T'): Options
    {
        return Options::fromArray(['node' => 'site1', 'peerNode' => 'site2', 'telegram' => ['token' => $token, 'chat_id' => '42', 'queue' => $queue]]);
    }

    public function testMessageIsQueuedNotSentAndNeverCarriesTheToken(): void
    {
        $pushed = [];
        $sent   = 0;
        $n      = new TelegramNotifier(
            $this->opt(),
            static function (string $m, array $p) use (&$pushed): void {
                $pushed[] = [$m, $p];
            },
            static function () use (&$sent): int {
                $sent++;

                return 200;
            },
        );

        $this->assertTrue($n->send('hello', [[['text' => 'x', 'callback_data' => 'y']]]));
        $this->assertSame(0, $sent);
        $this->assertCount(1, $pushed);
        $this->assertSame('sendMessage', $pushed[0][0]);
        $this->assertSame('42', $pushed[0][1]['chat_id']);
        $this->assertStringNotContainsString('"T"', serialize($pushed));
        $this->assertStringNotContainsString('T', (string) ($pushed[0][1]['chat_id'] . $pushed[0][1]['text']));
    }

    public function testResolvedAlertLosesItsButtonsAndGetsTheNote(): void
    {
        $calls = [];
        $n     = new TelegramNotifier(
            $this->opt(),
            null,
            static function (string $m, array $p) use (&$calls): int {
                $calls[] = [$m, $p];

                return 200;
            },
        );

        $n->markResolved('42', 7, 'Sync conflict #3', 'Resolved: kept site1');

        $this->assertSame('editMessageText', $calls[0][0]);
        $this->assertSame('7', $calls[0][1]['message_id']);
        $this->assertSame("Sync conflict #3

✅ Resolved: kept site1", $calls[0][1]['text']);
        $this->assertSame('{"inline_keyboard":[]}', $calls[0][1]['reply_markup']);
    }

    public function testFallsBackToDirectSendWhenTheQueueIsUnavailable(): void
    {
        $sent = [];
        $n    = new TelegramNotifier(
            $this->opt(),
            static function (): void {
                throw new RuntimeException('no queue_jobs table');
            },
            static function (string $m) use (&$sent): int {
                $sent[] = $m;

                return 200;
            },
        );

        $this->assertTrue($n->send('hello'));
        $this->assertSame(['sendMessage'], $sent);
    }

    public function testEmptyQueueNameSendsDirectly(): void
    {
        $pushed = 0;
        $sent   = 0;
        $n      = new TelegramNotifier(
            $this->opt(''),
            static function () use (&$pushed): void {
                $pushed++;
            },
            static function () use (&$sent): int {
                $sent++;

                return 200;
            },
        );

        $this->assertTrue($n->send('hello'));
        $this->assertSame([0, 1], [$pushed, $sent]);
    }

    public function testDeliverThrowsOnFailureSoTheQueueRetries(): void
    {
        $n = new TelegramNotifier($this->opt(), null, static fn (): int => 429);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 429');
        $n->deliver('sendMessage', ['chat_id' => '42', 'text' => 'x']);
    }

    public function testDisabledNotifierDoesNothing(): void
    {
        $called = 0;
        $n      = new TelegramNotifier($this->opt('telegram', ''), static function () use (&$called): void {
            $called++;
        }, static function () use (&$called): int {
            $called++;

            return 200;
        });

        $this->assertFalse($n->send('x'));
        $n->deliver('sendMessage', []);
        $this->assertSame(0, $called);
    }
}
