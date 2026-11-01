<?php

declare(strict_types=1);

namespace AdGo\Sync\Libraries;

use RuntimeException;

/**
 * Front-end files the admin pages load. The public ones are pinned by version and SHA-256 and downloaded by
 * `php spark sync:assets` into the application's public/ folder; when a file is there, the pages use it instead
 * of the CDN. DataTables Editor is licensed and never downloaded: `sync:assets --dte=<folder>` copies it from a
 * folder that holds your licensed bundle (see Config\Sync::$dteAssetsUrl).
 */
final class Assets
{
    /**
     * Public path (relative to public/) => [download URL, SHA-256].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const PUBLIC = [
        'assets/vendor/jquery-3.7.1.min.js' => [
            'https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js',
            'fc9a93dd241f6b045cbff0481cf4e1901becd0e12fb45166a8f17f95823f0b1a',
        ],
        'assets/vendor/bootstrap-5.3.8/bootstrap.min.css' => [
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css',
            'd85327d99c7a3ee1f9b5d0500d1370acea3ad2db39c163c2f51f232baedbdede',
        ],
        'assets/vendor/bootstrap-5.3.8/bootstrap.bundle.min.js' => [
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js',
            'e4fd49181388c48ec5040bd3fe66f57c29c8e67fcd8502b3354b96ec7ab47cc7',
        ],
        'assets/vendor/bootstrap-icons-1.11.3/bootstrap-icons.min.css' => [
            'https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css',
            'f643d6fe7e679f9de3e16311600c5ef5cd6b098f7a3a8828fcc29255d2b33e62',
        ],
        'assets/vendor/bootstrap-icons-1.11.3/fonts/bootstrap-icons.woff2' => [
            'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/fonts/bootstrap-icons.woff2',
            '476adf42b40325098fcfa8b36ab3e769186bb4f6ce6a249753e2e1a9c22bf99e',
        ],
        'assets/vendor/bootstrap-icons-1.11.3/fonts/bootstrap-icons.woff' => [
            'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/fonts/bootstrap-icons.woff',
            'bb1de989b83970f6f4e54de1cd974c5cba55b73582da5e1b225a6d0edf029483',
        ],
        // jsTree 3.3.17 (MIT), the folder picker of the Settings page. Its default theme refers to the images below.
        'assets/vendor/jstree-3.3.17/jstree.min.js' => [
            'https://cdn.jsdelivr.net/npm/jstree@3.3.17/dist/jstree.min.js',
            'b7754dd60e7cb55e608675ee5112939625c8090710d33c079868e132010b1d6a',
        ],
        'assets/vendor/jstree-3.3.17/themes/default/style.min.css' => [
            'https://cdn.jsdelivr.net/npm/jstree@3.3.17/dist/themes/default/style.min.css',
            'a0e940031a2893b30c999476073c0ccb774fdb08614171b7f2b721f4ce0a7a10',
        ],
        'assets/vendor/jstree-3.3.17/themes/default/32px.png' => [
            'https://cdn.jsdelivr.net/npm/jstree@3.3.17/dist/themes/default/32px.png',
            '3a5c5ca760a324913b14af3e9c0f496b50d5c09d17dcf09786261881c8200f8d',
        ],
        'assets/vendor/jstree-3.3.17/themes/default/40px.png' => [
            'https://cdn.jsdelivr.net/npm/jstree@3.3.17/dist/themes/default/40px.png',
            'a83492a1563137335ce3d41d82552c17104ebea9bb80c3dbe7673201b92197d7',
        ],
        'assets/vendor/jstree-3.3.17/themes/default/throbber.gif' => [
            'https://cdn.jsdelivr.net/npm/jstree@3.3.17/dist/themes/default/throbber.gif',
            '28e3fcaacae52189201f54a20576ee63c7450be82a0ee55398ac2f51bed98fac',
        ],
        // Open-source web fonts (SIL Open Font License), Latin subset, from @fontsource. Used when DTE is licensed (pay).
        'assets/fonts/inter-400.woff2' => [
            'https://cdn.jsdelivr.net/npm/@fontsource/inter@5.0.16/files/inter-latin-400-normal.woff2',
            '2301bb030a2bcaa9c763cc4771bd717aac16709c29eaba00673fcbe7cdf99a59',
        ],
        'assets/fonts/inter-700.woff2' => [
            'https://cdn.jsdelivr.net/npm/@fontsource/inter@5.0.16/files/inter-latin-700-normal.woff2',
            '7b43cb86a0e63bbb55376b4ea60d8cc9527a1421c367aa09962725e0c5140f5f',
        ],
        'assets/fonts/merriweather-400.woff2' => [
            'https://cdn.jsdelivr.net/npm/@fontsource/merriweather@5.0.8/files/merriweather-latin-400-normal.woff2',
            '5c2d662e92bcbf1a5970b97040f901031295e79a96314db8302f549003022087',
        ],
        'assets/fonts/merriweather-700.woff2' => [
            'https://cdn.jsdelivr.net/npm/@fontsource/merriweather@5.0.8/files/merriweather-latin-700-normal.woff2',
            '273c8613cdd2852dd5318f224d804ae6d2fc717c48d3f1dab587b6d396fb4fc8',
        ],
        'assets/fonts/jetbrains-400.woff2' => [
            'https://cdn.jsdelivr.net/npm/@fontsource/jetbrains-mono@5.0.16/files/jetbrains-mono-latin-400-normal.woff2',
            '7c53386f55c866c1b4c9309c4bcf74eda10896aab3a1780b0af5cc4976e27a27',
        ],
    ];

    /** @var list<array{0: string, 1: int, 2: string}> family, weight, file name under assets/fonts/ */
    public const FONT_FACES = [
        ['Inter', 400, 'inter-400.woff2'],
        ['Inter', 700, 'inter-700.woff2'],
        ['Merriweather', 400, 'merriweather-400.woff2'],
        ['Merriweather', 700, 'merriweather-700.woff2'],
        ['JetBrains Mono', 400, 'jetbrains-400.woff2'],
    ];

    /** Writes assets/fonts/fonts.css for the faces that are downloaded. Returns false when there is none. */
    public static function writeFontsCss(string $root): bool
    {
        $css = self::fontsCss($root);

        if ($css === '') {
            return false;
        }

        if (! is_dir($root . 'assets/fonts')) {
            mkdir($root . 'assets/fonts', 0775, true);
        }

        file_put_contents($root . 'assets/fonts/fonts.css', $css);

        return true;
    }

    /**
     * The @font-face rules for the downloaded fonts (written to assets/fonts/fonts.css). Only the faces whose file is
     * present are listed, so a partial download never points at a missing file.
     */
    public static function fontsCss(string $root): string
    {
        $css = '';

        foreach (self::FONT_FACES as [$family, $weight, $file]) {
            if (! is_file($root . 'assets/fonts/' . $file)) {
                continue;
            }

            $css .= "@font-face{font-family:'{$family}';font-style:normal;font-weight:{$weight};font-display:swap;"
                . "src:url('{$file}') format('woff2')}\n";
        }

        return $css;
    }

    /** Files of the licensed DataTables Editor bundle the Users page needs, in Config\Sync::$dteAssetsUrl. */
    public const DTE_FILES = ['datatables.min.js', 'datatables.min.css', 'key.bak'];

    /** URL the pages should use for a public asset: the local copy when it was downloaded, otherwise the CDN. */
    public static function url(string $path): string
    {
        if (is_file(FCPATH . $path)) {
            return site_url($path);
        }

        return self::PUBLIC[$path][0] ?? site_url($path);
    }

    /**
     * Downloads every public file that is missing or whose hash does not match. Each file is written only after its
     * SHA-256 checks out.
     *
     * @return array<string, string> path => 'downloaded' | 'present' | 'failed: <reason>'
     */
    public static function downloadPublic(string $root): array
    {
        $result = [];

        foreach (self::PUBLIC as $path => [$url, $sha]) {
            $target = $root . $path;

            if (is_file($target) && hash_file('sha256', $target) === $sha) {
                $result[$path] = 'present';
                continue;
            }

            try {
                $body = self::fetch($url);
            } catch (RuntimeException $e) {
                $result[$path] = 'failed: ' . $e->getMessage();
                continue;
            }

            if (hash('sha256', $body) !== $sha) {
                $result[$path] = 'failed: SHA-256 mismatch (the CDN file changed; check the version before trusting it)';
                continue;
            }

            if (! is_dir(dirname($target)) && ! mkdir(dirname($target), 0775, true)) {
                $result[$path] = 'failed: cannot create ' . dirname($target);
                continue;
            }

            file_put_contents($target, $body);
            $result[$path] = 'downloaded';
        }

        return $result;
    }

    /**
     * Copies the licensed DataTables Editor files from $from (a folder holding datatables.min.js, datatables.min.css
     * and key.bak or key.js) into $dest.
     *
     * @return array<string, string> file => 'copied' | 'missing'
     */
    public static function copyEditor(string $from, string $dest): array
    {
        $result = [];

        foreach (self::DTE_FILES as $file) {
            $src = rtrim($from, '/\\') . DIRECTORY_SEPARATOR . $file;

            // key.js is accepted for the key file, which some bundles ship under that name.
            if (! is_file($src) && $file === 'key.bak' && is_file(dirname($src) . DIRECTORY_SEPARATOR . 'key.js')) {
                $src = dirname($src) . DIRECTORY_SEPARATOR . 'key.js';
            }

            if (! is_file($src)) {
                $result[$file] = 'missing';
                continue;
            }

            if (! is_dir($dest)) {
                mkdir($dest, 0775, true);
            }

            copy($src, $dest . DIRECTORY_SEPARATOR . $file);
            $result[$file] = 'copied';
        }

        return $result;
    }

    private static function fetch(string $url): string
    {
        $context = stream_context_create(['http' => ['timeout' => 60, 'follow_location' => 1, 'user_agent' => 'ad-go-sync-assets']]);
        $body    = @file_get_contents($url, false, $context);

        if ($body === false || $body === '') {
            throw new RuntimeException('download failed: ' . $url);
        }

        return $body;
    }
}
