<?php

declare(strict_types=1);

namespace AdGo\Sync\Tests;

use PHPUnit\Framework\TestCase;
use AdGo\Sync\Libraries\Support;

final class ExclusiveLockTest extends TestCase
{
    public function testRunsAndReleases(): void
    {
        $n = 0;

        $this->assertTrue(Support::exclusive('unit-a', static function () use (&$n): void {
            $n++;
        }));
        $this->assertTrue(Support::exclusive('unit-a', static function () use (&$n): void {
            $n++;
        }), 'the lock must be free again after the first run');
        $this->assertSame(2, $n);
    }

    public function testSecondCallerIsSkippedWhileTheFirstIsRunning(): void
    {
        $inner = null;
        $ran   = 0;

        $this->assertTrue(Support::exclusive('unit-b', function () use (&$inner, &$ran): void {
            $inner = Support::exclusive('unit-b', static function () use (&$ran): void {
                $ran++;
            });
        }));

        $this->assertFalse($inner);
        $this->assertSame(0, $ran);
    }

    public function testDifferentNamesDoNotBlockEachOther(): void
    {
        $inner = null;
        Support::exclusive('unit-c', function () use (&$inner): void {
            $inner = Support::exclusive('unit-d', static function (): void {
            });
        });

        $this->assertTrue($inner);
    }

    public function testLockIsReleasedWhenTheJobThrows(): void
    {
        try {
            Support::exclusive('unit-e', static function (): void {
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException) {
        }

        $this->assertTrue(Support::exclusive('unit-e', static function (): void {
        }));
    }
}
