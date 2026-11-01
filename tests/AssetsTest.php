<?php

declare(strict_types=1);

namespace AdGo\Sync\Tests;

use AdGo\Sync\Libraries\Assets;
use PHPUnit\Framework\TestCase;

final class AssetsTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/sync-assets-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->dir);
    }

    public function testEditorFilesAreCopiedAndAKeyJsIsAcceptedForTheKey(): void
    {
        $from = $this->dir . '/licensed';
        mkdir($from);
        file_put_contents($from . '/datatables.min.js', 'js');
        file_put_contents($from . '/datatables.min.css', 'css');
        file_put_contents($from . '/key.js', 'key');

        $result = Assets::copyEditor($from, $this->dir . '/dest');

        $this->assertSame(['datatables.min.js' => 'copied', 'datatables.min.css' => 'copied', 'key.bak' => 'copied'], $result);
        $this->assertSame('key', file_get_contents($this->dir . '/dest/key.bak'));
    }

    public function testMissingEditorFilesAreReportedAndNothingIsInventedForThem(): void
    {
        $from = $this->dir . '/empty';
        mkdir($from);

        $result = Assets::copyEditor($from, $this->dir . '/dest');

        $this->assertSame(['datatables.min.js' => 'missing', 'datatables.min.css' => 'missing', 'key.bak' => 'missing'], $result);
        $this->assertFileDoesNotExist($this->dir . '/dest/datatables.min.js');
    }

    public function testEveryPinnedPublicFileHasAUrlAndA64CharHash(): void
    {
        foreach (Assets::PUBLIC as $path => [$url, $sha]) {
            $this->assertStringStartsWith('https://', $url, $path);
            $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $sha, $path);
        }
    }

    public function testFontFacesAreListedOnlyForFilesThatArePresent(): void
    {
        $root = $this->dir . '/';
        mkdir($root . 'assets/fonts', 0775, true);
        file_put_contents($root . 'assets/fonts/inter-400.woff2', 'x');

        $css = Assets::fontsCss($root);

        $this->assertStringContainsString("font-family:'Inter';font-style:normal;font-weight:400", $css);
        $this->assertStringNotContainsString('Merriweather', $css, 'a missing file is not listed');
        $this->assertTrue(Assets::writeFontsCss($root));
        $this->assertFileExists($root . 'assets/fonts/fonts.css');
    }

    private function rrmdir(string $dir): void
    {
        foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $f) {
            is_dir($dir . '/' . $f) ? $this->rrmdir($dir . '/' . $f) : unlink($dir . '/' . $f);
        }

        rmdir($dir);
    }
}
