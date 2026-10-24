<?php

declare(strict_types=1);

namespace AdGo\Sync\Tests;

use PHPUnit\Framework\TestCase;
use AdGo\Sync\Libraries\Options;

final class TiebreakTest extends TestCase
{
    public function testTheConfiguredOrderIsKeptWhenItNamesBothNodes(): void
    {
        $o = Options::fromArray(['node' => 'b', 'peerNode' => 'a', 'tiebreak' => ['b', 'a']]);

        $this->assertSame(0, $o->rank('b'));
        $this->assertSame(1, $o->rank('a'));
    }

    public function testRenamedNodesGetTheAlphabeticalOrderOnBothSides(): void
    {
        // the default list still says site1/site2 although the nodes are now called "zulu" and "alpha"
        $here  = Options::fromArray(['node' => 'zulu', 'peerNode' => 'alpha', 'tiebreak' => ['site1', 'site2']]);
        $there = Options::fromArray(['node' => 'alpha', 'peerNode' => 'zulu', 'tiebreak' => ['site1', 'site2']]);

        $this->assertSame(0, $here->rank('alpha'));
        $this->assertSame(1, $here->rank('zulu'));
        $this->assertSame([$here->rank('alpha'), $here->rank('zulu')], [$there->rank('alpha'), $there->rank('zulu')], 'both nodes agree');
    }

    public function testNoListAtAllWorksToo(): void
    {
        $o = Options::fromArray(['node' => 'n2', 'peerNode' => 'n1']);

        $this->assertSame(0, $o->rank('n1'));
        $this->assertSame(PHP_INT_MAX, $o->rank('someone-else'));
    }

    public function testTheDefaultNamesKeepTheirHistoricOrder(): void
    {
        $o = Options::fromArray(['node' => 'site2', 'peerNode' => 'site1']);

        $this->assertSame(0, $o->rank('site1'));
        $this->assertSame(1, $o->rank('site2'));
    }
}
